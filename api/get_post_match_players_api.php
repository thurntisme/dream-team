<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if post-match players are available
if (!isset($_SESSION['post_match_players'])) {
    echo json_encode(['success' => false, 'message' => 'No post-match players available']);
    exit;
}

$post_match_data = $_SESSION['post_match_players'];

// Validate session data
if ($post_match_data['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user session']);
    exit;
}

if (time() > $post_match_data['expires_at']) {
    unset($_SESSION['post_match_players']);
    echo json_encode(['success' => false, 'message' => 'Player selection has expired']);
    exit;
}

// Return the player options
echo json_encode([
    'success' => true,
    'players' => $post_match_data['options'],
    'expires_at' => $post_match_data['expires_at'],
    'time_remaining' => $post_match_data['expires_at'] - time()
]);
?>