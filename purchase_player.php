<?php
session_start();

require_once 'config.php';
require_once 'constants.php';
require_once 'helpers.php';

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

// Get JSON input for market purchases
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Fallback to POST data for team management purchases
    $formation = $_POST['formation'] ?? '';
    $team = $_POST['team'] ?? '';
    $substitutes = $_POST['substitutes'] ?? '[]';
    $player_cost = (int) ($_POST['player_cost'] ?? 0);
    $player_uuid = $_POST['player_uuid'] ?? '';

    if (empty($formation) || empty($team)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $is_market_purchase = false;
} else {
    // Handle market purchase
    $player_index = $data['player_index'] ?? null;
    $player_uuid = $data['player_uuid'] ?? '';
    $player_data = $data['player_data'] ?? null;
    $purchase_amount = (int) ($data['purchase_amount'] ?? 0);

    if ($player_index === null || empty($player_uuid) || !$player_data || $purchase_amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid market purchase data']);
        exit;
    }

    $is_market_purchase = true;
}

try {
    $db = getDbConnection();

    // Start transaction
    $db->exec('BEGIN TRANSACTION');

    // Get user's current data
    $stmt = $db->prepare('SELECT budget, team, substitutes, max_players FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data) {
        throw new Exception('User not found');
    }

    $current_budget = $user_data['budget'];

    if ($is_market_purchase) {
        // Handle market purchase
        $cost = $purchase_amount;

        // Check if user has enough budget
        if ($current_budget < $cost) {
            throw new Exception('Insufficient budget to purchase this player');
        }

        // Check if player already exists in team or substitutes
        $current_team = json_decode($user_data['team'] ?? '[]', true) ?: [];
        $current_substitutes = json_decode($user_data['substitutes'] ?? '[]', true) ?: [];

        foreach ($current_team as $existing_player) {
            if (
                $existing_player && isset($existing_player['uuid']) &&
                $existing_player['uuid'] === $player_uuid
            ) {
                throw new Exception('You already have this player in your team');
            }
        }

        foreach ($current_substitutes as $existing_player) {
            if (
                $existing_player && isset($existing_player['uuid']) &&
                $existing_player['uuid'] === $player_uuid
            ) {
                throw new Exception('You already have this player in your substitutes');
            }
        }

        // Check if player already exists in inventory
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM player_inventory WHERE user_id = :user_id AND player_uuid = :player_uuid AND status = "available"');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':player_uuid', $player_data['uuid'] ?? '', SQLITE3_TEXT);
        $result = $stmt->execute();
        $inventory_check = $result->fetchArray(SQLITE3_ASSOC);

        if ($inventory_check['count'] > 0) {
            throw new Exception('You already have this player in your inventory');
        }

        // Calculate new budget
        $new_budget = $current_budget - $cost;

        // Initialize player condition (fitness and form)
        $player_data = initializePlayerCondition($player_data);

        // Add player to inventory instead of directly to team
        $stmt = $db->prepare('INSERT INTO player_inventory (user_id, player_uuid, player_data, purchase_price) VALUES (:user_id, :player_uuid, :player_data, :purchase_price)');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':player_uuid', $player_data['uuid'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':player_data', json_encode($player_data), SQLITE3_TEXT);
        $stmt->bindValue(':purchase_price', $cost, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            throw new Exception('Failed to add player to inventory: ' . $db->lastErrorMsg());
        }

        // Update user's budget only
        $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
        $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update budget: ' . $db->lastErrorMsg());
        }

        $response_message = 'Player purchased successfully and added to your inventory!';
        $response_cost = $cost;

    } else {
        // Handle team management purchase (existing functionality)
        $cost = $player_cost;

        // Check if user has enough budget
        if ($current_budget < $cost) {
            throw new Exception('Insufficient budget to purchase this player');
        }

        // Calculate new budget
        $new_budget = $current_budget - $cost;

        // Update user's team, substitutes, and budget
        $stmt = $db->prepare('UPDATE users SET formation = :formation, team = :team, substitutes = :substitutes, budget = :budget WHERE id = :user_id');
        $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
        $stmt->bindValue(':team', $team, SQLITE3_TEXT);
        $stmt->bindValue(':substitutes', $substitutes, SQLITE3_TEXT);
        $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update team and budget: ' . $db->lastErrorMsg());
        }

        $response_message = 'Player purchased successfully!';
        $response_cost = $cost;
    }

    // Commit transaction
    $db->exec('COMMIT');
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => $response_message,
        'new_budget' => $new_budget,
        'player_name' => $player_data['name'] ?? 'Unknown Player',
        'player_cost' => $response_cost
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