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

    // Combine team (lineup) and substitutes into one array as per unified storage
    $teamArr = json_decode($team, true);
    $subsArr = json_decode($substitutes, true);
    if (!is_array($teamArr)) $teamArr = [];
    if (!is_array($subsArr)) $subsArr = [];
    // Ensure lineup is exactly 11 slots
    if (count($teamArr) < 11) {
        $teamArr = array_pad($teamArr, 11, null);
    } else {
        $teamArr = array_slice($teamArr, 0, 11);
    }
    $combined = array_merge($teamArr, $subsArr);

    $stmtMax = $db->prepare('SELECT max_players FROM user_club WHERE user_uuid = :user_uuid');
    $stmtMax->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
    $resMax = $stmtMax->execute();
    $rowMax = $resMax ? $resMax->fetchArray(SQLITE3_ASSOC) : [];
    $maxPlayers = $rowMax['max_players'] ?? DEFAULT_MAX_PLAYERS;
    if (is_numeric($maxPlayers) && $maxPlayers > 0 && count($combined) > (int)$maxPlayers) {
        $combined = array_slice($combined, 0, (int)$maxPlayers);
    }

    $stmt = $db->prepare('UPDATE user_club SET formation = :formation, team = :team WHERE user_uuid = :user_uuid');
    $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
    $stmt->bindValue(':team', json_encode($combined), SQLITE3_TEXT);
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
