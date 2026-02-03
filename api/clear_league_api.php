<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/auth_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = getDbConnection();
    $user_id = $_SESSION['user_id'];
    $current_season = date('Y');

    // Get current season
    $season_to_clear = isset($_POST['season']) ? intval($_POST['season']) : $current_season;

    // Delete all league matches for the season
    $stmt = $db->prepare('DELETE FROM league_matches WHERE season = :season');
    $stmt->bindValue(':season', $season_to_clear, SQLITE3_INTEGER);
    $stmt->execute();

    // Delete all league teams for the season
    $stmt = $db->prepare('DELETE FROM league_teams WHERE season = :season');
    $stmt->bindValue(':season', $season_to_clear, SQLITE3_INTEGER);
    $stmt->execute();

    $db->close();

    echo json_encode([
        'success' => true,
        'message' => "League for season {$season_to_clear} has been cleared successfully!",
        'season' => $season_to_clear
    ]);

} catch (Exception $e) {
    error_log("Clear league error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear league: ' . $e->getMessage()
    ]);
}
?>
