<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

header('Content-Type: application/json');

// Check if user is logged in and has pending reward
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if (!isset($_SESSION['pending_reward'])) {
    echo json_encode(['success' => false, 'message' => 'No pending reward found']);
    exit;
}

try {
    $db = getDbConnection();

    $pending_reward = $_SESSION['pending_reward'];
    $user_id = $_SESSION['user_id'];

    // First deduct the challenge cost
    $stmt = $db->prepare('UPDATE users SET budget = budget - :challenge_cost WHERE id = :user_id');
    $stmt->bindValue(':challenge_cost', $pending_reward['challenge_cost'], SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Then add the reward if any
    if ($pending_reward['amount'] > 0) {
        $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :user_id');
        $stmt->bindValue(':reward', $pending_reward['amount'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    // Get updated budget
    $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);
    $new_budget = $user_data['budget'];

    $db->close();

    // Clear the pending reward
    unset($_SESSION['pending_reward']);

    // Calculate net result
    $net_result = $pending_reward['amount'] - $pending_reward['challenge_cost'];

    echo json_encode([
        'success' => true,
        'message' => 'Reward processed successfully!',
        'new_budget' => formatMarketValue($new_budget),
        'net_result' => formatMarketValue($net_result),
        'match_result' => $pending_reward['match_result']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to process reward: ' . $e->getMessage()]);
}
?>