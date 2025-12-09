<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$formation = $_POST['formation'] ?? '';
$team = $_POST['team'] ?? '';

try {
    $db = new SQLite3('dreamteam.db');
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        club_name TEXT,
        formation TEXT,
        team TEXT
    )');

    $stmt = $db->prepare('UPDATE users SET formation = :formation, team = :team WHERE id = :id');
    if ($stmt === false) {
        throw new Exception($db->lastErrorMsg());
    }

    $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
    $stmt->bindValue(':team', $team, SQLITE3_TEXT);
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $db->lastErrorMsg()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>