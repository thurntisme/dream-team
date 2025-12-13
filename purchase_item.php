<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if (!hasClubName()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Club name required. Please complete your profile.']);
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['item_id']) || !isset($input['item_price'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$item_id = (int) $input['item_id'];
$item_price = (int) $input['item_price'];
$user_id = $_SESSION['user_id'];

try {
    $db = getDbConnection();

    // Start transaction
    $db->exec('BEGIN TRANSACTION');

    // Get user's current budget and max_players
    $stmt = $db->prepare('SELECT budget, max_players FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data) {
        throw new Exception('User not found');
    }

    $current_budget = $user_data['budget'];
    $current_max_players = $user_data['max_players'];

    // Check if user has enough budget
    if ($current_budget < $item_price) {
        throw new Exception('Insufficient budget');
    }

    // Get item details
    $stmt = $db->prepare('SELECT * FROM shop_items WHERE id = :item_id');
    $stmt->bindValue(':item_id', $item_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $item = $result->fetchArray(SQLITE3_ASSOC);

    if (!$item) {
        throw new Exception('Item not found');
    }

    // Verify price matches
    if ($item['price'] != $item_price) {
        throw new Exception('Price mismatch');
    }

    // Deduct budget
    $new_budget = $current_budget - $item_price;
    $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update budget');
    }

    // Calculate expiry date if item has duration
    $expires_at = null;
    if ($item['duration'] > 0) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $item['duration'] . ' days'));
    }

    // Add item to user's inventory or increase quantity if already exists
    $stmt = $db->prepare('SELECT id, quantity FROM user_inventory WHERE user_id = :user_id AND item_id = :item_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':item_id', $item_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $existing_item = $result->fetchArray(SQLITE3_ASSOC);

    if ($existing_item) {
        // Update existing item quantity
        $stmt = $db->prepare('UPDATE user_inventory SET quantity = quantity + 1 WHERE id = :id');
        $stmt->bindValue(':id', $existing_item['id'], SQLITE3_INTEGER);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update item quantity');
        }
    } else {
        // Insert new item
        $stmt = $db->prepare('INSERT INTO user_inventory (user_id, item_id, expires_at, quantity) VALUES (:user_id, :item_id, :expires_at, 1)');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':item_id', $item_id, SQLITE3_INTEGER);
        $stmt->bindValue(':expires_at', $expires_at, SQLITE3_TEXT);
        if (!$stmt->execute()) {
            throw new Exception('Failed to add item to inventory');
        }
    }

    // Apply item effects
    $effect_data = json_decode($item['effect_value'], true);
    $additional_message = '';

    switch ($item['effect_type']) {
        case 'squad_expansion':
            if (isset($effect_data['players'])) {
                $players_to_add = (int) $effect_data['players'];
                $new_max_players = $current_max_players + $players_to_add;

                $stmt = $db->prepare('UPDATE users SET max_players = :max_players WHERE id = :user_id');
                $stmt->bindValue(':max_players', $new_max_players, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

                if (!$stmt->execute()) {
                    throw new Exception('Failed to update max players');
                }

                $additional_message = " Your squad size has been increased from {$current_max_players} to {$new_max_players} players!";
            }
            break;

        case 'budget_boost':
            if (isset($effect_data['amount'])) {
                $budget_boost = (int) $effect_data['amount'];
                $boosted_budget = $new_budget + $budget_boost;

                $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                $stmt->bindValue(':budget', $boosted_budget, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

                if (!$stmt->execute()) {
                    throw new Exception('Failed to apply budget boost');
                }

                $additional_message = " You received an additional " . formatMarketValue($budget_boost) . " budget boost!";
            }
            break;

        // Add more effect types as needed
        default:
            // For other effects, just store in inventory for later processing
            break;
    }

    // Commit transaction
    $db->exec('COMMIT');
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Item purchased successfully!' . $additional_message,
        'new_budget' => $new_budget + ($effect_data['amount'] ?? 0),
        'new_max_players' => $current_max_players + ($effect_data['players'] ?? 0)
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
?>