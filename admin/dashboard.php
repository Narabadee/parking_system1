<?php
require_once "../config.php";

// Check if user is logged in and is admin
requireAdmin();

// Get admin information
$adminId = $_SESSION["user_id"];
$adminName = $_SESSION["first_name"] . " " . $_SESSION["last_name"];

// Get parking statistics
$totalSpotsSql = "SELECT COUNT(*) as total FROM parking_spots";
$availableSpotsSql = "SELECT COUNT(*) as available FROM parking_spots ps 
                     WHERE NOT EXISTS (SELECT 1 FROM parking_records pr 
                                      WHERE pr.spot_id = ps.spot_id AND pr.exit_time IS NULL)";
$occupiedSpotsSql = "SELECT COUNT(*) as occupied FROM parking_spots ps 
                     WHERE EXISTS (SELECT 1 FROM parking_records pr 
                                  WHERE pr.spot_id = ps.spot_id AND pr.exit_time IS NULL)";

$totalSpotsResult = mysqli_query($conn, $totalSpotsSql);
$availableSpotsResult = mysqli_query($conn, $availableSpotsSql);
$occupiedSpotsResult = mysqli_query($conn, $occupiedSpotsSql);

$totalSpots = mysqli_fetch_assoc($totalSpotsResult)['total'];
$availableSpots = mysqli_fetch_assoc($availableSpotsResult)['available'];
$occupiedSpots = mysqli_fetch_assoc($occupiedSpotsResult)['occupied'];

// Calculate occupancy percentage
$occupancyRate = ($totalSpots > 0) ? round(($occupiedSpots / $totalSpots) * 100) : 0;

// Get total users
$totalUsersSql = "SELECT COUNT(*) as total FROM user WHERE user_id != $adminId";
$totalUsersResult = mysqli_query($conn, $totalUsersSql);
$totalUsers = mysqli_fetch_assoc($totalUsersResult)['total'];

// Get today's income
$todayIncomeSql = "SELECT SUM(fee) as income FROM parking_records 
                  WHERE DATE(exit_time) = CURDATE() AND payment_status = 'Paid'";
$todayIncomeResult = mysqli_query($conn, $todayIncomeSql);
$todayIncome = mysqli_fetch_assoc($todayIncomeResult)['income'] ?: 0;

// Get monthly income
$monthlyIncomeSql = "SELECT SUM(fee) as income FROM parking_records 
                    WHERE MONTH(exit_time) = MONTH(CURDATE()) 
                    AND YEAR(exit_time) = YEAR(CURDATE()) 
                    AND payment_status = 'Paid'";
$monthlyIncomeResult = mysqli_query($conn, $monthlyIncomeSql);
$monthlyIncome = mysqli_fetch_assoc($monthlyIncomeResult)['income'] ?: 0;

// Get recent parking activities
$recentRecordsSql = "SELECT pr.*, u.first_name, u.last_name, ps.spot_number, pz.zone_name 
                     FROM parking_records pr
                     JOIN user u ON pr.user_id = u.user_id
                     JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                     JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                     WHERE pr.exit_time IS NOT NULL 
                     OR pr.confirmation_status IN ('expired', 'cancelled')
                     ORDER BY pr.entry_time DESC LIMIT 5";
$recentActivitiesResult = mysqli_query($conn, $recentRecordsSql);

// Get most used zones
$mostUsedZonesSql = "SELECT pz.zone_name, COUNT(*) as usage_count 
                    FROM parking_records pr
                    JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                    JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                    GROUP BY pz.zone_id
                    ORDER BY usage_count DESC
                    LIMIT 4";
