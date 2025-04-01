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
$successMsg = $errorMsg = '';

// Get user types for dropdown
$typesSql = "SELECT * FROM user_types";
$typesResult = mysqli_query($conn, $typesSql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $tel = $_POST['tel'];
    $typeId = $_POST['type_id'];
    $username = $_POST['username'];
    $email = $_POST['email'] ?? '';
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update user table
        $updateUserSql = "UPDATE user SET first_name = ?, last_name = ?, tel = ?, type_id = ?, email = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $updateUserSql);
        mysqli_stmt_bind_param($stmt, "sssisi", $firstName, $lastName, $tel, $typeId, $email, $userId);
        mysqli_stmt_execute($stmt);
        
        // Update auth_users table (username only, not password)
        $updateAuthSql = "UPDATE auth_users SET username = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $updateAuthSql);
        mysqli_stmt_bind_param($stmt, "si", $username, $userId);
        mysqli_stmt_execute($stmt);
        
        // Check if password change was requested
        if (!empty($_POST['new_password'])) {
            $newPassword = $_POST['new_password'];
            // Hash the password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $updatePasswordSql = "UPDATE auth_users SET password = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $updatePasswordSql);
            mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $userId);
            mysqli_stmt_execute($stmt);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        $successMsg = "User information updated successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $errorMsg = "Error updating user: " . $e->getMessage();
    }
}

// Get user details for the form
$userSql = "SELECT u.*, au.username, au.auth_id
            FROM user u
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Parking System Admin</title>
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
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
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
                        <h6 class="m-0 font-weight-bold text-primary">Edit User</h6>
                        <a href="users.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if($successMsg): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $successMsg; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($errorMsg): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $errorMsg; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="row">
                                <!-- Personal Information Section -->
                                <div class="col-md-6">
                                    <h5 class="mb-4">Personal Information</h5>
                                    <div class="mb-3">
                                        <label for="firstName" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="firstName" name="first_name" value="<?php echo htmlspecialchars($userData['first_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="lastName" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="lastName" name="last_name" value="<?php echo htmlspecialchars($userData['last_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="tel" class="form-label">Telephone</label>
                                        <input type="text" class="form-control" id="tel" name="tel" value="<?php echo htmlspecialchars($userData['tel']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="typeId" class="form-label">User Type</label>
                                        <select class="form-select" id="typeId" name="type_id" required>
                                            <?php mysqli_data_seek($typesResult, 0); ?>
                                            <?php while($type = mysqli_fetch_assoc($typesResult)): ?>
                                                <option value="<?php echo $type['type_id']; ?>" <?php echo $userData['type_id'] == $type['type_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Account Information Section -->
                                <div class="col-md-6">
                                    <h5 class="mb-4">Account Information</h5>
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="newPassword" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="Leave blank to keep current password">
                                        <div class="form-text">Only fill this if you want to change the user's password.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Leave blank to keep current password">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Registration Date</label>
                                        <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($userData['created_at'])); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-4 text-center">
                                <button type="submit" class="btn btn-primary">Update User</button>
                                <a href="users.php" class="btn btn-secondary ms-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password matching validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            
            if (newPassword && confirmPassword) {
                if (newPassword !== confirmPassword) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('newPassword').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirmPassword');
            if (confirmPassword.value) {
                if (this.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        });
    </script>
</body>
</html>