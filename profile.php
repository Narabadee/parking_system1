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

// Get additional user information
$sql = "SELECT u.*, ut.type_name FROM user u 
        JOIN user_types ut ON u.type_id = ut.type_id 
        WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userInfo = mysqli_fetch_assoc($result);

// Get subscription information
$subSql = "SELECT us.*, sp.plan_name, sp.price, sp.duration_months 
           FROM user_subscriptions us 
           JOIN subscription_plans sp ON us.plan_id = sp.plan_id 
           WHERE us.user_id = ? AND us.status = 'active' 
           ORDER BY us.end_date DESC 
           LIMIT 1";
$stmt = mysqli_prepare($conn, $subSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$subscription = mysqli_fetch_assoc($result);

// Get parking frequency data (last 6 months)
$monthlyData = [];
$freqSql = "SELECT DATE_FORMAT(entry_time, '%Y-%m') as month, COUNT(*) as count 
            FROM parking_records 
            WHERE user_id = ? 
            AND entry_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
            GROUP BY DATE_FORMAT(entry_time, '%Y-%m') 
            ORDER BY month ASC";
$stmt = mysqli_prepare($conn, $freqSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Initialize array for all months (including ones with no data)
$lastSixMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i month"));
    $monthName = date('M Y', strtotime("-$i month"));
    $lastSixMonths[$monthKey] = [
        'month' => $monthName,
        'count' => 0
    ];
}

// Fill in actual data
while ($row = mysqli_fetch_assoc($result)) {
    $lastSixMonths[$row['month']]['count'] = $row['count'];
}
$monthlyData = array_values($lastSixMonths);

// Get total spending
$spendingSql = "SELECT SUM(fee) as total_spending 
                FROM parking_records 
                WHERE user_id = ? AND payment_status = 'paid'";
$stmt = mysqli_prepare($conn, $spendingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$spendingData = mysqli_fetch_assoc($result);
$totalSpending = $spendingData['total_spending'] ? $spendingData['total_spending'] : 0;

// Get last parking time
$lastParkingSql = "SELECT MAX(entry_time) as last_time 
                   FROM parking_records 
                   WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $lastParkingSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$lastParkingData = mysqli_fetch_assoc($result);
$lastParkingTime = $lastParkingData['last_time'];

// Convert PHP array to JavaScript object for the chart
$chartData = json_encode($monthlyData);

// Handle subscription plan updates if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['subscribe'])) {
    $planId = $_POST['plan_id'];
    
    // Get plan information
    $planSql = "SELECT * FROM subscription_plans WHERE plan_id = ?";
    $stmt = mysqli_prepare($conn, $planSql);
    mysqli_stmt_bind_param($stmt, "i", $planId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $plan = mysqli_fetch_assoc($result);
    
    if ($plan) {
        // Calculate start and end dates
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));
        
        // Check if user already has an active subscription
        if ($subscription) {
            // Update existing subscription to expired
            $updateSql = "UPDATE user_subscriptions SET status = 'expired' WHERE subscription_id = ?";
            $stmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($stmt, "i", $subscription['subscription_id']);
            mysqli_stmt_execute($stmt);
        }
        
        // Create new subscription
        $insertSql = "INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status) 
                      VALUES (?, ?, ?, ?, 'active')";
        $stmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param($stmt, "iiss", $userId, $planId, $startDate, $endDate);
        
        if (mysqli_stmt_execute($stmt)) {
            // Get new subscription ID
            $subscriptionId = mysqli_insert_id($conn);
            
            // Record payment
            $paymentSql = "INSERT INTO payments (subscription_id, amount, payment_date, payment_method, reference_number) 
                          VALUES (?, ?, NOW(), 'Credit Card', CONCAT('SUB-', ?))";
            $stmt = mysqli_prepare($conn, $paymentSql);
            $amount = $plan['price'];
            $refId = rand(10000, 99999);
            mysqli_stmt_bind_param($stmt, "idi", $subscriptionId, $amount, $refId);
            mysqli_stmt_execute($stmt);
            
            // Update user type to subscriber
            $updateUserSql = "UPDATE user SET type_id = 2 WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $updateUserSql);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            
            // Show success message
            $successMessage = "Successfully subscribed to the " . $plan['plan_name'] . " plan!";
            
            // Refresh the page to show updated information
            header("Location: profile.php?success=1");
            exit();
        } else {
            $errorMessage = "Error subscribing to plan. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Parking Management System</title>
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
        .profile-header {
            background-color: #f8f9fc;
            border-left: 4px solid #4e73df;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .subscription-card {
            border-left: 4px solid #1cc88a;
        }
        .info-card {
            height: 100%;
        }
        .card-body canvas {
        min-height: 250px;
        height: 100%;
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
                        <a href="service_fee.php">
                            <i class="fas fa-money-bill icon"></i>Service Fee
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="active">
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
                <!-- Profile Header -->
                <div class="profile-header">
                    <h4>User Profile</h4>
                    <p>Manage your profile and subscription information</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" role="alert">
                    Successfully updated your subscription!
                </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $errorMessage; ?>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Personal Information Card -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow info-card">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3 text-center">
                                    <i class="fas fa-user-circle fa-5x text-gray-300 mb-3"></i>
                                </div>
                                <div class="mb-2">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($userInfo['tel']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>User Type:</strong> <?php echo htmlspecialchars($userInfo['type_name']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($userInfo['created_at'])); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Last Parking:</strong> 
                                    <?php echo $lastParkingTime ? date('F j, Y, g:i a', strtotime($lastParkingTime)) : 'Never'; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Total Spent:</strong> ฿<?php echo number_format($totalSpending, 2); ?>
                                </div>
                                <div class="text-center mt-4">
                                    <a href="#" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-edit"></i> Edit Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Card -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow subscription-card info-card">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-success">Subscription Information</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($subscription): ?>
                                <div class="text-center mb-4">
                                    <i class="fas fa-calendar-check fa-5x text-success opacity-25 mb-3"></i>
                                    <h5>Active Subscription</h5>
                                </div>
                                <div class="mb-2">
                                    <strong>Plan:</strong> <?php echo htmlspecialchars($subscription['plan_name']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($subscription['start_date'])); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>End Date:</strong> <?php echo date('F j, Y', strtotime($subscription['end_date'])); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Price:</strong> ฿<?php echo number_format($subscription['price'], 2); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Subscription Progress:</strong>
                                    <div class="progress mt-2">
                                        <?php
                                        $startDate = strtotime($subscription['start_date']);
                                        $endDate = strtotime($subscription['end_date']);
                                        $currentDate = time();
                                        $totalDuration = $endDate - $startDate;
                                        $elapsedDuration = $currentDate - $startDate;
                                        $percentComplete = min(100, max(0, ($elapsedDuration / $totalDuration) * 100));
                                        ?>
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentComplete; ?>%;" 
                                            aria-valuenow="<?php echo $percentComplete; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo round($percentComplete); ?>%
                                        </div>
                                    </div>
                                    <small class="text-muted">You have used <?php echo round($percentComplete); ?>% of your subscription.</small>
                                </div>
                                <div class="text-center mt-4">
                                    <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#subscriptionModal">
                                        <i class="fas fa-sync-alt"></i> Renew Subscription
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="text-center mb-4">
                                    <i class="fas fa-calendar-times fa-5x text-gray-300 mb-3"></i>
                                    <h5>No Active Subscription</h5>
                                </div>
                                <p class="text-center">
                                    Subscribe to a plan to get discounted parking rates and other benefits.
                                </p>
                                <div class="text-center mt-4">
                                    <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#subscriptionModal">
                                        <i class="fas fa-tag"></i> Get a Subscription
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Parking Activity Card -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow info-card">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-info">Parking Activity</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h5>Frequency of Use</h5>
                                    <p class="small text-muted">Last 6 months</p>
                                </div>
                                <canvas id="parkingFrequencyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Parking History -->
                    <div class="col-md-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Parking History</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Spot</th>
                                                <th>Zone</th>
                                                <th>Vehicle Number</th>
                                                <th>Duration</th>
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
                                                                LIMIT 10";
                                            $stmt = mysqli_prepare($conn, $recentRecordsSql);
                                            mysqli_stmt_bind_param($stmt, "i", $userId);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);

                                            if (mysqli_num_rows($result) > 0) {
                                                while($row = mysqli_fetch_assoc($result)) {
                                                    // Calculate duration
                                                    $entryTime = new DateTime($row['entry_time']);
                                                    if ($row['exit_time']) {
                                                        $exitTime = new DateTime($row['exit_time']);
                                                        $interval = $entryTime->diff($exitTime);
                                                        $duration = $interval->format('%Hh %Im');
                                                    } else {
                                                        $duration = "Still Parked";
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($row['entry_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($row['spot_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['zone_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['vehicle_number']); ?></td>
                                                        <td><?php echo $duration; ?></td>
                                                        <td><?php echo $row['fee'] ? '฿'.number_format($row['fee'], 2) : 'N/A'; ?></td>
                                                        <td>
                                                            <?php if ($row['payment_status'] == 'paid'): ?>
                                                                <span class="badge bg-success">Paid</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">Pending</span>
                                                            <?php endif; ?>
                                                        </td>
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

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update_profile.php" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" value="<?php echo htmlspecialchars($userInfo['first_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" value="<?php echo htmlspecialchars($userInfo['last_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="tel" value="<?php echo htmlspecialchars($userInfo['tel']); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Subscription Modal -->
    <div class="modal fade" id="subscriptionModal" tabindex="-1" aria-labelledby="subscriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subscriptionModalLabel">Choose a Subscription Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <?php
                        // Fetch available subscription plans
                        $plansSql = "SELECT * FROM subscription_plans ORDER BY duration_months";
                        $plansResult = mysqli_query($conn, $plansSql);
                        
                        while ($plan = mysqli_fetch_assoc($plansResult)) {
                            $monthlyPrice = $plan['price'] / $plan['duration_months'];
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header text-center bg-primary text-white">
                                    <h5 class="my-0"><?php echo htmlspecialchars($plan['plan_name']); ?></h5>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h1 class="card-title pricing-card-title text-center">฿<?php echo number_format($plan['price']); ?></h1>
                                    <p class="text-muted text-center">฿<?php echo number_format($monthlyPrice, 2); ?> / month</p>
                                    <ul class="list-unstyled mt-3 mb-4">
                                        <li><i class="fas fa-check text-success me-2"></i> <?php echo $plan['duration_months']; ?> months of access</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Discounted parking rates</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Priority parking spots</li>
                                        <li><i class="fas fa-check text-success me-2"></i> 24/7 customer support</li>
                                    </ul>
                                    <form method="post" class="mt-auto">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                        <button type="submit" name="subscribe" class="w-100 btn btn-lg btn-outline-primary">Subscribe</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Parking Frequency Chart
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('parkingFrequencyChart');
        
        // Set explicit height and width on the canvas container
        if (ctx) {
            ctx.parentNode.style.height = '200px'; // Reduce height
            ctx.parentNode.style.width = '100%'; // Ensure it fits the container
            
            var chartData = <?php echo $chartData; ?>;
            
            var labels = chartData.map(function(item) {
                return item.month;
            });
            
            var data = chartData.map(function(item) {
                return item.count;
            });
            
            // Destroy any existing chart instance
            if (window.parkingChart instanceof Chart) {
                window.parkingChart.destroy();
            }
            
            // Create new chart instance with smaller dimensions
            window.parkingChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Parking Sessions',
                        data: data,
                        backgroundColor: 'rgba(78, 115, 223, 0.2)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1,
                        tension: 0.4 // Smooth curve
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true, // Maintain aspect ratio for smaller size
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    animation: {
                        duration: 800 // Slightly faster animation
                    }
                }
            });
        }
    });
</script>
</body>
</html>