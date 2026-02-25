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
            ['type' => 'player', 'text' => 'You received a random player card!', 'icon' => '⚽'],
            ['type' => 'item', 'text' => 'You received a random item!', 'icon' => '🎁']
        ];

        // Generate 3 option with type budget
        $budget_options = [];
        while (count($budget_options) < 3) {
            $randAmount = rand(50000, 500000);
            $budget_options[] = [
                'type' => 'budget',
                'amount' => $randAmount,
                'text' => 'You received €' . number_format($randAmount) . '!',
                'icon' => '💰'
            ];
        }
        // Generate 3 option with type fans
        $fans_options = [];
        while (count($fans_options) < 3) {
            $randAmount = rand(100, 1000);
            $fans_options[] = [
                'type' => 'fans',
                'amount' => $randAmount,
                'text' => 'You gained ' . number_format($randAmount) . ' new fans!',
                'icon' => '👥'
            ];
        }

        $pool = array_merge($pool, $budget_options, $fans_options);

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
        echo json_encode(['success' => false, 'message' => 'Failed to get reward options: ' . $e->getMessage()]);
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
            // Insert random player into player_inventory for user's club
            // Get club_uuid
            $stmt = $db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            $club_uuid = $row['club_uuid'] ?? null;

            if (!$club_uuid) {
                throw new Exception('Club UUID not found');
            }

            // Get all available players
            $all_players = getDefaultPlayers();
            // Pick a random player
            $random_key = array_rand($all_players);
            $new_player = $all_players[$random_key];

            // Insert into player_inventory
            $stmt = $db->prepare('INSERT INTO player_inventory (club_uuid, player_uuid, player_data, status) VALUES (:club_uuid, :player_uuid, :player_data, "available")');
            $stmt->bindValue(':club_uuid', $club_uuid, SQLITE3_TEXT);
            $stmt->bindValue(':player_uuid', $new_player['uuid'], SQLITE3_TEXT);
            $stmt->bindValue(':player_data', json_encode($new_player), SQLITE3_TEXT);
            $result = $stmt->execute();

            if ($result) {
                $success = true;
                $message = "You unlocked " . $new_player['name'] . " (" . $new_player['position'] . " - " . $new_player['rating'] . ")!";
                $player_data = $new_player; // To return to frontend
            }
            break;

        case 'item':
            // Get a randome item from table shop_items where effect_type = 'player_pack'
            $stmt = $db->prepare('SELECT * FROM shop_items WHERE effect_type = "player_pack" ORDER BY RANDOM() LIMIT 1');
            $result = $stmt->execute();
            $pack = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            if (!$pack) {
                throw new Exception('No player pack items available');
            }

            // Insert player pack item into inventory
            $stmt = $db->prepare('INSERT INTO user_inventory (user_uuid, item_id) VALUES (:user_uuid, :item_id)');
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $stmt->bindValue(':item_id', $pack['id'], SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result) {
                $success = true;
                $message = "You received a " . $pack['name'] . "!";
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
