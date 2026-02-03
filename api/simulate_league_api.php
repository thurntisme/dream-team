<?php
session_start();
require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';
require_once '../includes/league_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    $db = getDbConnection();

    $user_id = $_SESSION['user_id'];
    $current_season = getCurrentSeason($db);
    $current_gameweek = getCurrentGameweek($db, $current_season);

    // Simulate the current gameweek (remaining matches)
    $gameweek_results = simulateCurrentGameweek($db, $user_id, $current_season, $current_gameweek);

    // Store results in session for display
    $_SESSION['gameweek_results'] = $gameweek_results;

    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Gameweek simulated successfully',
        'results' => $gameweek_results
    ]);
} catch (Exception $e) {
    error_log("Simulate league API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Simulation failed: ' . $e->getMessage()]);
}
