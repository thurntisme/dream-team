<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/auth_functions.php';
require_once '../includes/league_functions.php';

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

    // Get season to clear from POST data
    $season_to_clear = isset($_POST['season']) ? trim($_POST['season']) : null;
    
    if (!$season_to_clear) {
        // If no season provided, get current season
        $season_to_clear = getCurrentSeasonIdentifier($db);
    }

    // Delete all league team rosters for the season
    $stmt = $db->prepare('DELETE FROM league_team_rosters WHERE season = :season');
    $stmt->bindValue(':season', $season_to_clear, SQLITE3_TEXT);
    $stmt->execute();

    // Delete all league matches for the season
    $stmt = $db->prepare('DELETE FROM league_matches WHERE season = :season');
    $stmt->bindValue(':season', $season_to_clear, SQLITE3_TEXT);
    $stmt->execute();

    // Delete all league teams for the season
    $stmt = $db->prepare('DELETE FROM league_teams WHERE season = :season');
    $stmt->bindValue(':season', $season_to_clear, SQLITE3_TEXT);
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
