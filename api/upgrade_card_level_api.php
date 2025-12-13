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
    requireClubName('upgrade_card_level');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Validate input
if (!isset($_POST['player_uuid']) || !isset($_POST['player_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$player_uuid = sanitizeInput($_POST['player_uuid'], 'string');
$player_type = sanitizeInput($_POST['player_type'], 'string'); // 'team' or 'substitute'

if (empty($player_uuid) || !in_array($player_type, ['team', 'substitute'])) {
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

    // Parse team and substitutes data
    $team_data = json_decode($user_data['team'], true) ?: [];
    $substitutes_data = json_decode($user_data['substitutes'], true) ?: [];

    // Find the player
    $player_found = false;
    $player_index = -1;
    $current_player = null;

    if ($player_type === 'team') {
        for ($i = 0; $i < count($team_data); $i++) {
            if ($team_data[$i] && ($team_data[$i]['uuid'] ?? '') === $player_uuid) {
                $current_player = $team_data[$i];
                $player_index = $i;
                $player_found = true;
                break;
            }
        }
    } else {
        for ($i = 0; $i < count($substitutes_data); $i++) {
            if ($substitutes_data[$i] && ($substitutes_data[$i]['uuid'] ?? '') === $player_uuid) {
                $current_player = $substitutes_data[$i];
                $player_index = $i;
                $player_found = true;
                break;
            }
        }
    }

    if (!$player_found || !$current_player) {
        echo json_encode(['success' => false, 'message' => 'Player not found in your squad']);
        exit;
    }

    $current_card_level = $current_player['card_level'] ?? 1;
    $player_value = $current_player['value'] ?? 1000000;

    // Check maximum card level
    if ($current_card_level >= 10) {
        echo json_encode(['success' => false, 'message' => 'Player is already at maximum card level']);
        exit;
    }

    // Calculate upgrade cost
    $upgrade_cost = getCardLevelUpgradeCost($current_card_level, $player_value);

    // Check if user has enough budget
    if ($current_budget < $upgrade_cost) {
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient budget. You need ' . formatMarketValue($upgrade_cost) . ' but only have ' . formatMarketValue($current_budget)
        ]);
        exit;
    }

    // Calculate success rate based on card level (higher levels have lower success rates)
    $base_success_rate = 85; // 85% base success rate
    $level_penalty = ($current_card_level - 1) * 10; // -10% per level above 1
    $success_rate = max(30, $base_success_rate - $level_penalty); // Minimum 30% success rate

    // Generate random number to determine success
    $luck_roll = rand(1, 100);
    $upgrade_successful = $luck_roll <= $success_rate;

    // Always deduct the cost (upgrade attempt fee)
    $new_budget = $current_budget - $upgrade_cost;

    if ($upgrade_successful) {
        // Successful upgrade
        $new_card_level = $current_card_level + 1;
        $current_player['card_level'] = $new_card_level;
        $upgrade_result = 'success';
        $result_message = 'Card level upgraded successfully';
    } else {
        // Failed upgrade - no level increase but cost is still deducted
        $new_card_level = $current_card_level;
        $upgrade_result = 'failed';
        $result_message = 'Upgrade attempt failed, but you can try again';
    }

    // Update base salary if not set
    if (!isset($current_player['base_salary'])) {
        $current_player['base_salary'] = max(1000, $player_value * 0.001);
    }

    // Update the player in the appropriate array
    if ($player_type === 'team') {
        $team_data[$player_index] = $current_player;
    } else {
        $substitutes_data[$player_index] = $current_player;
    }

    // Update database
    $stmt = $db->prepare('UPDATE users SET budget = :budget, team = :team, substitutes = :substitutes WHERE id = :user_id');
    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
    $stmt->bindValue(':team', json_encode($team_data), SQLITE3_TEXT);
    $stmt->bindValue(':substitutes', json_encode($substitutes_data), SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if ($stmt->execute()) {
        $card_info = getCardLevelDisplayInfo($new_card_level);
        $benefits = getCardLevelBenefits($new_card_level);
        $new_salary = calculatePlayerSalary($current_player);

        echo json_encode([
            'success' => true,
            'upgrade_result' => $upgrade_result,
            'message' => $result_message,
            'new_budget' => $new_budget,
            'player_name' => $current_player['name'],
            'old_card_level' => $current_card_level,
            'new_card_level' => $new_card_level,
            'upgrade_cost' => $upgrade_cost,
            'success_rate' => $success_rate,
            'luck_roll' => $luck_roll,
            'card_info' => $card_info,
            'benefits' => $benefits,
            'new_salary' => $new_salary,
            'updated_player' => $current_player
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }

    $db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}