<?php
require_once "../config.php";

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Update expired reservations
    $updateExpiredSql = "UPDATE parking_records pr
                        JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                        SET pr.confirmation_status = 'expired',
                            pr.payment_status = 'expired',
                            pr.exit_time = NOW(),
                            ps.status = 'free'
                        WHERE pr.confirmation_status = 'pending'
                        AND pr.reservation_time < DATE_SUB(NOW(), INTERVAL 1 MINUTE)";

    mysqli_query($conn, $updateExpiredSql);
    
    mysqli_commit($conn);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}