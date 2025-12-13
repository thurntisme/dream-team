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
if (!isset($_POST['player_uuid']) || !isset($_POST['renewal_cost']) || !isset($_POST['new_matches'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$player_uuid = sanitizeInput($_POST['player_uuid'], 'string');
$renewal_cost = (int) $_POST['renewal_cost'];
$new_matches = (int) $_POST['new_matches'];

// Validate values
if (empty($player_uuid) || $renewal_cost <= 0 || $new_matches <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDbConnection();

    // Get current user data
    $stmt = $db->prepare('SELECT budget, team, substitutes FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
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
    $substitutes_data = json_decode($user_data['substitutes'], true) ?: [];

    // Find and update the player
    $player_found = false;

    // Check main team
    for ($i = 0; $i < count($team_data); $i++) {
        if ($team_data[$i] && ($team_data[$i]['uuid'] ?? '') === $player_uuid) {
            $team_data[$i]['contract_matches_remaining'] = ($team_data[$i]['contract_matches_remaining'] ?? 0) + $new_matches;
            $player_found = true;
            $player_name = $team_data[$i]['name'] ?? 'Unknown Player';
            break;
        }
    }

    // Check substitutes if not found in main team
    if (!$player_found) {
        for ($i = 0; $i < count($substitutes_data); $i++) {
            if ($substitutes_data[$i] && ($substitutes_data[$i]['uuid'] ?? '') === $player_uuid) {
                $substitutes_data[$i]['contract_matches_remaining'] = ($substitutes_data[$i]['contract_matches_remaining'] ?? 0) + $new_matches;
                $player_found = true;
                $player_name = $substitutes_data[$i]['name'] ?? 'Unknown Player';
                break;
            }
        }
    }

    if (!$player_found) {
        echo json_encode(['success' => false, 'message' => 'Player not found in your squad']);
        exit;
    }

    // Calculate new budget
    $new_budget = $current_budget - $renewal_cost;

    // Update database
    $stmt = $db->prepare('UPDATE users SET budget = :budget, team = :team, substitutes = :substitutes WHERE id = :user_id');
    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
    $stmt->bindValue(':team', json_encode($team_data), SQLITE3_TEXT);
    $stmt->bindValue(':substitutes', json_encode($substitutes_data), SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Contract renewed successfully',
            'new_budget' => $new_budget,
            'player_name' => $player_name,
            'matches_added' => $new_matches,
            'cost' => $renewal_cost
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }

    $db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>