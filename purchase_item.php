<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

header('Content-Type: application/json');

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['club_name'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$item_id = $input['item_id'] ?? null;
$item_name = $input['item_name'] ?? null;
$item_price = $input['item_price'] ?? null;

// Validate input
if (!$item_id || !$item_name || !$item_price) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate price
if ($item_price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item price']);
    exit;
}

try {
    $db = getDbConnection();

    // Get user's current budget
    $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Check if user has enough budget
    if ($user['budget'] < $item_price) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Insufficient budget']);
        exit;
    }

    // Get item details to verify price and get duration
    $stmt = $db->prepare('SELECT * FROM shop_items WHERE id = :item_id');
    $stmt->bindValue(':item_id', $item_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $item = $result->fetchArray(SQLITE3_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }

    // Verify price matches
    if ($item['price'] != $item_price) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Price mismatch']);
        exit;
    }

    // Check if user already has this item (for certain types)
    if (in_array($item['effect_type'], ['permanent_boost', 'formation_unlock'])) {
        $stmt = $db->prepare('SELECT id FROM user_items WHERE user_id = :user_id AND item_id = :item_id AND is_active = 1');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':item_id', $item_id, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result->fetchArray()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You already own this item']);
            exit;
        }
    }

    // Begin transaction
    $db->exec('BEGIN TRANSACTION');

    try {
        // Deduct budget from user
        $new_budget = $user['budget'] - $item_price;
        $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
        $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update user budget');
        }

        // Calculate expiration date if item has duration
        $expires_at = null;
        if ($item['duration'] > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $item['duration'] . ' days'));
        }

        // Add item to user's inventory
        $stmt = $db->prepare('INSERT INTO user_items (user_id, item_id, expires_at, is_active) VALUES (:user_id, :item_id, :expires_at, 1)');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':item_id', $item_id, SQLITE3_INTEGER);
        $stmt->bindValue(':expires_at', $expires_at, SQLITE3_TEXT);

        if (!$stmt->execute()) {
            throw new Exception('Failed to add item to inventory');
        }

        // Apply immediate effects based on item type
        $effect_value = json_decode($item['effect_value'], true);

        switch ($item['effect_type']) {
            case 'budget_boost':
                // Add additional budget immediately
                $bonus_budget = $effect_value['amount'] ?? 0;
                $final_budget = $new_budget + $bonus_budget;

                $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                $stmt->bindValue(':budget', $final_budget, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();
                break;

            case 'player_boost':
                // Boost all players (this would be applied when loading team)
                // For now, we just record the purchase
                break;

            case 'permanent_boost':
                // Apply permanent boost to specific position players
                $position = $effect_value['position'] ?? '';
                $rating_boost = $effect_value['rating'] ?? 0;

                if ($position && $rating_boost > 0) {
                    // Get user's team
                    $stmt = $db->prepare('SELECT team FROM users WHERE id = :user_id');
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user_data = $result->fetchArray(SQLITE3_ASSOC);

                    if ($user_data && $user_data['team']) {
                        $team = json_decode($user_data['team'], true);

                        // Apply boost to matching position players
                        foreach ($team as $index => $player) {
                            if ($player && isset($player['position']) && $player['position'] === $position) {
                                $team[$index]['rating'] = ($player['rating'] ?? 0) + $rating_boost;
                                // Recalculate value based on new rating
                                $team[$index]['value'] = calculatePlayerValue($team[$index]);
                            }
                        }

                        // Update team in database
                        $stmt = $db->prepare('UPDATE users SET team = :team WHERE id = :user_id');
                        $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
                        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                        $stmt->execute();
                    }
                }
                break;
        }

        // Commit transaction
        $db->exec('COMMIT');

        echo json_encode([
            'success' => true,
            'message' => 'Item purchased successfully',
            'new_budget' => $new_budget,
            'item_name' => $item['name']
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $db->exec('ROLLBACK');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Purchase failed: ' . $e->getMessage()]);
        exit;
    }

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

// Helper function to calculate player value based on rating
function calculatePlayerValue($player)
{
    $rating = $player['rating'] ?? 50;
    $position = $player['position'] ?? 'CM';

    // Base value calculation
    $base_value = $rating * 100000;

    // Position multipliers
    $position_multipliers = [
        'GK' => 0.8,
        'CB' => 0.9,
        'LB' => 0.95,
        'RB' => 0.95,
        'CDM' => 1.0,
        'CM' => 1.0,
        'CAM' => 1.1,
        'LW' => 1.15,
        'RW' => 1.15,
        'ST' => 1.2
    ];

    $multiplier = $position_multipliers[$position] ?? 1.0;

    return (int) ($base_value * $multiplier);
}
?>