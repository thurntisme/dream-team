<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/league_functions.php';

$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
$logPath = $logDir . DIRECTORY_SEPARATOR . 'match_simulator_api.log';
$log = function($event, $payload = []) use ($logPath) {
    $entry = json_encode(['ts' => date('c'), 'event' => $event, 'payload' => $payload]);
    error_log($entry . PHP_EOL, 3, $logPath);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $log('invalid_method', ['method' => $_SERVER['REQUEST_METHOD'] ?? null]);
    echo json_encode(['ok' => false, 'error' => 'invalid_method']);
    exit;
}

$uuid = $_POST['uuid'] ?? ($_GET['uuid'] ?? null);
$simulate_flag = $_POST['simulate_match'] ?? null;
if (!$uuid || !$simulate_flag) {
    $log('missing_params', ['uuid' => $uuid, 'simulate' => $simulate_flag]);
    echo json_encode(['ok' => false, 'error' => 'missing_params']);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_uuid'])) {
    $log('not_authenticated', []);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

try {
    $db = getDbConnection();
    $user_id = (int)$_SESSION['user_id'];
    $user_uuid = $_SESSION['user_uuid'];
    $log('start', ['uuid' => $uuid, 'user_id' => $user_id, 'user_uuid' => $user_uuid]);

    // Load match by uuid (allow AI teams with NULL user_uuid via LEFT JOIN)
    $stmt = $db->prepare('
        SELECT lm.*,
               ht.user_uuid as home_user_uuid,
               at.user_uuid as away_user_uuid
        FROM league_matches lm
        LEFT JOIN league_teams ht ON lm.home_team_id = ht.id
        LEFT JOIN league_teams at ON lm.away_team_id = at.id
        WHERE lm.uuid = :uuid
        LIMIT 1
    ');
    if ($stmt === false) {
        $log('prepare_failed_load', []);
        echo json_encode(['ok' => false, 'error' => 'prepare_failed']);
        exit;
    }
    $stmt->bindValue(':uuid', $uuid);
    $res = $stmt->execute();
    if ($res === false) {
        $log('execute_failed_load', []);
        echo json_encode(['ok' => false, 'error' => 'execute_failed']);
        exit;
    }
    $match = $res->fetchArray(SQLITE3_ASSOC);
    if (!$match) {
        $log('match_not_found', ['uuid' => $uuid]);
        echo json_encode(['ok' => false, 'error' => 'match_not_found']);
        exit;
    }

    // Only allow simulation if user participates and match is scheduled
    $is_user_match = ($match['home_user_uuid'] === $user_uuid) || ($match['away_user_uuid'] === $user_uuid);
    if (!$is_user_match) {
        $log('not_your_match', ['uuid' => $uuid, 'home' => $match['home_user_uuid'] ?? null, 'away' => $match['away_user_uuid'] ?? null]);
        echo json_encode(['ok' => false, 'error' => 'not_your_match']);
        exit;
    }
    if (($match['status'] ?? 'scheduled') !== 'scheduled') {
        $log('invalid_status', ['uuid' => $uuid, 'status' => $match['status'] ?? null]);
        echo json_encode(['ok' => false, 'error' => 'invalid_status']);
        exit;
    }

    $ok = simulateMatchByUUID($db, $uuid, $user_id);
    if (!$ok) {
        $log('simulate_failed', ['uuid' => $uuid, 'user_id' => $user_id]);
        echo json_encode(['ok' => false, 'error' => 'simulate_failed']);
        exit;
    }

    // Verify completion
    $stmtChk = $db->prepare('SELECT status FROM league_matches WHERE uuid = :uuid');
    if ($stmtChk === false) {
        $log('prepare_failed_check', []);
        echo json_encode(['ok' => false, 'error' => 'prepare_failed_check']);
        exit;
    }
    $stmtChk->bindValue(':uuid', $uuid);
    $resChk = $stmtChk->execute();
    $rowChk = $resChk ? $resChk->fetchArray(SQLITE3_ASSOC) : null;
    if (!$rowChk || ($rowChk['status'] ?? '') !== 'completed') {
        $log('not_completed', ['uuid' => $uuid]);
        echo json_encode(['ok' => false, 'error' => 'not_completed']);
        exit;
    }

    $db->close();
    $log('success', ['uuid' => $uuid]);
    echo json_encode(['ok' => true, 'match_result_uuid' => $uuid]);
} catch (Throwable $e) {
    $log('exception', ['message' => $e->getMessage()]);
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
