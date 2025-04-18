<?php
date_default_timezone_set('Asia/Bangkok'); // or your local timezone
require_once "config.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user information
$userId = $_SESSION["user_id"];
$firstName = $_SESSION["first_name"];
$lastName = $_SESSION["last_name"];

// Check if specific record is being accessed
$recordId = isset($_GET['record_id']) ? intval($_GET['record_id']) : null;

// Process payment if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["make_payment"])) {
    $recordId = $_POST["record_id"];
    $amount = $_POST["amount"];
    $paymentMethod = $_POST["payment_method"];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update parking record
        $updateRecordSql = "UPDATE parking_records SET 
                           exit_time = NOW(), 
                           fee = ?, 
                           payment_status = 'paid' 
                           WHERE record_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $updateRecordSql);
        mysqli_stmt_bind_param($stmt, "dii", $amount, $recordId, $userId);
        mysqli_stmt_execute($stmt);
        
        // Get spot ID to free up
        $getSpotSql = "SELECT spot_id FROM parking_records WHERE record_id = ?";
        $stmt = mysqli_prepare($conn, $getSpotSql);
        mysqli_stmt_bind_param($stmt, "i", $recordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $spotId = $row['spot_id'];
        
        // Update spot status to free
        $updateSpotSql = "UPDATE parking_spots SET status = 'free' WHERE spot_id = ?";
        $stmt = mysqli_prepare($conn, $updateSpotSql);
        mysqli_stmt_bind_param($stmt, "i", $spotId);
        mysqli_stmt_execute($stmt);
        
        // Create payment record
        $referenceNumber = 'PAY-' . time() . '-' . $userId;
        $createPaymentSql = "INSERT INTO payments (record_id, amount, payment_date, payment_method, reference_number) 
                            VALUES (?, ?, NOW(), ?, ?)";
        $stmt = mysqli_prepare($conn, $createPaymentSql);
        mysqli_stmt_bind_param($stmt, "idss", $recordId, $amount, $paymentMethod, $referenceNumber);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Set success message
        $_SESSION['success_message'] = "Payment successful! Your parking fee has been paid.";
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
    }
}

// Get active parking record
$activeParkingSql = "SELECT pr.record_id, pr.vehicle_number, ps.spot_number, pz.zone_name, 
                     pr.entry_time, pr.confirmation_status, ps.zone_id
                     FROM parking_records pr
                     JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                     JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                     WHERE pr.user_id = ? 
                     AND pr.exit_time IS NULL 
                     AND pr.confirmation_status NOT IN ('expired', 'cancelled', 'pending')
                     AND pr.payment_status != 'expired'";

if ($recordId) {
    $activeParkingSql .= "AND pr.record_id = ? ";
}

$activeParkingSql .= "LIMIT 1";

$stmt = mysqli_prepare($conn, $activeParkingSql);

if ($recordId) {
    mysqli_stmt_bind_param($stmt, "ii", $userId, $recordId);
} else {
    mysqli_stmt_bind_param($stmt, "i", $userId);
}

mysqli_stmt_execute($stmt);
$activeParkingResult = mysqli_stmt_get_result($stmt);
$hasActiveParking = mysqli_num_rows($activeParkingResult) > 0;

// Function to calculate parking fee with increasing hourly rate
function calculateParkingFee($duration, $zoneId) {
    // Duration is in HH:MM:SS format
    list($hours, $minutes, $seconds) = explode(':', $duration);
    $totalHours = $hours + ($minutes / 60) + ($seconds / 3600);
    
    // Round up to the nearest hour (minimum 1 hour)
    $billableHours = max(1, ceil($totalHours));
    
    // Base rates vary by zone
    $baseRates = [
        1 => 30, // Zone A: 30 baht base rate for first hour
        2 => 25, // Zone B: 25 baht base rate for first hour
        3 => 20, // Zone C: 20 baht base rate for first hour
        4 => 50  // Zone D (VIP): 50 baht base rate for first hour
    ];
    
    // Default rate if zone is not found
    $baseRate = isset($baseRates[$zoneId]) ? $baseRates[$zoneId] : 30;
    
    // Calculate fee with increasing rate per hour
    $fee = 0;
    for ($i = 1; $i <= $billableHours; $i++) {
        // Each subsequent hour costs 10 baht more than the previous hour
        $fee += $baseRate + (($i - 1) * 10);
    }
    
    return $fee;
}

// Update the fee calculation to use entry_time only for confirmed reservations
$feeSql = "SELECT pr.*, ps.zone_id,
           CASE 
               WHEN pr.confirmation_status = 'confirmed' 
               THEN TIMEDIFF(NOW(), pr.entry_time)
               ELSE NULL 
           END as duration
           FROM parking_records pr
           JOIN parking_spots ps ON pr.spot_id = ps.spot_id
           WHERE pr.record_id = ? AND pr.user_id = ?";

