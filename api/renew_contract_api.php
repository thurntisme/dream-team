<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

// Require authentication and club name
try {
    requireClubName('renew_contract');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Validate input
if (!isset($_POST['player_uuid']) || !isset($_POST['renewal_cost'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$player_uuid = sanitizeInput($_POST['player_uuid'], 'string');
$renewal_cost = (int) $_POST['renewal_cost'];

// Validate values
if (empty($player_uuid) || $renewal_cost <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDbConnection();

    // Get current user data
    $stmt = $db->prepare('SELECT budget, team FROM user_club WHERE user_uuid = :user_uuid');
    $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $current_budget = $user_data['budget'];

    // Check if user has enough budget
    if ($current_budget < $renewal_cost) {
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient budget. You need ' . formatMarketValue($renewal_cost) . ' but only have ' . formatMarketValue($current_budget)
        ]);
        exit;
    }

    // Parse team and substitutes data
    $team_data = json_decode($user_data['team'], true) ?: [];

    // Find and update the player
    $player_found = false;
    $match_addition = 20;
    $new_matches = 0;

    // Check main team
    for ($i = 0; $i < count($team_data); $i++) {
        if ($team_data[$i] && ($team_data[$i]['uuid'] ?? '') === $player_uuid) {
            $new_matches = ($team_data[$i]['contract_matches_remaining'] ?? 0) + $match_addition;
            $team_data[$i]['contract_matches_remaining'] = $new_matches;
            $player_found = true;
            $player_name = $team_data[$i]['name'] ?? 'Unknown Player';
            break;
        }
    }

    if (!$player_found) {
        echo json_encode(['success' => false, 'message' => 'Player not found in your squad']);
        exit;
    }

    // Calculate new budget
    $new_budget = $current_budget - $renewal_cost;

    // Update database
    $stmt = $db->prepare('UPDATE user_club SET budget = :budget, team = :team WHERE user_uuid = :user_uuid');
    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
    $stmt->bindValue(':team', json_encode($team_data), SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);

    if ($stmt->execute()) {
        // Award experience for contract renewal
        $expResult = addClubExp($_SESSION['user_uuid'], 8, 'Contract renewed for ' . $player_name, $db);

        $response = [
            'success' => true,
            'message' => 'Contract renewed successfully',
            'new_budget' => $new_budget,
            'player_name' => $player_name,
            'matches_added' => $new_matches,
            'cost' => $renewal_cost
        ];

        // Add level up information if applicable
        if ($expResult['success'] && $expResult['leveled_up']) {
            $response['level_up'] = [
                'new_level' => $expResult['new_level'],
                'levels_gained' => $expResult['levels_gained']
            ];
        }

        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }

    $db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>