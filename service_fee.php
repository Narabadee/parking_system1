<?php
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
$activeParkingSql = "SELECT pr.record_id, pr.vehicle_number, ps.spot_id, ps.spot_number, pz.zone_name, pz.zone_id, 
                     pr.entry_time, TIMEDIFF(NOW(), pr.entry_time) as duration 
                     FROM parking_records pr
                     JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                     JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                     WHERE pr.user_id = ? AND pr.exit_time IS NULL ";
                     
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
$activeResult = mysqli_stmt_get_result($stmt);
$hasActiveParking = mysqli_num_rows($activeResult) > 0;
$activeParking = $hasActiveParking ? mysqli_fetch_assoc($activeResult) : null;

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

// Calculate parking fee if there's an active parking
$fee = 0;
if ($hasActiveParking) {
    $fee = calculateParkingFee($activeParking['duration'], $activeParking['zone_id']);
}

// Get parking history
$historySql = "SELECT pr.record_id, pr.vehicle_number, ps.spot_number, pz.zone_name, 
              pr.entry_time, pr.exit_time, pr.fee, pr.payment_status,
              TIMEDIFF(IFNULL(pr.exit_time, NOW()), pr.entry_time) as duration 
              FROM parking_records pr
              JOIN parking_spots ps ON pr.spot_id = ps.spot_id
              JOIN parking_zones pz ON ps.zone_id = pz.zone_id
              WHERE pr.user_id = ? AND pr.exit_time IS NOT NULL
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
                
                <?php if ($hasActiveParking): ?>
                <!-- Current Parking Fee -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-car me-2"></i>Current Parking Session</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="fee-card">
                                    <div class="fee-label">Current Fee</div>
                                    <div class="fee-amount"><?php echo number_format($fee, 2); ?> ฿</div>
                                    <div class="fee-info">
                                        <div><strong>Duration:</strong> <?php echo $activeParking['duration']; ?></div>
                                        <div><strong>Vehicle:</strong> <?php echo htmlspecialchars($activeParking['vehicle_number']); ?></div>
                                        <div><strong>Spot:</strong> <?php echo htmlspecialchars($activeParking['zone_name'] . ' - ' . $activeParking['spot_number']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5>Payment Details</h5>
                                <form method="post" action="">
                                    <input type="hidden" name="record_id" value="<?php echo $activeParking['record_id']; ?>">
                                    <input type="hidden" name="amount" value="<?php echo $fee; ?>">
                                    
                                    <div class="payment-methods mb-3">
                                        <label class="form-label">Payment Method</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="method_cash" value="cash" checked>
                                            <label class="form-check-label" for="method_cash">
                                                <i class="fas fa-money-bill-wave me-2"></i>Cash
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="method_credit" value="credit_card">
                                            <label class="form-check-label" for="method_credit">
                                                <i class="fas fa-credit-card me-2"></i>Credit Card
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="method_qr" value="qr_code">
                                            <label class="form-check-label" for="method_qr">
                                                <i class="fas fa-qrcode me-2"></i>QR Payment
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        When you complete payment, your vehicle will be marked as exited and the parking spot will be available for others.
                                    </div>
                                    
                                    <button type="submit" name="make_payment" class="btn btn-primary">
                                        <i class="fas fa-check me-2"></i>Complete Payment and Exit
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    You don't have any active parking sessions at the moment.
                    <a href="available_parking.php" class="alert-link">Reserve a parking spot</a>.
                </div>
                <?php endif; ?>
                
                <!-- Parking History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Parking History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Vehicle Number</th>
                                        <th>Spot</th>
                                        <th>Entry Time</th>
                                        <th>Exit Time</th>
                                        <th>Duration</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($historyResult) > 0): ?>
                                        <?php while ($history = mysqli_fetch_assoc($historyResult)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($history['vehicle_number']); ?></td>
                                            <td><?php echo htmlspecialchars($history['zone_name'] . ' - ' . $history['spot_number']); ?></td>
                                            <td><?php echo htmlspecialchars($history['entry_time']); ?></td>
                                            <td><?php echo htmlspecialchars($history['exit_time']); ?></td>
                                            <td><?php echo htmlspecialchars($history['duration']); ?></td>
                                            <td><?php echo number_format($history['fee'], 2); ?> ฿</td>
                                            <td>
                                                <?php if ($history['payment_status'] == 'paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No parking history found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>