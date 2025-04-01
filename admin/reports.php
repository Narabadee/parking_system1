<?php
require_once "../config.php";
 
// Check if user is logged in and is admin
requireAdmin();

// Get admin information
$adminId = $_SESSION["user_id"];
$adminName = $_SESSION["first_name"] . " " . $_SESSION["last_name"];

// Process date filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Prepare date range query part
$dateRangeQuery = "WHERE (DATE(entry_time) BETWEEN '$startDate' AND '$endDate' OR DATE(exit_time) BETWEEN '$startDate' AND '$endDate')";

// Get revenue data based on report type
if ($reportType == 'daily') {
    $revenueQuery = "SELECT DATE(exit_time) as date, SUM(fee) as revenue, COUNT(*) as transactions 
                    FROM parking_records 
                    WHERE exit_time IS NOT NULL AND payment_status = 'Paid' 
                    AND DATE(exit_time) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(exit_time) 
                    ORDER BY date";
} else if ($reportType == 'weekly') {
    $revenueQuery = "SELECT YEARWEEK(exit_time, 1) as week_num, 
                    MIN(DATE(exit_time)) as week_start,
                    SUM(fee) as revenue, COUNT(*) as transactions 
                    FROM parking_records 
                    WHERE exit_time IS NOT NULL AND payment_status = 'Paid' 
                    AND DATE(exit_time) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY YEARWEEK(exit_time, 1) 
                    ORDER BY week_num";
} else if ($reportType == 'monthly') {
    $revenueQuery = "SELECT DATE_FORMAT(exit_time, '%Y-%m') as month, 
                    DATE_FORMAT(exit_time, '%b %Y') as month_name,
                    SUM(fee) as revenue, COUNT(*) as transactions 
                    FROM parking_records 
                    WHERE exit_time IS NOT NULL AND payment_status = 'Paid' 
                    AND DATE(exit_time) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE_FORMAT(exit_time, '%Y-%m'), DATE_FORMAT(exit_time, '%b %Y') 
                    ORDER BY month";
}

$revenueResult = mysqli_query($conn, $revenueQuery);

// Get zone usage statistics
$zoneUsageQuery = "SELECT pz.zone_name, COUNT(*) as usage_count, ROUND(AVG(TIME_TO_SEC(TIMEDIFF(COALESCE(pr.exit_time, NOW()), pr.entry_time))/3600), 1) as avg_duration
                  FROM parking_records pr
                  JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                  JOIN parking_zones pz ON ps.zone_id = pz.zone_id
                  $dateRangeQuery
                  GROUP BY pz.zone_id
                  ORDER BY usage_count DESC";
$zoneUsageResult = mysqli_query($conn, $zoneUsageQuery);

// Get peak hours statistics
$peakHoursQuery = "SELECT HOUR(entry_time) as hour, COUNT(*) as count 
                  FROM parking_records 
                  $dateRangeQuery
                  GROUP BY HOUR(entry_time) 
                  ORDER BY HOUR(entry_time)";
$peakHoursResult = mysqli_query($conn, $peakHoursQuery);

// Get user activity statistics
$userActivityQuery = "SELECT u.first_name, u.last_name, COUNT(*) as visit_count, SUM(pr.fee) as total_spent
                     FROM parking_records pr
                     JOIN user u ON pr.user_id = u.user_id
                     WHERE payment_status = 'Paid' AND (DATE(entry_time) BETWEEN '$startDate' AND '$endDate' OR DATE(exit_time) BETWEEN '$startDate' AND '$endDate')
                     GROUP BY pr.user_id
                     ORDER BY visit_count DESC
                     LIMIT 10";
$userActivityResult = mysqli_query($conn, $userActivityQuery);

// Get overall statistics for the date range
$overallStatsQuery = "SELECT 
                      COUNT(*) as total_transactions,
                      SUM(CASE WHEN payment_status = 'Paid' THEN fee ELSE 0 END) as total_revenue,
                      ROUND(AVG(CASE WHEN exit_time IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(exit_time, entry_time))/3600 ELSE NULL END), 1) as avg_duration,
                      COUNT(DISTINCT user_id) as unique_users
                      FROM parking_records
                      $dateRangeQuery";
$overallStatsResult = mysqli_query($conn, $overallStatsQuery);
$overallStats = mysqli_fetch_assoc($overallStatsResult);

// Get payment status statistics
$paymentStatusQuery = "SELECT payment_status, COUNT(*) as count
                      FROM parking_records
                      $dateRangeQuery
                      GROUP BY payment_status";
$paymentStatusResult = mysqli_query($conn, $paymentStatusQuery);

// Format data for charts
$revenueData = [];
$revenueLabels = [];
$revenueValues = [];
$transactionCounts = [];

