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

// Calculate total cost and identifying players to update
$total_cost = 0;
$cost_per_point = 1000; // Cost per 1% fitness recovery

$target_player_uuid = isset($_POST['player_uuid']) ? $_POST['player_uuid'] : null;
$player_found = false;

if ($target_player_uuid) {
    // Upgrade only the specified player
    foreach ($team_players as $index => &$player) {
        if ($player && isset($player['uuid']) && $player['uuid'] === $target_player_uuid) {
            $player_found = true;
            $fitnessVal = $player['fitness'] ?? 0;
            if ($fitnessVal < 0) {
                // injured, recommend injury power instead
                echo json_encode([
                    'success' => false,
                    'message' => 'Player is injured; consider using an injury power to treat them.'
                ]);
                exit;
            }
            if ($fitnessVal < 100) {
                $missing_fitness = 100 - $fitnessVal;
                $cost = $missing_fitness * $cost_per_point;
                $rating_multiplier = 1.0;
                if (isset($player['rating'])) {
                    $rating_multiplier = max(1.0, $player['rating'] / 75);
                }
                $cost = round($cost * $rating_multiplier);
                $total_cost += $cost;
                $player['fitness'] = 100;
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
        if ($player && isset($player['fitness']) && $player['fitness'] < 100 && $player['fitness'] >= 0) {
            $missing_fitness = 100 - $player['fitness'];
            $cost = $missing_fitness * $cost_per_point;
            $rating_multiplier = 1.0;
            if (isset($player['rating'])) {
                $rating_multiplier = max(1.0, $player['rating'] / 75);
            }
            $cost = round($cost * $rating_multiplier);
            $total_cost += $cost;
            $player['fitness'] = 100;
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
        'message' => 'All players are already at full fitness',
        'cost' => 0,
        'new_budget' => $current_budget
    ]);
    exit;
}

// Deduct budget and update team
$new_budget = $current_budget - $total_cost;

// SQLite transaction
$conn->exec('START TRANSACTION');

try {
    // Update user data (budget, team) in one go
    $team_json = json_encode($team_players);

    $stmt = $conn->prepare("UPDATE user_club SET budget = :budget, team = :team WHERE user_uuid = :user_uuid");
    $stmt->bindValue(':budget', $new_budget, SQLITE3_FLOAT);
    $stmt->bindValue(':team', $team_json, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $stmt->execute();

    $conn->exec('COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Team fitness upgraded successfully',
        'cost' => $total_cost,
        'new_budget' => $new_budget,
        'updated_team' => $team_players,
    ]);
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
