<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/constants.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'redirect' => 'install.php']);
    exit;
}



$formation = $_POST['formation'] ?? '';
$team = $_POST['team'] ?? '';
$substitutes = $_POST['substitutes'] ?? '[]';

try {
    $db = getDbConnection();

    $stmt = $db->prepare('UPDATE user_club SET formation = :formation, team = :team WHERE user_uuid = :user_uuid');
    $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
    $stmt->bindValue(':team', $team, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);

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