while ($row = mysqli_fetch_assoc($revenueResult)) {
    $revenueData[] = $row;
    
    if ($reportType == 'daily') {
        $dateObj = date_create($row['date']);
        $label = date_format($dateObj, 'd M');
        $revenueLabels[] = $label;
    } else if ($reportType == 'weekly') {
        $dateObj = date_create($row['week_start']);
        $label = 'Week of ' . date_format($dateObj, 'd M');
        $revenueLabels[] = $label;
    } else {
        $revenueLabels[] = $row['month_name'];
    }
    
    $revenueValues[] = $row['revenue'];
    $transactionCounts[] = $row['transactions'];
}

// Format zone usage data for chart
$zoneLabels = [];
$zoneValues = [];
$zoneDurations = [];

mysqli_data_seek($zoneUsageResult, 0);
while ($row = mysqli_fetch_assoc($zoneUsageResult)) {
    $zoneLabels[] = $row['zone_name'];
    $zoneValues[] = $row['usage_count'];
    $zoneDurations[] = $row['avg_duration'];
}

// Format peak hours data for chart
$hourLabels = [];
$hourValues = [];

mysqli_data_seek($peakHoursResult, 0);
while ($row = mysqli_fetch_assoc($peakHoursResult)) {
    // Convert 24-hour format to 12-hour format with AM/PM
    $hourLabels[] = date('g A', strtotime($row['hour'] . ':00'));
    $hourValues[] = $row['count'];
}

// Format payment status data for chart
$paymentStatusLabels = [];
$paymentStatusValues = [];

