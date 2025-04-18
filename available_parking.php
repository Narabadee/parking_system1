<?php
require_once "config.php";

date_default_timezone_set('Asia/Bangkok');
mysqli_query($conn, "SET time_zone = '+07:00'"); // Use your local time zone, e.g., Asia/Bangkok

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user information
$userId = $_SESSION["user_id"];
$firstName = $_SESSION["first_name"];
$lastName = $_SESSION["last_name"];

// Process parking reservation if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["reserve_spot"])) {
    $spotId = $_POST["spot_id"];
    $vehicleNumber = $_POST["vehicle_number"];
    
    // Check if spot is still available
    $checkSpotSql = "SELECT status FROM parking_spots WHERE spot_id = ?";
    $stmt = mysqli_prepare($conn, $checkSpotSql);
    mysqli_stmt_bind_param($stmt, "i", $spotId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row && $row['status'] == 'free') {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update spot status to occupied
            $updateSpotSql = "UPDATE parking_spots SET status = 'occupied' WHERE spot_id = ?";
            $stmt = mysqli_prepare($conn, $updateSpotSql);
            mysqli_stmt_bind_param($stmt, "i", $spotId);
            mysqli_stmt_execute($stmt);
            
            // Create parking record
            $createRecordSql = "INSERT INTO parking_records (user_id, spot_id, vehicle_number, entry_time, reservation_time, confirmation_status) 
                               VALUES (?, ?, ?, NOW(), NOW(), 'pending')";
            $stmt = mysqli_prepare($conn, $createRecordSql);
            mysqli_stmt_bind_param($stmt, "iis", $userId, $spotId, $vehicleNumber);
            mysqli_stmt_execute($stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $_SESSION['success_message'] = "Parking spot reserved successfully!";
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Error reserving parking spot: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "This parking spot is no longer available.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: available_parking.php");
    exit();
}

// Check if user has an active parking spot
$activeParkingSql = "SELECT pr.*, ps.spot_number, pz.zone_name 
                     FROM parking_records pr
                     JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                     JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                     WHERE pr.user_id = ? 
                     AND pr.exit_time IS NULL 
                     AND pr.confirmation_status NOT IN ('expired', 'cancelled', 'pending')
                     AND pr.payment_status != 'expired'
                     LIMIT 1";
