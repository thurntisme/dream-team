<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

// Get POST data
$formation = $_POST['formation'] ?? '';
$team = $_POST['team'] ?? '';
$substitutes = $_POST['substitutes'] ?? '[]';
$player_cost = (int) ($_POST['player_cost'] ?? 0);
$player_name = $_POST['player_name'] ?? '';

if (empty($formation) || empty($team)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

try {
    $db = getDbConnection();

    // Start transaction
    $db->exec('BEGIN TRANSACTION');

    // Get user's current budget
    $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data) {
        throw new Exception('User not found');
    }

    $current_budget = $user_data['budget'];

    // Check if user has enough budget
    if ($current_budget < $player_cost) {
        throw new Exception('Insufficient budget to purchase this player');
    }

    // Calculate new budget
    $new_budget = $current_budget - $player_cost;

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

    // Commit transaction
    $db->exec('COMMIT');
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Player purchased successfully!',
        'new_budget' => $new_budget,
        'player_name' => $player_name,
        'player_cost' => $player_cost
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