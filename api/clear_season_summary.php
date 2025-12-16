<?php
session_start();

// Clear season summary from session
if (isset($_SESSION['season_end_summary'])) {
    unset($_SESSION['season_end_summary']);
}

echo json_encode(['success' => true]);
?>