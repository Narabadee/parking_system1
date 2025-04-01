<?php
require_once "config.php";

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// If admin, redirect to admin dashboard
if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit();
}

// Get user information
$userId = $_SESSION["user_id"];
$firstName = $_SESSION["first_name"];
$lastName = $_SESSION["last_name"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Parking Management System</title>
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
        .welcome-card {
            background-color: #f8f9fc;
            border-left: 4px solid #4e73df;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
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
                        <a href="dashboard.php" class="active">
                            <i class="fas fa-tachometer-alt icon"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="available_parking.php">
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
                <div class="welcome-card">
                    <h4>Welcome, <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>!</h4>
                    <p>This is your dashboard where you can manage your parking needs.</p>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Active Parking
                                        </div>
                                        <?php
                                        // Check if user has an active parking spot
                                        $activeParkingSql = "SELECT COUNT(*) as active FROM parking_records 
                                                            WHERE user_id = ? AND exit_time IS NULL";
                                        $stmt = mysqli_prepare($conn, $activeParkingSql);
                                        mysqli_stmt_bind_param($stmt, "i", $userId);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        $row = mysqli_fetch_assoc($result);
                                        $hasActiveParking = $row['active'] > 0;
                                        ?>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $hasActiveParking ? "You have an active parking spot" : "No active parking"; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-car fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Parking Records
                                        </div>
                                        <?php
                                        // Get total number of parking records for this user
                                        $totalRecordsSql = "SELECT COUNT(*) as total FROM parking_records WHERE user_id = ?";
                                        $stmt = mysqli_prepare($conn, $totalRecordsSql);
                                        mysqli_stmt_bind_param($stmt, "i", $userId);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        $row = mysqli_fetch_assoc($result);
                                        $totalRecords = $row['total'];
                                        ?>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $totalRecords; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-history fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Subscription Status
                                        </div>
                                        <?php
                                        // Check if user has an active subscription
                                        $subscriptionSql = "SELECT p.plan_name FROM user_subscriptions us 
                                                          JOIN subscription_plans p ON us.plan_id = p.plan_id 
                                                          WHERE us.user_id = ? AND us.status = 'active' AND us.end_date >= CURDATE()";
                                        $stmt = mysqli_prepare($conn, $subscriptionSql);
                                        mysqli_stmt_bind_param($stmt, "i", $userId);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        if (mysqli_num_rows($result) > 0) {
                                            $row = mysqli_fetch_assoc($result);
                                            $subscriptionStatus = "Active: " . $row['plan_name'];
                                        } else {
                                            $subscriptionStatus = "No active subscription";
                                        }
                                        ?>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $subscriptionStatus; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Parking Records</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Spot</th>
                                                <th>Zone</th>
                                                <th>Vehicle Number</th>
                                                <th>Entry Time</th>
                                                <th>Exit Time</th>
                                                <th>Fee</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get recent parking records for this user
                                            $recentRecordsSql = "SELECT pr.*, ps.spot_number, pz.zone_name 
                                                              FROM parking_records pr
                                                              JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                                                              JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                                                              WHERE pr.user_id = ?
                                                              ORDER BY pr.entry_time DESC
                                                              LIMIT 5";
                                            $stmt = mysqli_prepare($conn, $recentRecordsSql);
                                            mysqli_stmt_bind_param($stmt, "i", $userId);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);

                                            if (mysqli_num_rows($result) > 0) {
                                                while($row = mysqli_fetch_assoc($result)) {
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['spot_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['zone_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['vehicle_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['entry_time']); ?></td>
                                                        <td><?php echo $row['exit_time'] ? htmlspecialchars($row['exit_time']) : 'Still Parked'; ?></td>
                                                        <td><?php echo $row['fee'] ? '฿'.htmlspecialchars($row['fee']) : 'N/A'; ?></td>
                                                        <td><?php echo htmlspecialchars($row['payment_status']); ?></td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                echo '<tr><td colspan="7" class="text-center">No parking records found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>