$stmt = mysqli_prepare($conn, $feeSql);
mysqli_stmt_bind_param($stmt, "ii", $recordId, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$parkingRecord = mysqli_fetch_assoc($result);

// Calculate fee only if parking record exists and is confirmed
if ($parkingRecord && $parkingRecord['confirmation_status'] === 'confirmed' && $parkingRecord['duration']) {
    $fee = calculateParkingFee($parkingRecord['duration'], $parkingRecord['zone_id']);
} else {
    $fee = 0;
}

// Get parking history
$historySql = "SELECT pr.record_id, pr.vehicle_number, ps.spot_number, pz.zone_name, 
              pr.entry_time, pr.exit_time, pr.fee, pr.payment_status,
              pr.confirmation_status,
              TIMEDIFF(IFNULL(pr.exit_time, NOW()), pr.entry_time) as duration 
              FROM parking_records pr
              JOIN parking_spots ps ON pr.spot_id = ps.spot_id
              JOIN parking_zones pz ON ps.zone_id = pz.zone_id
              WHERE pr.user_id = ? 
              AND (pr.exit_time IS NOT NULL OR pr.confirmation_status IN ('expired', 'cancelled'))
              ORDER BY pr.entry_time DESC
              LIMIT 10";
$stmt = mysqli_prepare($conn, $historySql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$historyResult = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Fee - Parking Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            color: white;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .content {
            padding: 20px;
        }
        .nav-item {
            margin-bottom: 5px;
        }
        .icon {
            margin-right: 10px;
        }
        .fee-card {
            background-color: #4e73df;
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .fee-amount {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .fee-label {
            font-size: 1.2rem;
            opacity: 0.8;
        }
        .fee-info {
            margin-top: 15px;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .payment-methods .form-check {
            margin-bottom: 15px;
        }
        .modal-header {
            background-color: #4e73df;
            color: white;
        }
        .rate-table th, .rate-table td {
            padding: 10px;
            text-align: center;
        }
        .payment-form .form-check {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .payment-form .form-check:hover {
            background-color: #f8f9fa;
        }

        .payment-form .form-check-input:checked + .form-check-label {
            color: #4e73df;
        }

        .payment-form .fas {
            margin-right: 10px;
            width: 20px;
        }

        .btn-primary {
            width: 100%;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="text-center py-4">
                    <h4>Parking Management</h4>
                </div>
                <hr class="sidebar-divider">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt icon"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="available_parking.php">
                            <i class="fas fa-parking icon"></i>Available Parking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="service_fee.php" class="active">
                            <i class="fas fa-money-bill icon"></i>Service Fee
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php">
                            <i class="fas fa-user icon"></i>Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt icon"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <h2 class="mb-4">Service Fee</h2>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php
                // Check for recently expired reservations
                $checkExpiredSql = "SELECT pr.*, ps.spot_number, pz.zone_name
                                    FROM parking_records pr
                                    JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                                    JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                                    WHERE pr.user_id = ?
                                    AND pr.confirmation_status = 'expired'
                                    AND pr.exit_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                                    ORDER BY pr.exit_time DESC
                                    LIMIT 1";

                $stmt = mysqli_prepare($conn, $checkExpiredSql);
                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);
                $expiredResult = mysqli_stmt_get_result($stmt);

                if ($expiredResult && mysqli_num_rows($expiredResult) > 0) {
                    $expiredRecord = mysqli_fetch_assoc($expiredResult);
                ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Reservation Expired!</strong> Your reservation for spot 
                        <?php echo htmlspecialchars($expiredRecord['zone_name'] . ' - ' . $expiredRecord['spot_number']); ?> 
                        has expired due to no confirmation from admin within the time limit.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php
                }
                ?>

                <!-- Our Parking Rates -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Parking Rates</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Our parking rates increase per hour to encourage shorter stays:</p>
                        
                        <table class="table table-bordered rate-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Zone</th>
                                    <th>1st Hour</th>
                                    <th>2nd Hour</th>
                                    <th>3rd Hour</th>
                                    <th>Each Additional Hour</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Zone A (Main Entrance)</td>
                                    <td>30 ฿</td>
                                    <td>40 ฿</td>
                                    <td>50 ฿</td>
                                    <td>+10 ฿ per hour</td>
                                </tr>
                                <tr>
                                    <td>Zone B (Side Entrance)</td>
                                    <td>25 ฿</td>
                                    <td>35 ฿</td>
                                    <td>45 ฿</td>
                                    <td>+10 ฿ per hour</td>
                                </tr>
                                <tr>
                                    <td>Zone C (Back Entrance)</td>
                                    <td>20 ฿</td>
                                    <td>30 ฿</td>
                                    <td>40 ฿</td>
                                    <td>+10 ฿ per hour</td>
                                </tr>
                                <tr>
                                    <td>Zone D (VIP)</td>
                                    <td>50 ฿</td>
                                    <td>60 ฿</td>
                                    <td>70 ฿</td>
                                    <td>+10 ฿ per hour</td>
                                </tr>
                            </tbody>
                        </table>
                        <small class="text-muted">*Parking time is rounded up to the nearest hour.</small>
                    </div>
                </div>
                
                <!-- Current Parking Fee -->
                <?php if ($activeParkingResult && mysqli_num_rows($activeParkingResult) > 0): ?>
                    <?php 
                    $parking = mysqli_fetch_assoc($activeParkingResult);
                    // Calculate current fee based on entry_time (set when admin confirms)
                    $currentDuration = strtotime('now') - strtotime($parking['entry_time']);
                    // Format duration as HH:MM:SS
                    $formattedDuration = gmdate('H:i:s', $currentDuration);

                    // Calculate fee based on zone rates
                    $zoneId = $parking['zone_id'];
                    $baseRates = [
                        1 => 30, // Zone A: 30 baht base rate
                        2 => 25, // Zone B: 25 baht base rate
                        3 => 20, // Zone C: 20 baht base rate
                        4 => 50  // Zone D (VIP): 50 baht base rate
                    ];

                    // Get base rate for the zone
                    $baseRate = isset($baseRates[$zoneId]) ? $baseRates[$zoneId] : 30;

                    // Calculate hours (round up to nearest hour)
                    $hours = ceil($currentDuration / 3600);
                    $currentFee = $baseRate; // Start with base rate

                    // Add additional hourly charges
                    if ($hours > 1) {
                        for ($i = 2; $i <= $hours; $i++) {
                            $currentFee += $baseRate + (($i - 1) * 10);
                        }
                    }
                    ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Current Parking Session</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Vehicle:</strong> <?php echo htmlspecialchars($parking['vehicle_number']); ?><br>
                                        <strong>Spot:</strong> <?php echo htmlspecialchars($parking['zone_name'] . ' - ' . $parking['spot_number']); ?><br>
                                        <strong>Entry Time:</strong> <?php echo htmlspecialchars($parking['entry_time']); ?><br>
                                        <strong>Duration:</strong> <?php echo $formattedDuration; ?><br>
                                        <strong>Current Fee:</strong> ฿<?php echo number_format($currentFee, 2); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <form method="post" class="payment-form">
                                        <input type="hidden" name="record_id" value="<?php echo $parking['record_id']; ?>">
                                        <input type="hidden" name="amount" value="<?php echo $currentFee; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Payment Method:</strong></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" value="credit_card" id="creditCard" checked>
                                                <label class="form-check-label" for="creditCard">
                                                    <i class="fas fa-credit-card"></i> Credit Card
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" value="promptpay" id="promptPay">
                                                <label class="form-check-label" for="promptPay">
                                                    <i class="fas fa-qrcode"></i> PromptPay
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" value="cash" id="cash">
                                                <label class="form-check-label" for="cash">
                                                    <i class="fas fa-money-bill"></i> Cash
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="make_payment" class="btn btn-primary">
                                            <i class="fas fa-cash-register"></i> Pay Now
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        You don't have any active parking sessions at the moment. 
                        <a href="available_parking.php" class="alert-link">Reserve a parking spot</a>.
                    </div>
                <?php endif; ?>

                <!-- Parking History Section -->
                <div class="table-responsive mt-4">
                    <h5>Parking History</h5>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Spot</th>
                                <th>Entry Time</th>
                                <th>Exit Time</th>
                                <th>Duration</th>
                                <th>Fee</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = mysqli_fetch_assoc($historyResult)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['vehicle_number']); ?></td>
                                <td><?php echo htmlspecialchars($record['zone_name'] . ' - ' . $record['spot_number']); ?></td>
                                <td><?php echo htmlspecialchars($record['entry_time']); ?></td>
                                <td><?php echo htmlspecialchars($record['exit_time']); ?></td>
                                <td><?php echo $record['duration']; ?></td>
                                <td>฿<?php echo $record['fee'] ? htmlspecialchars($record['fee']) : '0'; ?></td>
                                <td>
                                    <?php if ($record['confirmation_status'] == 'expired'): ?>
                                        <span class="badge bg-warning">Expired</span>
                                    <?php else: ?>
                                        <span class="badge bg-<?php echo $record['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($record['payment_status'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Check for expired reservations every 30 seconds
    function checkExpiredReservations() {
        location.reload();
    }

    // Only start checking if there's an active parking session
    <?php if ($hasActiveParking): ?>
        setInterval(checkExpiredReservations, 30000);
    <?php endif; ?>
    </script>
</body>
</html>