<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$action = $data['action'] ?? '';
$inventory_id = (int) ($data['inventory_id'] ?? 0);
$player_data = $data['player_data'] ?? null;
$sell_price = (int) ($data['sell_price'] ?? 0);

if (empty($action) || $inventory_id <= 0 || !$player_data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

try {
    $db = getDbConnection();

    // Start transaction
    $db->exec('BEGIN TRANSACTION');

    // Verify the inventory item belongs to the current user
    $stmt = $db->prepare('SELECT * FROM player_inventory WHERE id = :id AND user_id = :user_id AND status = "available"');
    $stmt->bindValue(':id', $inventory_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $inventory_item = $result->fetchArray(SQLITE3_ASSOC);

    if (!$inventory_item) {
        throw new Exception('Player not found in your inventory');
    }

    switch ($action) {
        case 'assign':
            // Get user's current team data
            $stmt = $db->prepare('SELECT team, substitutes, max_players FROM users WHERE id = :user_id');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $user_data = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user_data) {
                throw new Exception('User not found');
            }

            $current_team = json_decode($user_data['team'] ?? '[]', true) ?: [];
            $current_substitutes = json_decode($user_data['substitutes'] ?? '[]', true) ?: [];
            $max_players = $user_data['max_players'] ?? 23;

            // Check if team is full
            $total_players = count($current_team) + count($current_substitutes);
            if ($total_players >= $max_players) {
                throw new Exception('Your squad is full. Maximum players allowed: ' . $max_players);
            }

            // Check if player already exists in team
            foreach ($current_team as $existing_player) {
                if (
                    $existing_player && isset($existing_player['name']) &&
                    strtolower($existing_player['name']) === strtolower($player_data['name'])
                ) {
                    throw new Exception('You already have this player in your team');
                }
            }

            foreach ($current_substitutes as $existing_player) {
                if (
                    $existing_player && isset($existing_player['name']) &&
                    strtolower($existing_player['name']) === strtolower($player_data['name'])
                ) {
                    throw new Exception('You already have this player in your substitutes');
                }
            }

            // Initialize player condition and add to substitutes
            $player_data = initializePlayerCondition($player_data);
            $current_substitutes[] = $player_data;

            // Update user's substitutes
            $stmt = $db->prepare('UPDATE users SET substitutes = :substitutes WHERE id = :user_id');
            $stmt->bindValue(':substitutes', json_encode($current_substitutes), SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to add player to team: ' . $db->lastErrorMsg());
            }

            // Mark inventory item as assigned
            $stmt = $db->prepare('UPDATE player_inventory SET status = "assigned" WHERE id = :id');
            $stmt->bindValue(':id', $inventory_id, SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update inventory: ' . $db->lastErrorMsg());
            }

            break;

        case 'sell':
            // Get user's current budget
            $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $user_data = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user_data) {
                throw new Exception('User not found');
            }

            $new_budget = $user_data['budget'] + $sell_price;

            // Update user's budget
            $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
            $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update budget: ' . $db->lastErrorMsg());
            }

            // Mark inventory item as sold
            $stmt = $db->prepare('UPDATE player_inventory SET status = "sold" WHERE id = :id');
            $stmt->bindValue(':id', $inventory_id, SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update inventory: ' . $db->lastErrorMsg());
            }

            break;

        case 'delete':
            // Mark inventory item as deleted
            $stmt = $db->prepare('UPDATE player_inventory SET status = "deleted" WHERE id = :id');
            $stmt->bindValue(':id', $inventory_id, SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to delete player: ' . $db->lastErrorMsg());
            }

            break;

        default:
            throw new Exception('Invalid action');
    }

    // Commit transaction
    $db->exec('COMMIT');
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Action completed successfully',
        'action' => $action
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->exec('ROLLBACK');
        $db->close();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}