<?php
require_once "../config.php";

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordId = $_POST['record_id'];
    $action = $_POST['action'];
    
    mysqli_begin_transaction($conn);
    
    try {
        if ($action === 'confirm') {
            // Update parking record status to confirmed and set entry time to NOW()
            $updateRecordSql = "UPDATE parking_records 
                               SET confirmation_status = 'confirmed',
                                   entry_time = NOW() 
                               WHERE record_id = ?";
        } else {
            // Update parking record status to cancelled and free up the spot
            $updateRecordSql = "UPDATE parking_records pr
                               JOIN parking_spots ps ON pr.spot_id = ps.spot_id
                               SET pr.confirmation_status = 'cancelled',
                                   ps.status = 'free'
                               WHERE pr.record_id = ?";
        }
        
        $stmt = mysqli_prepare($conn, $updateRecordSql);
        mysqli_stmt_bind_param($stmt, "i", $recordId);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}