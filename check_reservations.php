<?php
require_once "config.php";

// Get all pending reservations older than 20 minutes
$checkReservationsSql = "SELECT record_id, spot_id 
                        FROM parking_records 
                        WHERE confirmation_status = 'pending' 
                        AND reservation_time < DATE_SUB(NOW(), INTERVAL 20 MINUTE)";

$result = mysqli_query($conn, $checkReservationsSql);

while ($row = mysqli_fetch_assoc($result)) {
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update parking record status to expired
        $updateRecordSql = "UPDATE parking_records 
                           SET confirmation_status = 'expired' 
                           WHERE record_id = ?";
        $stmt = mysqli_prepare($conn, $updateRecordSql);
        mysqli_stmt_bind_param($stmt, "i", $row['record_id']);
        mysqli_stmt_execute($stmt);
        
        // Free up the parking spot
        $updateSpotSql = "UPDATE parking_spots 
                         SET status = 'free' 
                         WHERE spot_id = ?";
        $stmt = mysqli_prepare($conn, $updateSpotSql);
        mysqli_stmt_bind_param($stmt, "i", $row['spot_id']);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error cancelling reservation: " . $e->getMessage());
    }
}