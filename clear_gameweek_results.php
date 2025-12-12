<?php
session_start();

// Clear gameweek results from session
if (isset($_SESSION['gameweek_results'])) {
    unset($_SESSION['gameweek_results']);
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>