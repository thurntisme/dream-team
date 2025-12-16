<?php
session_start();

// Clear gameweek results from session
if (isset($_SESSION['gameweek_results'])) {
    unset($_SESSION['gameweek_results']);
}

// Clear post-match rewards if user ignores them
if (isset($_SESSION['post_match_players'])) {
    unset($_SESSION['post_match_players']);
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>