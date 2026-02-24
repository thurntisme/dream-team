<?php
session_start();
require_once '../config/config.php';
require_once '../includes/league_functions.php';

header('Content-Type: application/json');



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

try {
    $db = getDbConnection();

    // Resolve numeric user_id from session user_uuid if present
    $user_id = null;
    if (!empty($_SESSION['user_uuid'])) {
        $stmt = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
        if ($stmt) {
            $stmt->bindValue(':uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
            $res = $stmt->execute();
            $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
            $user_id = isset($row['id']) ? (int)$row['id'] : null;
        }
    }

    // Fallback to legacy numeric session id
    if ($user_id === null && !empty($_SESSION['user_id'])) {
        $user_id = (int) $_SESSION['user_id'];
    }

    if ($user_id === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        $db->close();
        exit;
    }

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