<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');



$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['news_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request - news_id required']);
    exit;
}

$news_id = (int) $input['news_id'];

if ($news_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid news ID']);
    exit;
}

try {
    $db = getDbConnection();

    // Verify the news item belongs to the current user before deleting
    $stmt = $db->prepare('SELECT id FROM news WHERE id = :news_id AND user_id = :user_id');
    $stmt->bindValue(':news_id', $news_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $newsItem = $result->fetchArray(SQLITE3_ASSOC);

    if (!$newsItem) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'News item not found or access denied']);
        exit;
    }

    // Delete the news item
    $stmt = $db->prepare('DELETE FROM news WHERE id = :news_id AND user_id = :user_id');
    $stmt->bindValue(':news_id', $news_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'News item deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete news item']);
    }

    $db->close();

} catch (Exception $e) {
    error_log("Delete news API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>