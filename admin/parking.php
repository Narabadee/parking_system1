<?php
require_once "../config.php";

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Handle spot status update if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_spot"])) {
    $spotId = $_POST["spot_id"];
    $newStatus = $_POST["status"];
    
    $updateSql = "UPDATE parking_spots SET status = ? WHERE spot_id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "si", $newStatus, $spotId);
    
    if (mysqli_stmt_execute($stmt)) {
        $successMessage = "Parking spot status updated successfully!";
    } else {
        $errorMessage = "Error updating parking spot status: " . mysqli_error($conn);
    }
}

// Handle record update if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_record"])) {
    $recordId = $_POST["record_id"];
    $exitTime = $_POST["exit_time"] ? $_POST["exit_time"] : NULL;
    $fee = $_POST["fee"] ? $_POST["fee"] : NULL;
    $paymentStatus = $_POST["payment_status"];
    $spotId = $_POST["spot_id"];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update the parking record
        $updateRecordSql = "UPDATE parking_records SET exit_time = ?, fee = ?, payment_status = ? WHERE record_id = ?";
        $stmt = mysqli_prepare($conn, $updateRecordSql);
        mysqli_stmt_bind_param($stmt, "sdsi", $exitTime, $fee, $paymentStatus, $recordId);
        mysqli_stmt_execute($stmt);
        
        // If exit time is provided, update spot status to free
        if ($exitTime) {
            $updateSpotSql = "UPDATE parking_spots SET status = 'free' WHERE spot_id = ?";
            $stmt = mysqli_prepare($conn, $updateSpotSql);
            mysqli_stmt_bind_param($stmt, "i", $spotId);
            mysqli_stmt_execute($stmt);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        $successMessage = "Parking record updated successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $errorMessage = "Error updating parking record: " . $e->getMessage();
    }
}

// Get all parking zones
$zonesSql = "SELECT * FROM parking_zones ORDER BY zone_name";
$zonesResult = mysqli_query($conn, $zonesSql);
$zones = [];
while ($zone = mysqli_fetch_assoc($zonesResult)) {
    $zones[] = $zone;
}

// Get all active parking records
$activeRecordsSql = "SELECT pr.*, ps.spot_number, pz.zone_name, u.first_name, u.last_name, u.tel 
                    FROM parking_records pr
                    JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                    JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                    JOIN user u ON pr.user_id = u.user_id
                    WHERE pr.exit_time IS NULL 
                    AND pr.confirmation_status = 'confirmed'
                    ORDER BY pr.entry_time DESC";
$activeRecordsResult = mysqli_query($conn, $activeRecordsSql);

// Get recent completed parking records
$recentRecordsSql = "SELECT pr.*, ps.spot_number, pz.zone_name, u.first_name, u.last_name, u.tel 
                    FROM parking_records pr
                    JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                    JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                    JOIN user u ON pr.user_id = u.user_id
                    WHERE pr.exit_time IS NOT NULL 
                    OR pr.confirmation_status = 'expired'
                    ORDER BY pr.exit_time DESC, pr.reservation_time DESC
                    LIMIT 10";
$recentRecordsResult = mysqli_query($conn, $recentRecordsSql);

// Get pending reservations
$pendingReservationsSql = "SELECT pr.*, ps.spot_number, pz.zone_name, u.first_name, u.last_name,
                          TIMESTAMPDIFF(SECOND, NOW(), 
                            DATE_ADD(pr.reservation_time, INTERVAL 1 MINUTE)) as seconds_remaining
                          FROM parking_records pr
                          JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                          JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                          JOIN user u ON pr.user_id = u.user_id
                          WHERE pr.confirmation_status = 'pending'
                          AND pr.reservation_time >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                          ORDER BY pr.reservation_time ASC";

