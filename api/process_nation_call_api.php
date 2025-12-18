<?php
session_start();
require_once '../config/config.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');



$user_id = $_SESSION['user_id'];

try {
    $db = getDbConnection();

    // Database migrations for nation calls feature (ensure tables exist)
    try {
        // Add matches_played column to users table if it doesn't exist
        $db->exec('ALTER TABLE users ADD COLUMN matches_played INTEGER DEFAULT 0');
    } catch (Exception $e) {
        // Column already exists, ignore error
    }

    // Database tables are now created in install.php

    // Get user data
    $stmt = $db->prepare('SELECT matches_played, team, substitutes FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $userData = $result->fetchArray(SQLITE3_ASSOC);

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $matchesPlayed = $userData['matches_played'] ?? 0;

    // Check if nation calls should be triggered (every 8 matches)
    if ($matchesPlayed <= 0 || $matchesPlayed % 8 !== 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Nation calls are only available every 8 matches. You have played ' . $matchesPlayed . ' matches.'
        ]);
        exit;
    }

    // Check if nation call was already processed recently
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM nation_calls WHERE user_id = :user_id AND call_date > datetime("now", "-1 day")');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $recentCalls = $result->fetchArray(SQLITE3_ASSOC);

    if ($recentCalls['count'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Nation call was already processed recently. Please wait before processing another one.'
        ]);
        exit;
    }

    // Get all players
    $team = json_decode($userData['team'] ?? '[]', true) ?: [];
    $substitutes = json_decode($userData['substitutes'] ?? '[]', true) ?: [];
    $allPlayers = array_merge(array_filter($team), array_filter($substitutes));

    if (empty($allPlayers)) {
        echo json_encode(['success' => false, 'message' => 'No players available for nation call']);
        exit;
    }

    // Select best performing players for nation calls
    $calledPlayers = selectPlayersForNationCall($allPlayers);

    if (empty($calledPlayers)) {
        echo json_encode(['success' => false, 'message' => 'No players met the performance criteria for nation call']);
        exit;
    }

    // Calculate budget reward
    $totalReward = 0;
    $playersWithRewards = [];

    foreach ($calledPlayers as $player) {
        $reward = calculateNationCallReward($player);
        $totalReward += $reward;

        $player['reward'] = $reward;
        $playersWithRewards[] = $player;
    }

    // Update user budget
    $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :id');
    $stmt->bindValue(':reward', $totalReward, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Save nation call record
    $saved = saveNationCallRecord($db, $user_id, $playersWithRewards, $totalReward);

    if (!$saved) {
        // Rollback budget update if saving failed
        $stmt = $db->prepare('UPDATE users SET budget = budget - :reward WHERE id = :id');
        $stmt->bindValue(':reward', $totalReward, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save nation call record']);
        exit;
    }

    // Nation call processed successfully - the 24-hour check will prevent duplicates

    $db->close();

    echo json_encode([
        'success' => true,
        'called_players' => $playersWithRewards,
        'total_reward' => $totalReward,
        'matches_milestone' => $matchesPlayed,
        'message' => count($playersWithRewards) . ' player' . (count($playersWithRewards) > 1 ? 's' : '') . ' called up for international duty'
    ]);

} catch (Exception $e) {
    error_log("Process nation call API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>