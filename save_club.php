<?php
session_start();
header('Content-Type: application/json');

require_once 'config/config.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'redirect' => 'install.php']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$club_name = $_POST['club_name'] ?? '';

try {
    $db = getDbConnection();

    $stmt = $db->prepare('UPDATE users SET club_name = :club_name WHERE id = :id');
    $stmt->bindValue(':club_name', $club_name, SQLITE3_TEXT);
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if ($stmt->execute()) {
        $_SESSION['club_name'] = $club_name;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $db->lastErrorMsg()]);
    }

    $db->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>