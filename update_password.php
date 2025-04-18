<?php
require_once "config.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION["user_id"];
    $currentPassword = $_POST["current_password"];
    $newPassword = $_POST["new_password"];
    $confirmPassword = $_POST["confirm_password"];
    
    // Verify current password
    $sql = "SELECT password FROM user WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (password_verify($currentPassword, $user['password'])) {
        if ($newPassword === $confirmPassword) {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql = "UPDATE user SET password = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $userId);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: profile.php?success=password_updated");
                exit();
            } else {
                header("Location: profile.php?error=update_failed");
                exit();
            }
        } else {
            header("Location: profile.php?error=passwords_dont_match");
            exit();
        }
    } else {
        header("Location: profile.php?error=invalid_password");
        exit();
    }
}