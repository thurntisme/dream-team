<?php
session_start();
require_once '../config/config.php';
require_once '../includes/league_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}



try {
    $db = getDbConnection();
    $user_id = $_SESSION['user_id'];
    $current_season = getCurrentSeason($db);

    // Process relegation and promotion
    $result = processRelegationPromotion($db, $current_season);

    $db->close();

    if ($result['success']) {
        // Store result in session for display
        $_SESSION['relegation_result'] = $result;
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Relegation API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}
?>