// Function to get parking spots by zone
function getParkingSpotsByZone($conn, $zoneId) {
    $spotsSql = "SELECT ps.*, 
                 CASE 
                    WHEN ps.status = 'occupied' AND pr.confirmation_status = 'expired' THEN 'free'
                    ELSE ps.status 
                 END as current_status
                 FROM parking_spots ps
                 LEFT JOIN parking_records pr ON ps.spot_id = pr.spot_id 
                    AND pr.exit_time IS NULL
                 WHERE ps.zone_id = ?
                 ORDER BY ps.spot_number";
    $stmt = mysqli_prepare($conn, $spotsSql);
    mysqli_stmt_bind_param($stmt, "i", $zoneId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $spots = [];
    while ($spot = mysqli_fetch_assoc($result)) {
        $spots[] = $spot;
    }
    
    return $spots;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Management - Admin</title>
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
            height: 100px;
            margin: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }
        .parking-spot.free {
            background-color: #4CAF50; /* Green */
            color: white;
        }
        .parking-spot.occupied {
            background-color: #9e9e9e; /* Gray */
            color: white;
        }
        .parking-zone {
            margin-bottom: 30px;
            padding: 15px;
            border-radius: 5px;
            background-color: #f8f9fc;
        }
        .spot-number {
            font-size: 18px;
            font-weight: bold;
        }
        .update-form {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: white;
            z-index: 1000;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 200px;
        }
        .parking-spot:hover .update-form {
            display: block;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="text-center py-4">
                    <h4>Admin Panel</h4>
                </div>
                <hr class="sidebar-divider">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt icon"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="parking.php" class="active">
                            <i class="fas fa-parking icon"></i>Parking Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php">
                            <i class="fas fa-users icon"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php">
                            <i class="fas fa-chart-bar icon"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt icon"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <!-- Alert Messages -->
                <?php if (isset($successMessage) || isset($errorMessage)): ?>
                <div class="alert-container">
                    <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Parking Management</h1>
                </div>

                <!-- Parking Zones -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Parking Spots by Zone</h6>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="parkingZonesTabs" role="tablist">
                            <?php foreach ($zones as $index => $zone): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                        id="zone-<?php echo $zone['zone_id']; ?>-tab" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#zone-<?php echo $zone['zone_id']; ?>" 
                                        type="button" 
                                        role="tab" 
                                        aria-controls="zone-<?php echo $zone['zone_id']; ?>" 
                                        aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                    <?php echo htmlspecialchars($zone['zone_name']); ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tab-content" id="parkingZonesContent">
                            <?php foreach ($zones as $index => $zone): ?>
                            <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                                id="zone-<?php echo $zone['zone_id']; ?>" 
                                role="tabpanel" 
                                aria-labelledby="zone-<?php echo $zone['zone_id']; ?>-tab">
                                <div class="parking-zone">
                                    <h5><?php echo htmlspecialchars($zone['zone_name']); ?> - <?php echo htmlspecialchars($zone['description']); ?></h5>
                                    <div class="d-flex flex-wrap">
                                        <?php 
                                        $spots = getParkingSpotsByZone($conn, $zone['zone_id']);
                                        foreach ($spots as $spot): 
                                        ?>
                                        <div class="parking-spot <?php echo $spot['current_status'] == 'free' ? 'free' : 'occupied'; ?>" data-spot-id="<?php echo $spot['spot_id']; ?>">
                                            <div class="spot-number"><?php echo htmlspecialchars($spot['spot_number']); ?></div>
                                            <div class="update-form">
                                                <form method="post" action="">
                                                    <input type="hidden" name="spot_id" value="<?php echo $spot['spot_id']; ?>">
                                                    <div class="mb-2">
                                                        <select name="status" class="form-select form-select-sm">
                                                            <option value="free" <?php echo $spot['current_status'] === 'free' ? 'selected' : ''; ?>>Free</option>
                                                            <option value="occupied" <?php echo $spot['current_status'] === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" name="update_spot" class="btn btn-primary btn-sm w-100">Update</button>
                                                </form>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Pending Reservations -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pending Reservations</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Spot</th>
                                        <th>Vehicle</th>
                                        <th>Reserved At</th>
                                        <th>Time Remaining</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = mysqli_query($conn, $pendingReservationsSql);
                                    while ($reservation = mysqli_fetch_assoc($result)):
                                    ?>
                                    <tr id="reservation-<?php echo $reservation['record_id']; ?>">
                                        <td><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['zone_name'] . ' - ' . $reservation['spot_number']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['vehicle_number']); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($reservation['reservation_time'])); ?></td>
                                        <td>
                                            <div class="countdown" data-seconds="<?php echo $reservation['seconds_remaining']; ?>">
                                                <?php echo gmdate('i:s', max(0, $reservation['seconds_remaining'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm confirm-btn" 
                                                    onclick="confirmReservation(<?php echo $reservation['record_id']; ?>)">
                                                Confirm
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="cancelReservation(<?php echo $reservation['record_id']; ?>)">
                                                Cancel
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Active Parking Records -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Active Parking Records</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Spot</th>
                                        <th>Zone</th>
                                        <th>Vehicle</th>
                                        <th>Entry Time</th>
                                        <th>Phone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($activeRecordsResult) > 0): ?>
                                        <?php while ($record = mysqli_fetch_assoc($activeRecordsResult)): ?>
                                        <tr>
                                            <td><?php echo $record['record_id']; ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['spot_number']); ?></td>
                                            <td><?php echo htmlspecialchars($record['zone_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['vehicle_number']); ?></td>
                                            <td><?php echo htmlspecialchars($record['entry_time']); ?></td>
                                            <td><?php echo htmlspecialchars($record['tel']); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateRecordModal<?php echo $record['record_id']; ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                                
                                                <!-- Update Record Modal -->
                                                <div class="modal fade" id="updateRecordModal<?php echo $record['record_id']; ?>" tabindex="-1" aria-labelledby="updateRecordModalLabel<?php echo $record['record_id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="updateRecordModalLabel<?php echo $record['record_id']; ?>">Update Parking Record</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form method="post" action="">
                                                                    <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                                                    <input type="hidden" name="spot_id" value="<?php echo $record['spot_id']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="user<?php echo $record['record_id']; ?>" class="form-label">User</label>
                                                                        <input type="text" class="form-control" id="user<?php echo $record['record_id']; ?>" value="<?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>" disabled>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="vehicle<?php echo $record['record_id']; ?>" class="form-label">Vehicle Number</label>
                                                                        <input type="text" class="form-control" id="vehicle<?php echo $record['record_id']; ?>" value="<?php echo htmlspecialchars($record['vehicle_number']); ?>" disabled>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="parking<?php echo $record['record_id']; ?>" class="form-label">Parking Spot</label>
                                                                        <input type="text" class="form-control" id="parking<?php echo $record['record_id']; ?>" value="<?php echo htmlspecialchars($record['zone_name'] . ' - ' . $record['spot_number']); ?>" disabled>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="entry_time<?php echo $record['record_id']; ?>" class="form-label">Entry Time</label>
                                                                        <input type="text" class="form-control" id="entry_time<?php echo $record['record_id']; ?>" value="<?php echo htmlspecialchars($record['entry_time']); ?>" disabled>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="exit_time<?php echo $record['record_id']; ?>" class="form-label">Exit Time</label>
                                                                        <input type="datetime-local" class="form-control" id="exit_time<?php echo $record['record_id']; ?>" name="exit_time">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="fee<?php echo $record['record_id']; ?>" class="form-label">Fee (฿)</label>
                                                                        <input type="number" step="0.01" class="form-control" id="fee<?php echo $record['record_id']; ?>" name="fee">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="payment_status<?php echo $record['record_id']; ?>" class="form-label">Payment Status</label>
                                                                        <select class="form-select" id="payment_status<?php echo $record['record_id']; ?>" name="payment_status">
                                                                            <option value="pending" <?php echo $record['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="paid" <?php echo $record['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                                        </select>
                                                                    </div>
                                                                    
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                        <button type="submit" name="update_record" class="btn btn-primary">Save changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No active parking records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Completed Parking Records -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Completed Parking Records</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Spot</th>
                                        <th>Vehicle</th>
                                        <th>Entry Time</th>
                                        <th>Exit Time</th>
                                        <th>Duration</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recentRecordsResult) > 0): ?>
                                        <?php while ($record = mysqli_fetch_assoc($recentRecordsResult)): ?>
                                        <tr>
                                            <td><?php echo $record['record_id']; ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['zone_name'] . ' - ' . $record['spot_number']); ?></td>
                                            <td><?php echo htmlspecialchars($record['vehicle_number']); ?></td>
                                            <td><?php echo htmlspecialchars($record['entry_time']); ?></td>
                                            <td><?php echo htmlspecialchars($record['exit_time']); ?></td>
                                            <td>
                                                <?php 
                                                $entry = new DateTime($record['entry_time']);
                                                $exit = new DateTime($record['exit_time']);
                                                $interval = $entry->diff($exit);
                                                echo $interval->format('%a days, %h hours, %i minutes');
                                                ?>
                                            </td>
                                            <td>฿<?php echo htmlspecialchars($record['fee']); ?></td>
                                            <td>
                                                <?php
                                                if ($record['confirmation_status'] == 'expired') {
                                                    echo '<span class="badge bg-secondary">Expired</span>';
                                                } elseif ($record['payment_status'] == 'paid') {
                                                    echo '<span class="badge bg-success">Paid</span>';
                                                } else {
                                                    echo '<span class="badge bg-warning">Pending Payment</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No completed parking records found</td>
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

    <!-- Add Parking Record Modal -->
    <div class="modal fade" id="addParkingRecordModal" tabindex="-1" aria-labelledby="addParkingRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addParkingRecordModalLabel">Add New Parking Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addParkingRecordForm" method="post" action="add_record.php">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Select User</option>
                                <?php
                                $usersSql = "SELECT user_id, first_name, last_name FROM user ORDER BY first_name, last_name";
                                $usersResult = mysqli_query($conn, $usersSql);
                                while ($user = mysqli_fetch_assoc($usersResult)) {
                                    echo '<option value="' . $user['user_id'] . '">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="zone_id" class="form-label">Parking Zone</label>
                            <select class="form-select" id="zone_id" name="zone_id" required>
                                <option value="">Select Zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['zone_id']; ?>"><?php echo htmlspecialchars($zone['zone_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="spot_id" class="form-label">Parking Spot</label>
                            <select class="form-select" id="spot_id" name="spot_id" required disabled>
                                <option value="">Select Spot</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vehicle_number" class="form-label">Vehicle Number</label>
                            <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="entry_time" class="form-label">Entry Time</label>
                            <input type="datetime-local" class="form-control" id="entry_time" name="entry_time" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Automatically dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Handle zone selection to load available spots
            document.getElementById('zone_id').addEventListener('change', function() {
                const zoneId = this.value;
                const spotSelect = document.getElementById('spot_id');
                
                // Clear current options
                spotSelect.innerHTML = '<option value="">Select Spot</option>';
                
                if (zoneId) {
                    // Enable the spot select
                    spotSelect.disabled = false;
                    
                    // Fetch available spots for the selected zone
                    fetch(`get_available_spots.php?zone_id=${zoneId}`)
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(spot => {
                                const option = document.createElement('option');
                                option.value = spot.spot_id;
                                option.textContent = spot.spot_number;
                                spotSelect.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching spots:', error);
                        });
                } else {
                    // Disable the spot select if no zone is selected
                    spotSelect.disabled = true;
                }
            });

            // Countdown timer for pending reservations
            const countdowns = document.querySelectorAll('.countdown');
            
            countdowns.forEach(countdown => {
                let seconds = parseInt(countdown.dataset.seconds);
                
                const timer = setInterval(() => {
                    seconds--;
                    
                    if (seconds <= 0) {
                        clearInterval(timer);
                        const row = countdown.closest('tr');
                        row.remove();
                        // Optionally show a message that the reservation expired
                    } else {
                        countdown.textContent = new Date(seconds * 1000).toISOString().substr(14, 5);
                    }
                }, 1000);
            });
        });

        // Check for expired reservations more frequently (every 10 seconds instead of every minute)
        setInterval(checkExpiredReservations, 10000);

        // Functions for confirming/canceling reservations
        function confirmReservation(recordId) {
            fetch('confirm_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `record_id=${recordId}&action=confirm`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`reservation-${recordId}`).remove();
                }
            });
        }

        function cancelReservation(recordId) {
            if (confirm('Are you sure you want to cancel this reservation?')) {
                fetch('confirm_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `record_id=${recordId}&action=cancel`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`reservation-${recordId}`).remove();
                    }
                });
            }
        }

        // Add this to the existing JavaScript in parking.php
        function refreshSpots() {
            // Check expired reservations
            fetch('check_expired_reservations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to update spot status
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Check every 10 seconds
        setInterval(refreshSpots, 10000);

        // Add this to your existing JavaScript
        function updateSpotStatus(spotId) {
            const spotElement = document.querySelector(`[data-spot-id="${spotId}"]`);
            if (spotElement) {
                spotElement.classList.remove('occupied');
                spotElement.classList.add('free');
            }
        }

        // Modify the existing checkExpiredReservations function
        function checkExpiredReservations() {
            fetch('check_expired_reservations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update spot status without page reload
                        location.reload(); // For now, reload the page
                        // TODO: Implement real-time spot status updates
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>