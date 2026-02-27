<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/league_functions.php'; // reuse helpers (generateScore etc)
require_once __DIR__ . '/../includes/utility_functions.php';

$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logPath = $logDir . DIRECTORY_SEPARATOR . 'online_match_simulator_api.log';
$log = function ($event, $payload = []) use ($logPath) {
    $entry = json_encode(['ts' => date('c'), 'event' => $event, 'payload' => $payload]);
    error_log($entry . PHP_EOL, 3, $logPath);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $log('invalid_method', ['method' => $_SERVER['REQUEST_METHOD'] ?? null]);
    echo json_encode(['ok' => false, 'error' => 'invalid_method']);
    exit;
}

$home_uuid = $_POST['home_uuid'] ?? null;
$away_uuid = $_POST['away_uuid'] ?? null;
$simulate_flag = $_POST['simulate'] ?? null;
if (!$home_uuid || !$away_uuid || !$simulate_flag) {
    $log('missing_params', compact('home_uuid', 'away_uuid', 'simulate_flag'));
    echo json_encode(['ok' => false, 'error' => 'missing_params']);
    exit;
}


if (!isset($_SESSION['user_uuid'])) {
    $log('not_authenticated', []);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

try {
    $db = getDbConnection();
    $user_uuid = $_SESSION['user_uuid'];
    $award = rand(10000, 50000);
    $log('start', ['user_uuid' => $user_uuid, 'home' => $home_uuid, 'away' => $away_uuid]);

    // fetch club rows
    $stmt = $db->prepare('SELECT user_uuid, club_name, team FROM user_club WHERE user_uuid = :uuid');
    $stmt->bindValue(':uuid', $home_uuid, SQLITE3_TEXT);
    $res = $stmt->execute();
    $home_club = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;

    $stmt->bindValue(':uuid', $away_uuid, SQLITE3_TEXT);
    $res = $stmt->execute();
    $away_club = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;

    if (!$home_club || !$away_club) {
        $log('club_not_found', ['home' => !!$home_club, 'away' => !!$away_club]);
        echo json_encode(['ok' => false, 'error' => 'club_not_found']);
        exit;
    }

    // helper to compute simple strength based on player ratings
    function computeClubStrength($club)
    {
        $team = json_decode($club['team'] ?? '[]', true) ?? [];
        $strength = 0;
        foreach ($team as $pl) {
            $rating = (int) ($pl['rating'] ?? 0);
            $fitness = (int) ($pl['fitness'] ?? 50);
            $strength += $rating * ($fitness / 100);
        }
        // normalize into 20-80 range
        if ($strength <= 0) {
            $strength = 20;
        } else {
            $strength = 20 + min(60, $strength / max(1, count($team)));
        }
        // add randomness
        $strength += rand(-5, 5);
        return max(20, min(80, $strength));
    }

    $home_strength = computeClubStrength($home_club);
    $away_strength = computeClubStrength($away_club);

    // home advantage
    $home_score = generateScore($home_strength + 5);
    $away_score = generateScore($away_strength);

    // persist match data (award column added for future use)
    $db->exec('CREATE TABLE IF NOT EXISTS online_matches (
        id INT PRIMARY KEY AUTO_INCREMENT,
        uuid VARCHAR(255),
        home_user_uuid VARCHAR(255),
        away_user_uuid VARCHAR(255),
        home_score INT,
        away_score INT,
        home_team_data TEXT,
        away_team_data TEXT,
        award BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );');

    $match_uuid = generateUUID();
    $stmtIns = $db->prepare('INSERT INTO online_matches (uuid, home_user_uuid, away_user_uuid, home_score, away_score, home_team_data, away_team_data, award) VALUES (:uuid, :h, :a, :hs, :as, :ht, :at, :award)');
    $stmtIns->bindValue(':uuid', $match_uuid, SQLITE3_TEXT);
    $stmtIns->bindValue(':h', $home_uuid, SQLITE3_TEXT);
    $stmtIns->bindValue(':a', $away_uuid, SQLITE3_TEXT);
    $stmtIns->bindValue(':hs', $home_score, SQLITE3_INTEGER);
    $stmtIns->bindValue(':as', $away_score, SQLITE3_INTEGER);
    $stmtIns->bindValue(':ht', $home_club['team'], SQLITE3_TEXT);
    $stmtIns->bindValue(':at', $away_club['team'], SQLITE3_TEXT);
    $stmtIns->bindValue(':award', $award, SQLITE3_TEXT);
    $stmtIns->execute();

    // deduct cost from user's budget
    $cost = 5000;
    $netCost = max(0, $cost - $award);

    $stmtUpd = $db->prepare('
        UPDATE user_club
        SET budget = budget - :cost
        WHERE user_uuid = :uuid
        AND budget >= :cost
    ');
    if ($stmtUpd) {
        $stmtUpd->bindValue(':cost', $netCost, SQLITE3_INTEGER);
        $stmtUpd->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
        $stmtUpd->execute();
    }

    $db->close();

    $log('success', ['match_uuid' => $match_uuid, 'home_score' => $home_score, 'away_score' => $away_score, 'cost' => $cost]);
    echo json_encode([
        'ok' => true,
        'match_uuid' => $match_uuid,
        'home_score' => $home_score,
        'away_score' => $away_score,
        'home_strength' => $home_strength,
        'away_strength' => $away_strength,
        'home_team_data' => $home_club['team'] ?? null,
        'away_team_data' => $away_club['team'] ?? null,
        'award' => $award,
        'cost' => $cost
    ]);
} catch (Throwable $e) {
    $log('exception', ['message' => $e->getMessage()]);
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
