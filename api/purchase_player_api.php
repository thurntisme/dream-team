<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');



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

    if ($player_index === null || (!$player_uuid && (!$player_data || empty($player_data['uuid'] ?? ''))) || !$player_data || $purchase_amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid market purchase data']);
        exit;
    }

    $is_market_purchase = true;
}

try {
    $db = getDbConnection();

    // Start transaction
    $db->exec('START TRANSACTION');

    $stmt = $db->prepare('SELECT budget, team, max_players FROM user_club WHERE user_uuid = :user_uuid');
    $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
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
        $current_substitutes = [];

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

        // Resolve and normalize player UUID to 16-char (strip hyphens)
        $resolved_uuid = $player_data['uuid'] ?? $player_uuid;
        $resolved_uuid = substr(str_replace('-', '', $resolved_uuid), 0, 16);
        if (!$resolved_uuid) {
            throw new Exception('Player UUID missing');
        }
        // Ensure player_data carries normalized uuid
        $player_data['uuid'] = $resolved_uuid;

        // Check if player already exists in inventory
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM player_inventory WHERE club_uuid = (SELECT club_uuid FROM user_club WHERE user_uuid = :user_uuid) AND player_uuid = :player_uuid AND status = "available"');
        $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
        $stmt->bindValue(':player_uuid', $resolved_uuid, SQLITE3_TEXT);
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
        // Resolve or initialize club_uuid
        $stmtClub = $db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :uuid');
        if ($stmtClub === false) {
            throw new Exception('Failed to resolve club: ' . $db->lastErrorMsg());
        }
        $stmtClub->bindValue(':uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
        $resClub = $stmtClub->execute();
        $rowClub = $resClub ? $resClub->fetchArray(SQLITE3_ASSOC) : null;
        $clubUuidVal = $rowClub['club_uuid'] ?? '';
        if ($clubUuidVal === '' || $clubUuidVal === null) {
            $clubUuidVal = generateUUID();
            $stmtSetClub = $db->prepare('UPDATE user_club SET club_uuid = :club_uuid WHERE user_uuid = :user_uuid');
            if ($stmtSetClub === false) {
                throw new Exception('Failed to initialize club: ' . $db->lastErrorMsg());
            }
            $stmtSetClub->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
            $stmtSetClub->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
            if (!$stmtSetClub->execute()) {
                throw new Exception('Failed to initialize club: ' . $db->lastErrorMsg());
            }
        }

        $stmt = $db->prepare('INSERT INTO player_inventory (club_uuid, player_uuid, player_data, purchase_price) VALUES (:club_uuid, :player_uuid, :player_data, :purchase_price)');
        if ($stmt === false) {
            throw new Exception('Failed to prepare inventory insert: ' . $db->lastErrorMsg());
        }
        $stmt->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
        $stmt->bindValue(':player_uuid', $resolved_uuid, SQLITE3_TEXT);
        $stmt->bindValue(':player_data', json_encode($player_data), SQLITE3_TEXT);
        $stmt->bindValue(':purchase_price', $cost, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            throw new Exception('Failed to add player to inventory: ' . $db->lastErrorMsg());
        }

        // Update club budget
        $stmt = $db->prepare('UPDATE user_club SET budget = :budget WHERE user_uuid = :user_uuid');
        $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
        $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);

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

        // Update club formation, team, and budget (no substitutes column)
        $stmt = $db->prepare('UPDATE user_club SET formation = :formation, team = :team, budget = :budget WHERE user_uuid = :user_uuid');
        $stmt->bindValue(':formation', $formation, SQLITE3_TEXT);
        $stmt->bindValue(':team', $team, SQLITE3_TEXT);
        $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
        $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update team and budget: ' . $db->lastErrorMsg());
        }

        $response_message = 'Player purchased successfully!';
        $response_cost = $cost;
    }

    // Award experience for player purchase
    $expResult = addClubExp($_SESSION['user_id'], 15, 'Player purchased: ' . ($player_data['name'] ?? 'Unknown Player'), $db);

    // Commit transaction
    $db->exec('COMMIT');
    $db->close();

    $response = [
        'success' => true,
        'message' => $response_message,
        'new_budget' => $new_budget,
        'player_name' => $player_data['name'] ?? 'Unknown Player',
        'player_cost' => $response_cost
    ];

    // Add level up information if applicable
    if ($expResult['success'] && $expResult['leveled_up']) {
        $response['level_up'] = [
            'new_level' => $expResult['new_level'],
            'levels_gained' => $expResult['levels_gained']
        ];
    }

    echo json_encode($response);

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
