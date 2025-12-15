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

$formation = $_POST['formation'] ?? '';
$team = $_POST['team'] ?? '';
$substitutes = $_POST['substitutes'] ?? '[]';

try {
    $db = getDbConnection();

    // Check if substitutes column exists, if not add it
    $result = $db->query("PRAGMA table_info(users)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }

    if (!in_array('substitutes', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN substitutes TEXT DEFAULT "[]"');
    }

    if (!in_array('max_players', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN max_players INTEGER DEFAULT 23');
    }

    $stmt = $db->prepare('UPDATE users SET formation = :formation, team = :team, substitutes = :substitutes WHERE id = :id');
    $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
    $stmt->bindValue(':team', $team, SQLITE3_TEXT);
    $stmt->bindValue(':substitutes', $substitutes, SQLITE3_TEXT);
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if ($stmt->execute()) {
        // Award experience for team management
        require_once '../includes/helpers.php';
        $expResult = addClubExp($_SESSION['user_id'], 10, 'Team formation updated', $db);

        $response = ['success' => true];
        if ($expResult['success'] && $expResult['leveled_up']) {
            $response['level_up'] = [
                'new_level' => $expResult['new_level'],
                'levels_gained' => $expResult['levels_gained']
            ];
        }

        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => $db->lastErrorMsg()]);
    }

    $db->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>