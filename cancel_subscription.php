<?php
require_once "config.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_subscription'])) {
    $userId = $_SESSION["user_id"];
    $subscriptionId = $_POST["subscription_id"];
    
    // Set status to 'cancelled', but do not change end_date
    $updateSql = "UPDATE user_subscriptions SET status = 'cancelled' WHERE subscription_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "ii", $subscriptionId, $userId);
    mysqli_stmt_execute($stmt);
    
    header("Location: profile.php?success=subscription_cancelled");
    exit();
}