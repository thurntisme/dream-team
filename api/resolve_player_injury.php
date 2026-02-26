<?php
// API to resolve injuries (fitness below 0) for player's squad
// Author: Generated

require_once '../config/config.php';
require_once '../config/constants.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

session_start();
if (!isset($_SESSION['user_uuid'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}
$user_uuid = $_SESSION['user_uuid'];

// connect
try {
    $conn = getDbConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$stmt = $conn->prepare("SELECT budget, team FROM user_club WHERE user_uuid = :user_uuid");
$stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$current_budget = $user['budget'];
$team_players = json_decode($user['team'], true) ?: [];

// gather list of players that are injured (fitness < 0)
$injured_players = [];
foreach ($team_players as $p) {
    if ($p && isset($p['fitness']) && $p['fitness'] < 0) {
        $injured_players[] = [
            'uuid' => $p['uuid'] ?? null,
            'name' => $p['name'] ?? 'Unknown',
            'position' => $p['position'] ?? null,
            'fitness' => $p['fitness']
        ];
    }
}

// scan for injured and reset fitness
$resolved_count = 0;
$cost_per_player = 500000; // high cost per treatment
$total_cost = 0;

foreach ($team_players as $idx => &$player) {
    if ($player && isset($player['fitness']) && $player['fitness'] < 0) {
        $player['fitness'] = 50;
        $resolved_count++;
        $total_cost += $cost_per_player;
    }
}
unset($player);

if ($resolved_count === 0) {
    echo json_encode(['success' => true, 'message' => 'No injured players to treat', 'cost' => 0, 'new_budget' => $current_budget, 'injured_players' => $injured_players]);
    exit;
}

// check budget
if ($total_cost > $current_budget) {
    echo json_encode(['success' => false, 'message' => 'Insufficient budget', 'cost' => $total_cost, 'budget' => $current_budget, 'injured_players' => $injured_players]);
    exit;
}

$new_budget = $current_budget - $total_cost;

// update database
$conn->exec('START TRANSACTION');
try {
    $stmt = $conn->prepare("UPDATE user_club SET budget = :budget, team = :team WHERE user_uuid = :user_uuid");
    $stmt->bindValue(':budget', $new_budget, SQLITE3_FLOAT);
    $stmt->bindValue(':team', json_encode($team_players), SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $stmt->execute();
    $conn->exec('COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Resolved injuries for ' . $resolved_count . ' player(s)',
        'resolved_count' => $resolved_count,
        'cost' => $total_cost,
        'new_budget' => $new_budget,
        'injured_players' => $injured_players,
        'updated_team' => $team_players
    ]);
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
