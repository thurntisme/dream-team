<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/auth_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Support GET (reward options) and POST (apply selected reward)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    try {
        $user_uuid = $_SESSION['user_uuid'];
        $match_uuid = isset($_GET['match_uuid']) ? $_GET['match_uuid'] : null;
        if (!$match_uuid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Match ID is required']);
            exit;
        }
        $claimed_key = "mystery_box_claimed_{$match_uuid}_{$user_uuid}";
        if (isset($_SESSION[$claimed_key]) && $_SESSION[$claimed_key] === true) {
            echo json_encode(['success' => true, 'claimed' => true, 'options' => []]);
            exit;
        }

        // Generate reward options server-side
        $pool = [
            ['type' => 'budget', 'amount' => 150000, 'text' => 'You received €150,000!', 'icon' => '💰'],
            ['type' => 'budget', 'amount' => 250000, 'text' => 'You received €250,000!', 'icon' => '💰'],
            ['type' => 'budget', 'amount' => 350000, 'text' => 'You received €350,000!', 'icon' => '💰'],
            ['type' => 'player', 'text' => 'You received a random player card!', 'icon' => '⚽'],
            ['type' => 'item', 'text' => 'You received a training boost item!', 'icon' => '🏃'],
            ['type' => 'budget', 'amount' => 100000, 'text' => 'You received €100,000!', 'icon' => '💰'],
            ['type' => 'fans', 'amount' => 300, 'text' => 'You gained 300 new fans!', 'icon' => '👥']
        ];
        // Shuffle and take 3
        $shuffled = $pool;
        shuffle($shuffled);
        $options = array_slice($shuffled, 0, 3);

        // Store in session to allow server-side validation later
        $opts_key = "mystery_box_options_{$match_uuid}_{$user_uuid}";
        $_SESSION[$opts_key] = $options;

        echo json_encode(['success' => true, 'claimed' => false, 'options' => $options]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to get reward options']);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['reward'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$reward = $input['reward'];
$user_uuid = $_SESSION['user_uuid'];

try {
    $db = getDbConnection();

    // Check if user has already claimed mystery box reward for this match
    $match_uuid = $input['match_uuid'] ?? null;
    if (!$match_uuid) {
        throw new Exception('Match ID is required');
    }

    $session_key = "mystery_box_claimed_{$match_uuid}_{$user_uuid}";
    if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true) {
        throw new Exception('Mystery box reward already claimed for this match');
    }

    // Validate reward data
    if (!isset($reward['type']) || !isset($reward['text'])) {
        throw new Exception('Invalid reward data');
    }
    // Optional: ensure selected reward matches one of server-provided options
    $opts_key = "mystery_box_options_{$match_uuid}_{$user_uuid}";
    if (isset($_SESSION[$opts_key]) && is_array($_SESSION[$opts_key])) {
        $allowed = false;
        foreach ($_SESSION[$opts_key] as $opt) {
            if ($reward['type'] === ($opt['type'] ?? null)) {
                // For budget/fans, also match amount
                if (in_array($reward['type'], ['budget', 'fans'], true)) {
                    if (intval($reward['amount'] ?? 0) === intval($opt['amount'] ?? -1)) {
                        $allowed = true;
                        break;
                    }
                } else {
                    $allowed = true;
                    break;
                }
            }
        }
        if (!$allowed) {
            throw new Exception('Selected reward not permitted');
        }
    }

    $success = false;
    $message = '';

    switch ($reward['type']) {
        case 'budget':
            if (!isset($reward['amount']) || !is_numeric($reward['amount'])) {
                throw new Exception('Invalid budget amount');
            }

            $amount = intval($reward['amount']);
            if ($amount <= 0 || $amount > 2000000) { // Max 2M for safety
                throw new Exception('Invalid budget amount');
            }

            // Update user budget
            $stmt = $db->prepare('UPDATE user_club SET budget = budget + :amount WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result) {
                $success = true;
                $message = "Added €" . number_format($amount) . " to your budget!";
            }
            break;

        case 'fans':
            if (!isset($reward['amount']) || !is_numeric($reward['amount'])) {
                throw new Exception('Invalid fan amount');
            }

            $amount = intval($reward['amount']);
            if ($amount <= 0 || $amount > 5000) { // Max 5K fans for safety
                throw new Exception('Invalid fan amount');
            }

            // Update user fans
            $stmt = $db->prepare('UPDATE user_club SET fans = fans + :amount WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result) {
                $success = true;
                $message = "Gained " . number_format($amount) . " new fans!";
            }
            break;

        case 'player':
            // Get user's current team from user_club
            $stmt = $db->prepare('SELECT team, max_players FROM user_club WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user_info = $result->fetchArray(SQLITE3_ASSOC);

            $full_team = json_decode($user_info['team'] ?? '[]', true) ?: [];
            $max_players = $user_info['max_players'] ?? 25; // Default to 25 if not set

            // Calculate current total players
            $current_players_count = count(array_filter($full_team));

            // Check if user has space
            if ($current_players_count >= $max_players) {
                // No space, give budget equivalent instead
                $equivalent_budget = 300000;

                $stmt = $db->prepare('UPDATE user_club SET budget = budget + :amount WHERE user_uuid = :user_uuid');
                $stmt->bindValue(':amount', $equivalent_budget, SQLITE3_INTEGER);
                $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
                $result = $stmt->execute();

                if ($result) {
                    $success = true;
                    $message = "Squad full! Received €" . number_format($equivalent_budget) . " instead of player card.";
                }
            } else {
                // Get all available players
                $all_players = getDefaultPlayers();

                // Get owned player names (to avoid duplicates)
                $owned_names = array_map(function($p) { return $p['name'] ?? ''; }, array_filter($full_team));

                // Filter available players
                $available_players = array_filter($all_players, function ($p) use ($owned_names) {
                    return !in_array($p['name'], $owned_names);
                });

                if (empty($available_players)) {
                    // User owns all players (unlikely), give budget
                    $equivalent_budget = 500000;

                    $stmt = $db->prepare('UPDATE user_club SET budget = budget + :amount WHERE user_uuid = :user_uuid');
                    $stmt->bindValue(':amount', $equivalent_budget, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
                    $result = $stmt->execute();

                    if ($result) {
                        $success = true;
                        $message = "You own all players! Received €" . number_format($equivalent_budget) . " bonus.";
                    }
                } else {
                    // Pick a random player
                    $random_key = array_rand($available_players);
                    $new_player = $available_players[$random_key];

                    // Generate UUID and initialize stats if not present
                    if (!isset($new_player['uuid'])) {
                        $new_player['uuid'] = uniqid('player_');
                    }
                    if (!isset($new_player['fitness'])) {
                        $new_player['fitness'] = 100;
                    }

                    // Add to team (append)
                    $full_team[] = $new_player;

                    // Update database
                    $stmt = $db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = :user_uuid');
                    $stmt->bindValue(':team', json_encode($full_team), SQLITE3_TEXT);
                    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
                    $result = $stmt->execute();

                    if ($result) {
                        $success = true;
                        $message = "You unlocked " . $new_player['name'] . " (" . $new_player['position'] . " - " . $new_player['rating'] . ")!";
                        $player_data = $new_player; // To return to frontend
                    }
                }
            }
            break;

        case 'item':
            // For item rewards, we could add training items
            // For now, we'll give a budget equivalent
            $equivalent_budget = 150000;

            $stmt = $db->prepare('UPDATE user_club SET budget = budget + :amount WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':amount', $equivalent_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result) {
                $success = true;
                $message = "Received a training item worth €" . number_format($equivalent_budget) . "!";
            }
            break;

        default:
            throw new Exception('Unknown reward type');
    }

    if (!$success) {
        throw new Exception('Failed to apply reward');
    }

    // Mark mystery box as claimed for this match
    $_SESSION[$session_key] = true;

    // Get updated user data
    $stmt = $db->prepare('SELECT budget, fans FROM user_club WHERE user_uuid = :user_uuid');
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    $db->close();

    $response = [
        'success' => true,
        'message' => $message,
        'updated_budget' => $user_data['budget'],
        'updated_fans' => $user_data['fans']
    ];

    if (isset($player_data)) {
        $response['player_data'] = $player_data;
    }

    echo json_encode($response);
} catch (Exception $e) {
    error_log("Mystery box reward error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to apply reward: ' . $e->getMessage()
    ]);
}