mysqli_data_seek($paymentStatusResult, 0);
while ($row = mysqli_fetch_assoc($paymentStatusResult)) {
    $paymentStatusLabels[] = ucfirst(strtolower($row['payment_status']));
    $paymentStatusValues[] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Parking Management System</title>
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
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            color: white;
            height: 100%;
        }
        .stats-card.primary {
            background-color: #4e73df;
        }
        .stats-card.success {
            background-color: #1cc88a;
        }
        .stats-card.info {
            background-color: #36b9cc;
        }
        .stats-card.warning {
            background-color: #f6c23e;
        }
        .stats-card-value {
            font-size: 1.75rem;
            font-weight: bold;
        }
        .stats-card-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1rem;
        }
        .date-filter {
            background-color: #f8f9fc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
                        <a href="reports.php" class="active">
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
                <h2 class="mb-4"><i class="fas fa-chart-bar me-2"></i> Parking Reports</h2>
                
                <!-- Date Filter Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type">
                                    <option value="daily" <?php echo ($reportType == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($reportType == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($reportType == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Summary Stats -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card primary h-100">
                            <div class="stats-card-value">฿<?php echo number_format($overallStats['total_revenue'], 2); ?></div>
                            <div class="stats-card-label">Total Revenue</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card success h-100">
                            <div class="stats-card-value"><?php echo $overallStats['total_transactions']; ?></div>
                            <div class="stats-card-label">Total Transactions</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card info h-100">
                            <div class="stats-card-value"><?php echo $overallStats['avg_duration']; ?> hrs</div>
                            <div class="stats-card-label">Avg. Parking Duration</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card warning h-100">
                            <div class="stats-card-value"><?php echo $overallStats['unique_users']; ?></div>
                            <div class="stats-card-label">Unique Users</div>
                        </div>
                    </div>
                </div>
                
                <!-- Revenue Charts -->
                <div class="row">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Revenue Trend</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="revenueDropdown" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="revenueDropdown">
                                        <div class="dropdown-header">Export Options:</div>
                                        <a class="dropdown-item" href="#" onclick="exportTableToCSV('revenue_report.csv', 'revenueTable')">
                                            <i class="fas fa-file-csv fa-sm fa-fw me-2 text-gray-400"></i>Export CSV
                                        </a>
                                        <a class="dropdown-item" href="#" onclick="printTable('revenueTable')">
                                            <i class="fas fa-print fa-sm fa-fw me-2 text-gray-400"></i>Print
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-area mb-4" style="height: 466px;"> <!-- Increased height -->
                                    <canvas id="revenueChart"></canvas>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-bordered" id="revenueTable">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Revenue (฿)</th>
                                                <th>Transactions</th>
                                                <th>Avg. Per Transaction (฿)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if (count($revenueData) > 0) {
                                                foreach ($revenueData as $index => $data) {
                                                    $avgPerTransaction = $data['transactions'] > 0 ? 
                                                        round($data['revenue'] / $data['transactions'], 2) : 0;
                                                    echo '<tr>';
                                                    echo '<td>' . $revenueLabels[$index] . '</td>';
                                                    echo '<td>' . number_format($data['revenue'], 2) . '</td>';
                                                    echo '<td>' . $data['transactions'] . '</td>';
                                                    echo '<td>' . number_format($avgPerTransaction, 2) . '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="4" class="text-center">No data available for the selected period</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Payment Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie mb-4">
                                    <canvas id="paymentStatusChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <?php
                                    $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'];
                                    mysqli_data_seek($paymentStatusResult, 0);
                                    $i = 0;
                                    while ($status = mysqli_fetch_assoc($paymentStatusResult)) {
                                        echo '<span class="me-2">';
                                        echo '<i class="fas fa-circle" style="color: ' . $colors[$i % count($colors)] . '"></i> ';
                                        echo ucfirst(strtolower($status['payment_status'])) . ' (' . $status['count'] . ')';
                                        echo '</span> ';
                                        $i++;
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Zone Usage Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Zone</th>
                                                <th>Usage Count</th>
                                                <th>Avg. Duration (hrs)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            mysqli_data_seek($zoneUsageResult, 0);
                                            while ($zone = mysqli_fetch_assoc($zoneUsageResult)) {
                                                echo '<tr>';
                                                echo '<td>' . htmlspecialchars($zone['zone_name']) . '</td>';
                                                echo '<td>' . $zone['usage_count'] . '</td>';
                                                echo '<td>' . $zone['avg_duration'] . '</td>';
                                                echo '</tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Peak Hours Chart -->
                <div class="row">
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Peak Hours Analysis</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-bar">
                                    <canvas id="peakHoursChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Zone Usage Comparison</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-bar">
                                    <canvas id="zoneComparisonChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Users Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Top Users by Activity</h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                        <div class="dropdown-header">Export Options:</div>
                                        <a class="dropdown-item" href="#" onclick="exportTableToCSV('user_activity.csv', 'userActivityTable')">
                                            <i class="fas fa-file-csv fa-sm fa-fw me-2 text-gray-400"></i>Export CSV
                                        </a>
                                        <a class="dropdown-item" href="#" onclick="printTable('userActivityTable')">
                                            <i class="fas fa-print fa-sm fa-fw me-2 text-gray-400"></i>Print
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="userActivityTable">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Visit Count</th>
                                                <th>Total Spent (฿)</th>
                                                <th>Avg. Spent per Visit (฿)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (mysqli_num_rows($userActivityResult) > 0) {
                                                while ($user = mysqli_fetch_assoc($userActivityResult)) {
                                                    $avgSpent = $user['visit_count'] > 0 ? 
                                                        round($user['total_spent'] / $user['visit_count'], 2) : 0;
                                                    
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td>';
                                                    echo '<td>' . $user['visit_count'] . '</td>';
                                                    echo '<td>' . number_format($user['total_spent'], 2) . '</td>';
                                                    echo '<td>' . number_format($avgSpent, 2) . '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="4" class="text-center">No user activity data available for the selected period</td></tr>';
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
        // Revenue Chart
        var ctx = document.getElementById("revenueChart").getContext('2d');
        var revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenueLabels); ?>,
                datasets: [
                    {
                        label: "Revenue (฿)",
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
                        data: <?php echo json_encode($revenueValues); ?>,
                        yAxisID: 'y'
                    },
                    {
                        label: "Transactions",
                        lineTension: 0.3,
                        backgroundColor: "rgba(28, 200, 138, 0.05)",
                        borderColor: "rgba(28, 200, 138, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(28, 200, 138, 1)",
                        pointBorderColor: "rgba(28, 200, 138, 1)",
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
                        pointHoverBorderColor: "rgba(28, 200, 138, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: <?php echo json_encode($transactionCounts); ?>,
                        yAxisID: 'y1'
                    }
                ],
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
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        },
                        ticks: {
                            callback: function(value) {
                                return '฿' + value;
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
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
                                if (context.dataset.label === "Revenue (฿)") {
                                    if (context.parsed.y !== null) {
                                        label += '฿' + context.parsed.y;
                                    }
                                } else {
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y;
                                    }
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Payment Status Chart
        var paymentCtx = document.getElementById("paymentStatusChart").getContext('2d');
        var paymentStatusChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($paymentStatusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($paymentStatusValues); ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)"
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right'
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10
                    }
                }
            }
        });

        // Peak Hours Chart
        var peakHoursCtx = document.getElementById("peakHoursChart").getContext('2d');
        var peakHoursChart = new Chart(peakHoursCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($hourLabels); ?>,
                datasets: [{
                    label: "Entries",
                    backgroundColor: "#4e73df",
                    hoverBackgroundColor: "#2e59d9",
                    borderColor: "#4e73df",
                    data: <?php echo json_encode($hourValues); ?>
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 12
                        }
                    },
                    y: {
                        ticks: {
                            beginAtZero: true
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }
                },
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
                        caretPadding: 10
                    }
                }
            }
        });

        // Zone Comparison Chart
        var zoneComparisonCtx = document.getElementById("zoneComparisonChart").getContext('2d');
        var zoneComparisonChart = new Chart(zoneComparisonCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($zoneLabels); ?>,
                datasets: [{
                    label: "Usage Count",
                    backgroundColor: "#1cc88a",
                    hoverBackgroundColor: "#17a673",
                    borderColor: "#1cc88a",
                    data: <?php echo json_encode($zoneValues); ?>
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        ticks: {
                            beginAtZero: true
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }
                },
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
                        caretPadding: 10
                    }
                }
            }
        });
    </script>
</body>
</html>