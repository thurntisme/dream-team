<?php
require_once '../config/config.php';
require_once '../config/constants.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get database connection
try {
    $conn = getDbConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Get current user data (budget, team, substitutes)
$stmt = $conn->prepare("SELECT budget, team, substitutes FROM users WHERE id = :id");
$stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$current_budget = $user['budget'];
$team_players = json_decode($user['team'], true) ?: [];
$substitute_players = json_decode($user['substitutes'], true) ?: [];

// Calculate total cost and identifying players to update
$total_cost = 0;
$cost_per_point = 1000; // Cost per 1% fitness recovery

// Process main team
foreach ($team_players as $index => &$player) {
    if ($player && isset($player['fitness']) && $player['fitness'] < 100) {
        $missing_fitness = 100 - $player['fitness'];
        $cost = $missing_fitness * $cost_per_point;

        // Adjust cost based on player rating
        $rating_multiplier = 1.0;
        if (isset($player['rating'])) {
            $rating_multiplier = max(1.0, $player['rating'] / 75);
        }

        $cost = round($cost * $rating_multiplier);
        $total_cost += $cost;

        $player['fitness'] = 100;
    }
}
unset($player); // Break reference

// Process substitutes
foreach ($substitute_players as $index => &$player) {
    if ($player && isset($player['fitness']) && $player['fitness'] < 100) {
        $missing_fitness = 100 - $player['fitness'];
        $cost = $missing_fitness * $cost_per_point;

        // Adjust cost based on player rating
        $rating_multiplier = 1.0;
        if (isset($player['rating'])) {
            $rating_multiplier = max(1.0, $player['rating'] / 75);
        }

        $cost = round($cost * $rating_multiplier);
        $total_cost += $cost;

        $player['fitness'] = 100;
    }
}
unset($player); // Break reference

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
$conn->exec('BEGIN TRANSACTION');

try {
    // Update user data (budget, team, substitutes) in one go
    $team_json = json_encode($team_players);
    $substitutes_json = json_encode($substitute_players);

    $stmt = $conn->prepare("UPDATE users SET budget = :budget, team = :team, substitutes = :substitutes WHERE id = :id");
    $stmt->bindValue(':budget', $new_budget, SQLITE3_FLOAT);
    $stmt->bindValue(':team', $team_json, SQLITE3_TEXT);
    $stmt->bindValue(':substitutes', $substitutes_json, SQLITE3_TEXT);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();

    $conn->exec('COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Team fitness upgraded successfully',
        'cost' => $total_cost,
        'new_budget' => $new_budget,
        'updated_team' => $team_players,
        'updated_substitutes' => $substitute_players
    ]);
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