$mostUsedZonesResult = mysqli_query($conn, $mostUsedZonesSql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin DashboardEIEI - Parking Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
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
        .stat-card {
            border-left: 4px solid;
            margin-bottom: 20px;
        }
        .stat-card.primary {
            border-left-color: #4e73df;
        }
        .stat-card.success {
            border-left-color: #1cc88a;
        }
        .stat-card.warning {
            border-left-color: #f6c23e;
        }
        .stat-card.danger {
            border-left-color: #e74a3b;
        }
        .stat-card-body {
            padding: 20px;
        }
        .stat-card-icon {
            font-size: 2rem;
            opacity: 0.3;
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
                        <a href="dashboard.php" class="active">
                            <i class="fas fa-tachometer-alt icon"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="parking.php">
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
                    <li class="nav-item mt-3">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt icon"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <div class="welcome-card">
                    <h4>Welcome, Admin <?php echo htmlspecialchars($adminName); ?>!</h4>
                    <p>This is your admin dashboard where you can manage the parking system.</p>
                </div>

                <!-- Stats Row -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow stat-card primary h-100">
                            <div class="stat-card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Occupancy Rate
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $occupancyRate; ?>%</div>
                                        <div class="mt-2 small">
                                            <span class="text-success"><?php echo $occupiedSpots; ?> occupied</span> / 
                                            <span class="text-danger"><?php echo $availableSpots; ?> available</span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-car-side stat-card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow stat-card success h-100">
                            <div class="stat-card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Today's Income
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">฿<?php echo number_format($todayIncome, 2); ?></div>
                                        <div class="mt-2 small">
                                            <?php 
                                            // Get today's transaction count
                                            $todayTransactionsSql = "SELECT COUNT(*) as count FROM parking_records 
                                                                   WHERE DATE(exit_time) = CURDATE() AND payment_status = 'Paid'";
                                            $todayTransactionsResult = mysqli_query($conn, $todayTransactionsSql);
                                            $todayTransactions = mysqli_fetch_assoc($todayTransactionsResult)['count'];
                                            echo $todayTransactions . ' transactions today';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-day stat-card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow stat-card warning h-100">
                            <div class="stat-card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Monthly Income
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">฿<?php echo number_format($monthlyIncome, 2); ?></div>
                                        <div class="mt-2 small">
                                            <?php 
                                            // Get monthly transaction count
                                            $monthlyTransactionsSql = "SELECT COUNT(*) as count FROM parking_records 
                                                                     WHERE MONTH(exit_time) = MONTH(CURDATE()) 
                                                                     AND YEAR(exit_time) = YEAR(CURDATE()) 
                                                                     AND payment_status = 'Paid'";
                                            $monthlyTransactionsResult = mysqli_query($conn, $monthlyTransactionsSql);
                                            $monthlyTransactions = mysqli_fetch_assoc($monthlyTransactionsResult)['count'];
                                            echo $monthlyTransactions . ' transactions this month';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt stat-card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow stat-card danger h-100">
                            <div class="stat-card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Total Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalUsers; ?></div>
                                        <div class="mt-2 small">
                                            <?php 
                                            // Get new users this month
                                            $newUsersSql = "SELECT COUNT(*) as count FROM user 
                                                          WHERE MONTH(created_at) = MONTH(CURDATE()) 
                                                          AND YEAR(created_at) = YEAR(CURDATE())";
                                            $newUsersResult = mysqli_query($conn, $newUsersSql);
                                            // If the query fails (probably because 'created_at' column doesn't exist yet), handle it
                                            if ($newUsersResult) {
                                                $newUsers = mysqli_fetch_assoc($newUsersResult)['count'];
                                                echo $newUsers . ' new users this month';
                                            } else {
                                                echo 'Managing ' . $totalUsers . ' users';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users stat-card-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mt-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Income Overview</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area" style="height: 344px;"> <!-- Increased height -->
                                    <canvas id="incomeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Zone Usage</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie">
                                    <canvas id="zoneChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <?php
                                    if (mysqli_num_rows($mostUsedZonesResult) > 0) {
                                        $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'];
                                        $i = 0;
                                        while ($zone = mysqli_fetch_assoc($mostUsedZonesResult)) {
                                            echo '<span class="mr-2">';
                                            echo '<i class="fas fa-circle" style="color: ' . $colors[$i % count($colors)] . '"></i> ';
                                            echo htmlspecialchars($zone['zone_name']) . ' (' . $zone['usage_count'] . ')';
                                            echo '</span> ';
                                            $i++;
                                        }
                                    } else {
                                        echo 'No zone data available';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Parking Activities</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Vehicle Number</th>
                                                <th>Zone</th>
                                                <th>Spot</th>
                                                <th>Entry Time</th>
                                                <th>Exit Time</th>
                                                <th>Duration</th>
                                                <th>Fee</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (mysqli_num_rows($recentActivitiesResult) > 0) {
                                                while ($record = mysqli_fetch_assoc($recentActivitiesResult)) {
                                                    $entryTime = strtotime($record['entry_time']);
                                                    $exitTime = $record['exit_time'] ? strtotime($record['exit_time']) : time();
                                                    $duration = ceil(($exitTime - $entryTime) / 3600);
                                                    
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($record['vehicle_number']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($record['zone_name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($record['spot_number']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($record['entry_time']) . '</td>';
                                                    echo '<td>' . ($record['exit_time'] ? htmlspecialchars($record['exit_time']) : 'Still Parked') . '</td>';
                                                    echo '<td>' . $duration . ' hr' . ($duration > 1 ? 's' : '') . '</td>';
                                                    echo '<td>' . ($record['fee'] ? '฿' . htmlspecialchars($record['fee']) : 'N/A') . '</td>';
                                                    echo '<td>';
                                                    switch($record['confirmation_status']) {
                                                        case 'expired':
                                                            echo '<span class="badge bg-secondary">Expired</span>';
                                                            break;
                                                        case 'cancelled':
                                                            echo '<span class="badge bg-danger">Cancelled</span>';
                                                            break;
                                                        case 'confirmed':
                                                            echo '<span class="badge bg-success">Completed</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-warning">Pending</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="9" class="text-center">No recent activities found</td></tr>';
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
    <script>
        // Income Chart
        var ctx = document.getElementById("incomeChart").getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Monthly Income (฿)",
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: [
                        <?php
                        // Get monthly income for current year
                        $currentYear = date('Y');
                        for ($month = 1; $month <= 12; $month++) {
                            $monthlyDataSql = "SELECT SUM(fee) as monthly_income FROM parking_records 
                                              WHERE MONTH(exit_time) = $month 
                                              AND YEAR(exit_time) = $currentYear 
                                              AND payment_status = 'Paid'";
                            $monthlyDataResult = mysqli_query($conn, $monthlyDataSql);
                            $monthlyData = mysqli_fetch_assoc($monthlyDataResult);
                            echo ($monthlyData['monthly_income'] ?: 0);
                            if ($month < 12) echo ", ";
                        }
                        ?>
                    ],
                }],
            },
            options: {
                maintainAspectRatio: false, // Allow the chart to fill the container
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    },
                    y: {
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '฿' + value;
                            }
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleMarginBottom: 10,
                        titleColor: '#6e707e',
                        titleFontSize: 14,
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: 'index',
                        caretPadding: 10,
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '฿' + context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Zone Usage Chart
        var zonectx = document.getElementById("zoneChart").getContext('2d');
        var zoneChart = new Chart(zonectx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php
                    // Reset the pointer for the result set
                    mysqli_data_seek($mostUsedZonesResult, 0);
                    while ($zone = mysqli_fetch_assoc($mostUsedZonesResult)) {
                        echo '"' . htmlspecialchars($zone['zone_name']) . '"';
                        if (!mysqli_error($conn)) echo ', ';
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php
                        // Reset the pointer for the result set
                        mysqli_data_seek($mostUsedZonesResult, 0);
                        while ($zone = mysqli_fetch_assoc($mostUsedZonesResult)) {
                            echo $zone['usage_count'];
                            if (!mysqli_error($conn)) echo ', ';
                        }
                        ?>
                    ],
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#f4b619'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    }
                },
                cutout: '70%',
            },
        });
    </script>
</body>
</html>