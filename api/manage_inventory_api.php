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
            // Get user's full team (lineup + substitutes) and formation
            $stmt = $db->prepare('SELECT formation, team, max_players FROM user_club WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $user_data = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user_data) {
                throw new Exception('User not found');
            }

            $full_team = json_decode($user_data['team'] ?? '[]', true) ?: [];
            $formation = $user_data['formation'] ?? '4-4-2';
            $max_players = $user_data['max_players'] ?? 23;

            // Calculate lineup slot count (always 11)
            $lineup_size = 11;
            // Ensure array has at least 11 entries (lineup), pad with nulls
            if (count($full_team) < $lineup_size) {
                $full_team = array_pad($full_team, $lineup_size, null);
            }

            // Split into lineup and substitutes
            $lineup = array_slice($full_team, 0, $lineup_size);
            $subs = array_slice($full_team, $lineup_size);

            // Count total current players (non-null) across lineup and subs
            $total_players = count(array_filter($lineup, fn($p) => $p !== null)) + count(array_filter($subs, fn($p) => $p !== null));
            if ($total_players >= $max_players) {
                throw new Exception('Your squad is full. Maximum players allowed: ' . $max_players);
            }

            // Prevent duplicates across entire squad by name
            foreach (array_merge($lineup, $subs) as $existing_player) {
                if (
                    $existing_player && isset($existing_player['name']) &&
                    strtolower($existing_player['name']) === strtolower($player_data['name'])
                ) {
                    throw new Exception('You already have this player in your squad');
                }
            }

            // Initialize player condition
            $player_data = initializePlayerCondition($player_data);

            // Append to substitutes list
            $subs[] = $player_data;

            // Recombine and persist
            $combined = array_merge($lineup, $subs);
            $stmt = $db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':team', json_encode($combined), SQLITE3_TEXT);
            $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
            if (!$stmt->execute()) {
                throw new Exception('Failed to add player to squad: ' . $db->lastErrorMsg());
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
