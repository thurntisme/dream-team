<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

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
        
        // Create stadiums table if it doesn't exist
        $db->exec('CREATE TABLE IF NOT EXISTS stadiums (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT DEFAULT "Home Stadium",
            capacity INTEGER DEFAULT 10000,
            level INTEGER DEFAULT 1,
            facilities TEXT DEFAULT "{}",
            last_upgrade DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )');
        
        // Check if stadium already exists for this user
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM stadiums WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $stadium_exists = $result->fetchArray(SQLITE3_ASSOC)['count'] > 0;
        
        // Create default stadium if it doesn't exist
        if (!$stadium_exists) {
            $stmt = $db->prepare('INSERT INTO stadiums (user_id, name) VALUES (:user_id, :name)');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':name', $club_name . ' Stadium', SQLITE3_TEXT);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $db->lastErrorMsg()]);
    }

    $db->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>