<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'redirect' => 'install.php']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$formation = $_POST['formation'] ?? '';
$team = $_POST['team'] ?? '';

try {
    $db = getDbConnection();

    $stmt = $db->prepare('UPDATE users SET formation = :formation, team = :team WHERE id = :id');
    $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
    $stmt->bindValue(':team', $team, SQLITE3_TEXT);
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $db->lastErrorMsg()]);
    }

    $db->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>