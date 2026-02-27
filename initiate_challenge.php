<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}



if (!hasClubName()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Club name required. Please complete your profile.']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$opponent_uuid = $input['opponent_uuid'] ?? null;

if (!$opponent_uuid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Opponent UUID required']);
    exit;
}

try {
    $db = getDbConnection();

    // Get user's team data via user_uuid
    $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
    $stmt->bindValue(':uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Get opponent's team data via opponent_uuid
    $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :opponent_uuid AND u.uuid != :user_uuid');
    $stmt->bindValue(':opponent_uuid', $opponent_uuid, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);
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

    // Deduct challenge cost from user's budget in user_club
    $stmt = $db->prepare('UPDATE user_club SET budget = budget - :cost WHERE user_uuid = :uuid');
    $stmt->bindValue(':cost', $challenge_cost, SQLITE3_INTEGER);
    $stmt->bindValue(':uuid', $_SESSION['user_uuid'], SQLITE3_TEXT);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to process challenge payment. Please try again.']);
        exit;
    }

    // Store challenge data in session for match simulator
    $_SESSION['active_challenge'] = [
        'opponent_uuid' => $opponent_uuid,
        'challenge_cost' => $challenge_cost,
        'initiated_at' => time()
    ];

    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Challenge initiated successfully',
        'redirect_url' => "online_match.php?opponent={$opponent_uuid}"
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