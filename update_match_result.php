<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

header('Content-Type: application/json');

// Check if user is logged in and has pending reward
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_reward'])) {
    echo json_encode(['success' => false, 'message' => 'No pending reward found']);
    exit;
}

// Get the live match result from POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['userGoals']) || !isset($input['opponentGoals'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid match data']);
    exit;
}

$userGoals = intval($input['userGoals']);
$opponentGoals = intval($input['opponentGoals']);

// Determine match result
if ($userGoals > $opponentGoals) {
    $matchResult = 'win';
} elseif ($userGoals < $opponentGoals) {
    $matchResult = 'loss';
} else {
    $matchResult = 'draw';
}

try {
    $db = getDbConnection();

    // Get user data for club level calculation
    $stmt = $db->prepare('SELECT team FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $club_level = calculateClubLevel($user_team);
    $level_bonus = calculateLevelBonus($club_level);

    $db->close();

    // Get current pending reward data
    $pending_reward = $_SESSION['pending_reward'];
    $challenge_cost = $pending_reward['challenge_cost'];

    // Calculate earnings based on live result
    $earnings = 0;
    $bonus_earnings = 0;
    $result_message = '';

    if ($matchResult === 'win') {
        // Calculate win reward (150% of challenge cost)
        $base_earnings = $challenge_cost * 1.5;
        $bonus_earnings = $base_earnings * $level_bonus;
        $earnings = $base_earnings + $bonus_earnings;

        $result_message = 'Victory! You can earn ' . formatMarketValue($base_earnings) . ' in prize money';
        if ($bonus_earnings > 0) {
            $result_message .= ' + ' . formatMarketValue($bonus_earnings) . ' club level bonus (Level ' . $club_level . ')!';
        } else {
            $result_message .= '!';
        }
    } elseif ($matchResult === 'draw') {
        // Calculate draw reward (80% refund)
        $base_earnings = $challenge_cost * 0.8;
        $bonus_earnings = $base_earnings * ($level_bonus * 0.5);
        $earnings = $base_earnings + $bonus_earnings;

        $result_message = 'Draw! You can receive ' . formatMarketValue($base_earnings) . ' (80% refund)';
        if ($bonus_earnings > 0) {
            $result_message .= ' + ' . formatMarketValue($bonus_earnings) . ' level bonus.';
        } else {
            $result_message .= '.';
        }
    } else {
        // Calculate loss consolation
        if ($club_level >= 2) {
            $consolation_rate = 0.1 + ($club_level * 0.05);
            $earnings = $challenge_cost * $consolation_rate;

            $result_message = 'Defeat! You lost the challenge fee but can receive ' . formatMarketValue($earnings) . ' consolation bonus (' . round($consolation_rate * 100) . '% - Level ' . $club_level . ').';
        } else {
            $earnings = $challenge_cost * 0.1;

            $result_message = 'Defeat! You lost the challenge fee but can receive ' . formatMarketValue($earnings) . ' consolation bonus (10% - Level ' . $club_level . ').';
        }
    }

    // Update session with live result
    $_SESSION['pending_reward'] = [
        'amount' => $earnings,
        'challenge_cost' => $challenge_cost,
        'match_result' => $matchResult,
        'opponent_name' => $pending_reward['opponent_name'],
        'financial_details' => [
            'earnings' => $earnings,
            'base_earnings' => $matchResult === 'win' ? $challenge_cost * 1.5 : ($matchResult === 'draw' ? $challenge_cost * 0.8 : $earnings),
            'bonus_earnings' => $bonus_earnings,
            'club_level' => $club_level,
            'challenge_cost' => $challenge_cost,
            'message' => $result_message,
            'pending' => true
        ]
    ];

    echo json_encode([
        'success' => true,
        'match_result' => $matchResult,
        'earnings' => $earnings,
        'message' => $result_message
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update match result: ' . $e->getMessage()]);
}

// Club level calculation functions (copied from other files for consistency)
function calculateClubLevel($team)
{
    if (!is_array($team))
        return 1;

    $total_rating = 0;
    $player_count = 0;
    $total_value = 0;

    foreach ($team as $player) {
        if ($player && isset($player['rating']) && isset($player['value'])) {
            $total_rating += $player['rating'];
            $total_value += $player['value'];
            $player_count++;
        }
    }

    if ($player_count === 0)
        return 1;

    $avg_rating = $total_rating / $player_count;
    $avg_value = $total_value / $player_count;

    // Level calculation based on average rating and value
    if ($avg_rating >= 85 && $avg_value >= 50000000) {
        return 5; // Elite
    } elseif ($avg_rating >= 80 && $avg_value >= 30000000) {
        return 4; // Professional
    } elseif ($avg_rating >= 75 && $avg_value >= 15000000) {
        return 3; // Semi-Professional
    } elseif ($avg_rating >= 70 && $avg_value >= 5000000) {
        return 2; // Amateur
    } else {
        return 1; // Beginner
    }
}

function calculateLevelBonus($level)
{
    switch ($level) {
        case 5:
            return 0.25; // 25%
        case 4:
            return 0.20; // 20%
        case 3:
            return 0.15; // 15%
        case 2:
            return 0.10; // 10%
        case 1:
        default:
            return 0.0; // 0%
    }
}
?>