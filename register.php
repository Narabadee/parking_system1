<?php
require_once "config.php";

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = sanitize($_POST["first_name"]);
    $last_name = sanitize($_POST["last_name"]);
    $tel = sanitize($_POST["tel"]);
    $username = sanitize($_POST["username"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $error = "";
    
    // Validate input
    if (empty($first_name) || empty($last_name) || empty($tel) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username already exists
        $check_sql = "SELECT auth_id FROM auth_users WHERE username = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $username);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Username already exists. Please choose another one.";
        } else {
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // First insert into user table
                $user_sql = "INSERT INTO user (type_id, first_name, last_name, tel) VALUES (?, ?, ?, ?)";
                $user_stmt = mysqli_prepare($conn, $user_sql);
                $type_id = 1; // Default type is 'normal'
                mysqli_stmt_bind_param($user_stmt, "isss", $type_id, $first_name, $last_name, $tel);
                mysqli_stmt_execute($user_stmt);
                
                // Get the new user_id
                $user_id = mysqli_insert_id($conn);
                
                // Hash the password
                $hashed_password = hashPassword($password);
                
                // Insert into auth_users table
                $auth_sql = "INSERT INTO auth_users (user_id, username, password, role) VALUES (?, ?, ?, ?)";
                $auth_stmt = mysqli_prepare($conn, $auth_sql);
                $role = "user"; // Default role is 'user'
                mysqli_stmt_bind_param($auth_stmt, "isss", $user_id, $username, $hashed_password, $role);
                mysqli_stmt_execute($auth_stmt);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Set success message
                setAlert("success", "Registration successful! You can now login.");
                
                // Redirect to login page
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Parking Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-register {
            background-color: #4e73df;
            border-color: #4e73df;
            width: 100%;
        }
        .login-link {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="logo">
                <h2>Parking Management</h2>
                <p>Create a new account</p>
            </div>
            
            <?php if(isset($error)) { ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php } ?>
            
            <?php displayAlert(); ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row mb-3">
                    <div class="col">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($first_name) ? $first_name : ''; ?>" required>
                    </div>
                    <div class="col">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($last_name) ? $last_name : ''; ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="tel" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="tel" name="tel" value="<?php echo isset($tel) ? $tel : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-register">Register</button>
            </form>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>