<?php
require_once "config.php";

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$userId = $_SESSION["user_id"];

// Check for expired reservations
$checkSql = "SELECT COUNT(*) as count 
             FROM parking_records 
             WHERE user_id = ? 
             AND confirmation_status = 'pending'
             AND reservation_time < DATE_SUB(NOW(), INTERVAL 1 MINUTE)
             AND exit_time IS NULL";

$stmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

echo json_encode(['hasExpired' => $row['count'] > 0]);