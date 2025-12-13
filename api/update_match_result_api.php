<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';

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
    $club_level = $user_data['club_level'] ?? 1;
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

    // Award experience based on match result
    require_once '../includes/helpers.php';
    $expGain = 0;
    $expReason = '';

    if ($matchResult === 'win') {
        $expGain = 30;
        $expReason = 'Match victory against ' . $pending_reward['opponent_name'];
    } elseif ($matchResult === 'draw') {
        $expGain = 15;
        $expReason = 'Match draw against ' . $pending_reward['opponent_name'];
    } else {
        $expGain = 5;
        $expReason = 'Match participation against ' . $pending_reward['opponent_name'];
    }

    $expResult = addClubExp($_SESSION['user_id'], $expGain, $expReason);

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

    $response = [
        'success' => true,
        'match_result' => $matchResult,
        'earnings' => $earnings,
        'message' => $result_message
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
    echo json_encode(['success' => false, 'message' => 'Failed to update match result: ' . $e->getMessage()]);
}



function calculateLevelBonus($level)
{
    // Progressive bonus system: 2% per level up to 100%
    return min($level * 0.02, 1.0); // Cap at 100% bonus
}
?>