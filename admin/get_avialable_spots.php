<?php
require_once "../config.php";

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    // Return empty result if not authorized
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Get zone_id from the GET request
if (isset($_GET['zone_id']) && !empty($_GET['zone_id'])) {
    $zoneId = $_GET['zone_id'];
    
    // Prepare and execute query to get available (free) spots for the selected zone
    $spotsSql = "SELECT spot_id, spot_number FROM parking_spots 
                WHERE zone_id = ? AND status = 'free' 
                ORDER BY spot_number";
    
    $stmt = mysqli_prepare($conn, $spotsSql);
    mysqli_stmt_bind_param($stmt, "i", $zoneId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $spots = [];
    while ($spot = mysqli_fetch_assoc($result)) {
        $spots[] = $spot;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($spots);
} else {
    // Return empty result if no zone_id provided
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>