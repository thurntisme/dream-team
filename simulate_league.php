<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'includes/league_functions.php';

// Check authentication
requireAuth();
requireClubName();

try {
    $db = getDbConnection();

    $user_id = $_SESSION['user_id'];
    $current_season = getCurrentSeason($db);
    $current_gameweek = getCurrentGameweek($db, $current_season);

    // Simulate the current gameweek
    $gameweek_results = simulateCurrentGameweek($db, $user_id, $current_season, $current_gameweek);

    // Store results in session for display
    $_SESSION['gameweek_results'] = $gameweek_results;

    $db->close();
} catch (Exception $e) {
    error_log("Simulate league error: " . $e->getMessage());
    header('Location: league.php?tab=standings&error=simulation_failed');
    exit;
}

// Redirect back to league page with detailed results
header('Location: league.php?tab=standings&gameweek_completed=1');
exit;
?>