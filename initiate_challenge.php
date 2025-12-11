<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['club_name'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$opponent_id = $input['opponent_id'] ?? null;

if (!$opponent_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Opponent ID required']);
    exit;
}

try {
    $db = getDbConnection();

    // Get user's team data
    $stmt = $db->prepare('SELECT name, club_name, formation, team, budget FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Get opponent's team data
    $stmt = $db->prepare('SELECT name, club_name, formation, team, budget FROM users WHERE id = :opponent_id AND id != :user_id');
    $stmt->bindValue(':opponent_id', $opponent_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $opponent_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data || !$opponent_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User or opponent not found']);
        exit;
    }

    // Calculate challenge cost
    $opponent_team = json_decode($opponent_data['team'] ?? '[]', true);
    $opponent_team_value = calculateTeamValue($opponent_team);
    $challenge_cost = 5000000 + ($opponent_team_value * 0.005); // Base cost + 0.5% of opponent's team value

    // Validate user's team
    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $user_player_count = count(array_filter($user_team, fn($p) => $p !== null));

    if ($user_player_count < 11) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You need a complete team (11 players) to challenge other clubs! You currently have ' . $user_player_count . '/11 players.'
        ]);
        exit;
    }

    // Check if user has enough budget
    if ($user_data['budget'] < $challenge_cost) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient funds! You need ' . formatMarketValue($challenge_cost) . ' to challenge this club. Your current budget: ' . formatMarketValue($user_data['budget'])
        ]);
        exit;
    }

    // Validate opponent's team
    $opponent_player_count = count(array_filter($opponent_team, fn($p) => $p !== null));
    if ($opponent_player_count < 11) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This club doesn\'t have a complete team (11 players) and cannot be challenged. They have ' . $opponent_player_count . '/11 players.'
        ]);
        exit;
    }

    // Deduct challenge cost from user's budget
    $stmt = $db->prepare('UPDATE users SET budget = budget - :cost WHERE id = :user_id');
    $stmt->bindValue(':cost', $challenge_cost, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to process challenge payment. Please try again.']);
        exit;
    }

    // Store challenge data in session for match simulator
    $_SESSION['active_challenge'] = [
        'opponent_id' => $opponent_id,
        'challenge_cost' => $challenge_cost,
        'initiated_at' => time()
    ];

    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Challenge initiated successfully',
        'redirect_url' => "match-simulator.php?opponent={$opponent_id}"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

// Helper function to calculate team value
function calculateTeamValue($team)
{
    if (!is_array($team))
        return 0;

    $totalValue = 0;
    foreach ($team as $player) {
        if ($player && isset($player['value'])) {
            $totalValue += $player['value'];
        }
    }
    return $totalValue;
}
?>