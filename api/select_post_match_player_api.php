<?php
session_start();
require_once '../config/config.php';
require_once '../includes/league_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['selected_index']) || !is_numeric($input['selected_index'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid player selection']);
    exit;
}

$selected_index = (int) $input['selected_index'];
$user_id = $_SESSION['user_id'];

try {
    $db = getDbConnection();

    $result = selectPostMatchPlayer($db, $user_id, $selected_index);

    $db->close();

    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Post-match player selection API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>