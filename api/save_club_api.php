<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'redirect' => 'install.php']);
    exit;
}



$club_name = $_POST['club_name'] ?? '';

try {
    $db = getDbConnection();

    $stmt = $db->prepare('UPDATE user_club SET club_name = :club_name WHERE user_uuid = :user_uuid');
    if ($stmt !== false) {
        $stmt->bindValue(':club_name', $club_name, SQLITE3_TEXT);
        $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
    }

    if ($stmt !== false && $stmt->execute()) {
        $_SESSION['club_name'] = $club_name;
        
        // Database tables are now created in install.php
        
        // Check if stadium already exists for this user
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM stadiums WHERE user_id = :user_id');
        if ($stmt !== false) {
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $stadium_exists = $result ? ($result->fetchArray(SQLITE3_ASSOC)['count'] > 0) : false;
        } else {
            $stadium_exists = false;
        }
        
        // Create default stadium if it doesn't exist
        if (!$stadium_exists) {
            $stmt = $db->prepare('INSERT INTO stadiums (user_id, name) VALUES (:user_id, :name)');
            if ($stmt !== false) {
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':name', $club_name . ' Stadium', SQLITE3_TEXT);
                $stmt->execute();
            }
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
