<?php
require_once "../config.php";

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$userId = $_GET['id'];

// Get user details
$userSql = "SELECT u.*, ut.type_name, au.username, au.role, au.created_at as account_created 
            FROM user u
            LEFT JOIN user_types ut ON u.type_id = ut.type_id
            LEFT JOIN auth_users au ON u.user_id = au.user_id
            WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $userSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$userResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($userResult) == 0) {
    header("Location: users.php");
    exit();
}

$userData = mysqli_fetch_assoc($userResult);

// Get user's subscription status
$subscriptionSql = "SELECT us.*, sp.plan_name, sp.price
                  FROM user_subscriptions us
                  JOIN subscription_plans sp ON us.plan_id = sp.plan_id
                  WHERE us.user_id = ? AND us.status = 'active'
                  ORDER BY us.end_date DESC 
                  LIMIT 1";
$stmt = mysqli_prepare($conn, $subscriptionSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$subscriptionResult = mysqli_stmt_get_result($stmt);
$subscriptionData = mysqli_fetch_assoc($subscriptionResult);

// Get parking history
$parkingSql = "SELECT pr.*, ps.spot_number, pz.zone_name 
              FROM parking_records pr
              JOIN parking_spots ps ON pr.spot_id = ps.spot_id
              JOIN parking_zones pz ON ps.zone_id = pz.zone_id
              WHERE pr.user_id = ?
              ORDER BY pr.entry_time DESC";
$stmt = mysqli_prepare($conn, $parkingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$parkingResult = mysqli_stmt_get_result($stmt);

// Calculate Statistics
// Total parking sessions
$totalParkingSql = "SELECT COUNT(*) as total_sessions FROM parking_records WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $totalParkingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$totalResult = mysqli_stmt_get_result($stmt);
$totalParking = mysqli_fetch_assoc($totalResult)['total_sessions'];

// Total spending
$totalSpendingSql = "SELECT SUM(fee) as total_spent FROM parking_records WHERE user_id = ? AND payment_status = 'paid'";
$stmt = mysqli_prepare($conn, $totalSpendingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$spendingResult = mysqli_stmt_get_result($stmt);
$totalSpending = mysqli_fetch_assoc($spendingResult)['total_spent'] ?: 0;

// Monthly usage (last 6 months)
$monthlySql = "SELECT 
                DATE_FORMAT(entry_time, '%Y-%m') as month,
                COUNT(*) as count
              FROM parking_records
              WHERE user_id = ? AND entry_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(entry_time, '%Y-%m')
              ORDER BY month ASC";
$stmt = mysqli_prepare($conn, $monthlySql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$monthlyResult = mysqli_stmt_get_result($stmt);

$months = [];
$counts = [];
while ($row = mysqli_fetch_assoc($monthlyResult)) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $counts[] = $row['count'];
}

// Average parking duration
$durationSql = "SELECT 
                AVG(TIMESTAMPDIFF(MINUTE, entry_time, IFNULL(exit_time, NOW()))) as avg_duration
                FROM parking_records
                WHERE user_id = ? AND entry_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
$stmt = mysqli_prepare($conn, $durationSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$durationResult = mysqli_stmt_get_result($stmt);
$avgDuration = mysqli_fetch_assoc($durationResult)['avg_duration'] ?: 0;
$avgDurationHours = round($avgDuration / 60, 1);

// Most used zone
$zoneSql = "SELECT pz.zone_name, COUNT(*) as count
            FROM parking_records pr
            JOIN parking_spots ps ON pr.spot_id = ps.spot_id
            JOIN parking_zones pz ON ps.zone_id = pz.zone_id
            WHERE pr.user_id = ?
            GROUP BY pz.zone_id
            ORDER BY count DESC
            LIMIT 1";
$stmt = mysqli_prepare($conn, $zoneSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$zoneResult = mysqli_stmt_get_result($stmt);
$favoriteZone = mysqli_fetch_assoc($zoneResult);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Parking System Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .user-info-row {
            padding: 10px 0;
            border-bottom: 1px solid #e3e6f0;
        }
        .user-info-row:last-child {
            border-bottom: none;
        }
        .stat-card {
            border-left: 4px solid #4e73df;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fc;
        }
        .stat-card.blue {
            border-left-color: #4e73df;
        }
        .stat-card.green {
            border-left-color: #1cc88a;
        }
        .stat-card.orange {
            border-left-color: #f6c23e;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="text-center py-4">
                    <h4>Admin Dashboard</h4>
                </div>
                <hr class="sidebar-divider">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt icon"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="parking.php">
                            <i class="fas fa-parking icon"></i>Parking Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="active">
                            <i class="fas fa-users icon"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php">
                            <i class="fas fa-chart-bar icon"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="subscriptions.php">
                            <i class="fas fa-calendar-check icon"></i>Subscriptions
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
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">User Details</h6>
                        <a href="users.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h4><?php echo $userData['first_name'] . ' ' . $userData['last_name']; ?> (<?php echo $userData['username']; ?>)</h4>
                                <p>Role: <?php echo ucfirst($userData['role']); ?></p>
                                <p>Account Created: <?php echo date('d M Y', strtotime($userData['account_created'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Subscription</h5>
                                <?php if ($subscriptionData) { ?>
                                    <p>Plan: <?php echo $subscriptionData['plan_name']; ?> ($<?php echo $subscriptionData['price']; ?>)</p>
                                    <p>Expires: <?php echo date('d M Y', strtotime($subscriptionData['end_date'])); ?></p>
                                <?php } else { ?>
                                    <p>No active subscription</p>
                                <?php } ?>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="stat-card blue">
                                    <h5>Total Parking Sessions</h5>
                                    <h3><?php echo $totalParking; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card green">
                                    <h5>Total Spending</h5>
                                    <h3>$<?php echo number_format($totalSpending, 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card orange">
                                    <h5>Average Parking Duration</h5>
                                    <h3><?php echo $avgDurationHours; ?> hours</h3>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mt-4">Parking History</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Zone</th>
                                        <th>Spot Number</th>
                                        <th>Entry Time</th>
                                        <th>Exit Time</th>
                                        <th>Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    mysqli_data_seek($parkingResult, 0);
                                    while ($row = mysqli_fetch_assoc($parkingResult)) { ?>
                                        <tr>
                                            <td><?php echo $row['zone_name']; ?></td>
                                            <td><?php echo $row['spot_number']; ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($row['entry_time'])); ?></td>
                                            <td><?php echo $row['exit_time'] ? date('d M Y H:i', strtotime($row['exit_time'])) : 'Ongoing'; ?></td>
                                            <td>$<?php echo number_format($row['fee'], 2); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="mt-4">Monthly Parking Usage (Last 6 Months)</h5>
                        <canvas id="usageChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var ctx = document.getElementById('usageChart').getContext('2d');
        var usageChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Parking Sessions',
                    data: <?php echo json_encode($counts); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>