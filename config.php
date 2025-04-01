<?php
// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "parking_management1";
// Define base URL for the project
$base_url = "http://localhost/parking_system1/";
// Create database connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Function to redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to redirect to user dashboard if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Function to generate password hash
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Function to set alert message
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Function to display alert message
function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $type = $_SESSION['alert']['type'];
        $message = $_SESSION['alert']['message'];
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['alert']);
    } else {
        echo "<div class='alert alert-info'>No alerts to display.</div>";
    }
}

// Function to calculate parking fee
if (!function_exists('calculateParkingFee')) {
    function calculateParkingFee($entryTime, $exitTime = null, $userType = 'normal') {
        // If exit time is null, use current time
        if ($exitTime === null) {
            $exitTime = date('Y-m-d H:i:s');
        }
        
        // Convert to timestamps
        $entry = strtotime($entryTime);
        $exit = strtotime($exitTime);
        
        // Calculate hours (rounded up)
        $hours = ceil(($exit - $entry) / 3600);
        
        // Different rates based on user type
        if ($userType == 'sub') {
            // Subscribers get discounted rate
            $hourlyRate = 5; // 5 baht per hour
        } else {
            // Normal rate
            $hourlyRate = 10; // 10 baht per hour
        }
        
        // Calculate fee
        $fee = $hours * $hourlyRate;
        
        // Return both fee and hours
        return [
            'fee' => $fee,
            'hours' => $hours,
            'hourly_rate' => $hourlyRate
        ];
    }
}