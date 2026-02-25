<?php
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

// Get database connection
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


$total_cost = 0;
$cost_per_point = 50000; // Much higher cost for form upgrade (game balance)

$target_player_uuid = isset($_POST['player_uuid']) ? $_POST['player_uuid'] : null;
$player_found = false;

if ($target_player_uuid) {
    // Upgrade only the specified player
    foreach ($team_players as $index => &$player) {
        if ($player && isset($player['uuid']) && $player['uuid'] === $target_player_uuid) {
            $current_form = isset($player['form']) ? floatval($player['form']) : 7.0;
            if ($current_form < 10.0) {
                $missing_form = 10.0 - $current_form;
                $cost = $missing_form * $cost_per_point;
                $rating_multiplier = 1.0;
                if (isset($player['rating'])) {
                    $rating_multiplier = max(1.0, $player['rating'] / 75);
                }
                $cost = round($cost * $rating_multiplier);
                $total_cost += $cost;
                $player['form'] = 10.0;
                $player_found = true;
            } else {
                // Already at peak form
                $player_found = true;
            }
            break;
        }
    }
    unset($player);
    if (!$player_found) {
        echo json_encode([
            'success' => false,
            'message' => 'Player not found in your team',
        ]);
        exit;
    }
} else {
    // Upgrade all players as before
    foreach ($team_players as $index => &$player) {
        $current_form = isset($player['form']) ? floatval($player['form']) : 7.0;
        if ($player && $current_form < 10.0) {
            $missing_form = 10.0 - $current_form;
            $cost = $missing_form * $cost_per_point;
            $rating_multiplier = 1.0;
            if (isset($player['rating'])) {
                $rating_multiplier = max(1.0, $player['rating'] / 75);
            }
            $cost = round($cost * $rating_multiplier);
            $total_cost += $cost;
            $player['form'] = 10.0;
        }
    }
    unset($player);
}

// Check if user has enough budget
if ($total_cost > $current_budget) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient budget',
        'cost' => $total_cost,
        'budget' => $current_budget
    ]);
    exit;
}

if ($total_cost == 0) {
    echo json_encode([
        'success' => true,
        'message' => 'All players already at peak form',
        'cost' => 0,
        'new_budget' => $current_budget,
        'updated_team' => $team_players,
        'updated_substitutes' => $substitute_players
    ]);
    exit;
}

// Perform transaction
$conn->exec('START TRANSACTION');

try {
    // 1. Deduct budget
    $new_budget = $current_budget - $total_cost;
    
    // 2. Update database
    $stmt = $conn->prepare("UPDATE user_club SET budget = :budget, team = :team WHERE user_uuid = :user_uuid");
    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
    $stmt->bindValue(':team', json_encode($team_players), SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $stmt->execute();
    
    $conn->exec('COMMIT');
    
    echo json_encode([
        'success' => true,
        'message' => 'Team form boosted successfully',
        'cost' => $total_cost,
        'new_budget' => $new_budget,
        'updated_team' => $team_players,
    ]);
    
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo json_encode([
        'success' => false, 
        'message' => 'Database error during update: ' . $e->getMessage()
    ]);
}
