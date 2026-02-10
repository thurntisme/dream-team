<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');



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

    $db->exec('START TRANSACTION');

    // Verify the inventory item belongs to the current club
    $stmt = $db->prepare('SELECT * FROM player_inventory WHERE id = :id AND club_uuid = (SELECT club_uuid FROM user_club WHERE user_uuid = :user_uuid) AND status = "available"');
    $stmt->bindValue(':id', $inventory_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $inventory_item = $result->fetchArray(SQLITE3_ASSOC);

    if (!$inventory_item) {
        throw new Exception('Player not found in your inventory');
    }

    switch ($action) {
        case 'assign':
            // Get user's formation and current team data
            $stmt = $db->prepare('SELECT formation, team, max_players FROM user_club WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $user_data = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user_data) {
                throw new Exception('User not found');
            }

            $saved_team = json_decode($user_data['team'] ?? '[]', true) ?: [];
            $formation = $user_data['formation'] ?? '4-4-2';
            $max_players = $user_data['max_players'] ?? 23;

            // Determine total starting slots based on formation
            $positions = FORMATIONS[$formation]['positions'] ?? [];
            $total_slots = 0;
            foreach ($positions as $line) {
                $total_slots += count($line);
            }

            // Initialize team array to formation slot size if empty
            if (!is_array($saved_team) || count($saved_team) === 0) {
                $saved_team = array_fill(0, $total_slots, null);
            } else if (count($saved_team) < $total_slots) {
                // Pad with nulls if team shorter than formation slots
                $saved_team = array_pad($saved_team, $total_slots, null);
            }

            // Count current starting players
            $starting_players = count(array_filter($saved_team, function($p) { return $p !== null; }));
            if ($starting_players >= $max_players) {
                throw new Exception('Your squad is full. Maximum players allowed: ' . $max_players);
            }

            // Prevent duplicates by name
            foreach ($saved_team as $existing_player) {
                if (
                    $existing_player && isset($existing_player['name']) &&
                    strtolower($existing_player['name']) === strtolower($player_data['name'])
                ) {
                    throw new Exception('You already have this player in your team');
                }
            }

            // Initialize player condition
            $player_data = initializePlayerCondition($player_data);

            // Assign to first empty slot; if none, error
            $assigned = false;
            for ($i = 0; $i < $total_slots; $i++) {
                if ($saved_team[$i] === null) {
                    $saved_team[$i] = $player_data;
                    $assigned = true;
                    break;
                }
            }

            if (!$assigned) {
                throw new Exception('No empty slot in your starting lineup. Manage your team formation first.');
            }

            // Persist updated team
            $stmt = $db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':team', json_encode($saved_team), SQLITE3_TEXT);
            $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
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
            $stmt = $db->prepare('SELECT budget FROM user_club WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $user_data = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user_data) {
                throw new Exception('User not found');
            }

            $new_budget = $user_data['budget'] + $sell_price;

            // Update user's budget
            $stmt = $db->prepare('UPDATE user_club SET budget = :budget WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);

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