$stmt = mysqli_prepare($conn, $activeParkingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$activeResult = mysqli_stmt_get_result($stmt);
$hasActiveParking = mysqli_num_rows($activeResult) > 0;
$activeParking = $hasActiveParking ? mysqli_fetch_assoc($activeResult) : null;

// Pending reservation alert for available_parking.php
$pendingSql = "SELECT pr.*, ps.spot_number, pz.zone_name, pr.reservation_time
               FROM parking_records pr
               JOIN parking_spots ps ON pr.spot_id = ps.spot_id
               JOIN parking_zones pz ON ps.zone_id = pz.zone_id
               WHERE pr.user_id = ?
               AND pr.confirmation_status = 'pending'
               AND pr.exit_time IS NULL
               ORDER BY pr.reservation_time DESC
               LIMIT 1";
$stmt = mysqli_prepare($conn, $pendingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$pendingResult = mysqli_stmt_get_result($stmt);

if ($pendingResult && mysqli_num_rows($pendingResult) > 0) {
    $pending = mysqli_fetch_assoc($pendingResult);
    
    // Convert reservation_time to Unix timestamp using DateTime to handle time zones properly
    $reservationDateTime = new DateTime($pending['reservation_time'], new DateTimeZone('Asia/Bangkok'));
    $reservedAt = $reservationDateTime->getTimestamp();
    $now = time();
    
    // Calculate time difference and limit to 60 seconds
    $timeElapsed = $now - $reservedAt;
    $secondsLeft = max(0, 60 - $timeElapsed);
    
    // If more than 1 minute has passed, expire the reservation
    if ($timeElapsed > 60) {
        $expireSql = "UPDATE parking_records 
                      SET confirmation_status = 'expired' 
                      WHERE record_id = ? AND confirmation_status = 'pending'";
        $stmt = mysqli_prepare($conn, $expireSql);
        mysqli_stmt_bind_param($stmt, "i", $pending['record_id']);
        mysqli_stmt_execute($stmt);
        
        // Free up the parking spot
        $updateSpotSql = "UPDATE parking_spots 
                         SET status = 'free' 
                         WHERE spot_id = ?";
        $stmt = mysqli_prepare($conn, $updateSpotSql);
        mysqli_stmt_bind_param($stmt, "i", $pending['spot_id']);
        mysqli_stmt_execute($stmt);
        
        // Redirect to refresh the page
        header("Location: available_parking.php");
        exit();
    }
}

// Get all parking zones
$zonesSql = "SELECT * FROM parking_zones ORDER BY zone_name";
$zonesResult = mysqli_query($conn, $zonesSql);
$zones = [];
while ($zoneRow = mysqli_fetch_assoc($zonesResult)) {
    $zones[] = $zoneRow;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Parking - Parking Management System</title>
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
        .parking-spot {
            width: 100px;
            height: 80px;
            margin: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            font-weight: bold;
            transition: all 0.3s;
        }
        .parking-spot.free {
            background-color: #1cc88a;
        }
        .parking-spot.occupied {
            background-color: #858796;
            cursor: not-allowed;
        }
        .parking-spot:hover {
            transform: scale(1.05);
        }
        .zone-container {
            margin-bottom: 30px;
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .zone-header {
            background-color: #f8f9fc;
            padding: 10px 15px;
            border-bottom: 1px solid #e3e6f0;
        }
        .zone-spots {
            display: flex;
            flex-wrap: wrap;
            padding: 15px;
        }
        .modal-header {
            background-color: #4e73df;
            color: white;
        }
        .legend {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 5px;
            border-radius: 3px;
        }
        .legend-free {
            background-color: #1cc88a;
        }
        .legend-occupied {
            background-color: #858796;
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
                        <a href="available_parking.php" class="active">
                            <i class="fas fa-parking icon"></i>Available Parking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="service_fee.php">
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
                <h2 class="mb-4">Available Parking Spots</h2>
                
                <!-- Parking Rates Card -->
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

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color legend-free"></div>
                        <span>Free</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-occupied"></div>
                        <span>Occupied</span>
                    </div>
                </div>
                
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
                
                <?php if ($hasActiveParking): ?>
                <div class="alert alert-info mb-4">
                    <h5><i class="fas fa-info-circle me-2"></i>You have an active parking reservation</h5>
                    <p>Vehicle: <strong><?php echo htmlspecialchars($activeParking['vehicle_number']); ?></strong></p>
                    <p>Spot: <strong><?php echo htmlspecialchars($activeParking['zone_name'] . ' - ' . $activeParking['spot_number']); ?></strong></p>
                    <p>Entry Time: <strong><?php echo htmlspecialchars($activeParking['entry_time']); ?></strong></p>
                    <a href="service_fee.php?record_id=<?php echo $activeParking['record_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt me-2"></i>Exit Parking
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($pendingResult && mysqli_num_rows($pendingResult) > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Reservation Pending!</strong>
                    <br>
                    Your reservation for <b><?php echo htmlspecialchars($pending['zone_name'] . ' - ' . $pending['spot_number']); ?></b> is pending admin confirmation.
                    <br>
                    <span class="text-danger">Please go to the parking spot within <span id="pending-countdown"><?php echo $secondsLeft; ?></span> seconds and wait for admin confirmation.</span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <script>
                // Countdown timer for pending reservation
                let pendingSeconds = <?php echo $secondsLeft; ?>;
                const pendingCountdown = document.getElementById('pending-countdown');
                if (pendingCountdown) {
                    const timer = setInterval(() => {
                        pendingSeconds--;
                        if (pendingSeconds <= 0) {
                            clearInterval(timer);
                            pendingCountdown.textContent = "0";
                            location.reload();
                        } else {
                            pendingCountdown.textContent = pendingSeconds;
                        }
                    }, 1000);
                }
                </script>
                <?php endif; ?>
                
                <?php foreach ($zones as $zone): ?>
                <div class="zone-container">
                    <div class="zone-header">
                        <h5 class="mb-0"><?php echo htmlspecialchars($zone['zone_name']); ?></h5>
                        <small><?php echo htmlspecialchars($zone['description']); ?></small>
                    </div>
                    <div class="zone-spots">
                        <?php
                        // Get parking spots for this zone
                        $spotsSql = "SELECT ps.*,
                                     CASE 
                                         WHEN ps.status = 'occupied' AND 
                                              EXISTS (
                                                  SELECT 1 FROM parking_records pr 
                                                  WHERE pr.spot_id = ps.spot_id 
                                                  AND pr.exit_time IS NULL
                                                  AND pr.confirmation_status IN ('expired', 'cancelled')
                                              ) 
                                         THEN 'free'
                                         ELSE ps.status 
                                     END as current_status
                                     FROM parking_spots ps
                                     WHERE ps.zone_id = ?
                                     ORDER BY ps.spot_number";
                        $stmt = mysqli_prepare($conn, $spotsSql);
                        mysqli_stmt_bind_param($stmt, "i", $zone['zone_id']);
                        mysqli_stmt_execute($stmt);
                        $spotsResult = mysqli_stmt_get_result($stmt);
                        
                        while ($spot = mysqli_fetch_assoc($spotsResult)):
                            $isFree = $spot['status'] == 'free';
                            $spotClass = $isFree ? 'free' : 'occupied';
                            $isDisabled = !$isFree || $hasActiveParking;
                        ?>
                        <div class="parking-spot <?php echo $spotClass; ?>"
                             <?php if (!$isDisabled): ?>
                             data-bs-toggle="modal" 
                             data-bs-target="#reserveModal"
                             data-spot-id="<?php echo $spot['spot_id']; ?>"
                             data-spot-number="<?php echo htmlspecialchars($spot['spot_number']); ?>"
                             data-zone-name="<?php echo htmlspecialchars($zone['zone_name']); ?>"
                             <?php endif; ?>>
                            <?php echo htmlspecialchars($spot['spot_number']); ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Reserve Modal -->
    <div class="modal fade" id="reserveModal" tabindex="-1" aria-labelledby="reserveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reserveModalLabel">Reserve Parking Spot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="spot_id" id="spot_id">
                        <p>You are about to reserve spot <span id="selected_spot"></span> in <span id="selected_zone"></span>.</p>
                        <div class="mb-3">
                            <label for="vehicle_number" class="form-label">Vehicle Number</label>
                            <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reserve_spot" class="btn btn-primary">Reserve Spot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pass data to modal when shown
        const reserveModal = document.getElementById('reserveModal');
        if (reserveModal) {
            reserveModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const spotId = button.getAttribute('data-spot-id');
                const spotNumber = button.getAttribute('data-spot-number');
                const zoneName = button.getAttribute('data-zone-name');
                
                const spotIdInput = document.getElementById('spot_id');
                const selectedSpotSpan = document.getElementById('selected_spot');
                const selectedZoneSpan = document.getElementById('selected_zone');
                
                spotIdInput.value = spotId;
                selectedSpotSpan.textContent = spotNumber;
                selectedZoneSpan.textContent = zoneName;
            });
        }

        function checkExpiredReservations() {
            fetch('check_expired_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasExpired) {
                        location.reload();
                    }
                });
        }

        // Check every 10 seconds
        setInterval(checkExpiredReservations, 10000);
    </script>
</body>
</html>