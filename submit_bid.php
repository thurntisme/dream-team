<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';

header('Content-Type: application/json');

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!hasClubName()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Club name required. Please complete your profile.']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$owner_id = $input['owner_id'] ?? null;
$player_index = $input['player_index'] ?? null;
$player_uuid = $input['player_uuid'] ?? null;
$player_data = $input['player_data'] ?? null;
$bid_amount = $input['bid_amount'] ?? null;

// Validate input
if (!$owner_id || $player_index === null || !$player_uuid || !$player_data || !$bid_amount) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate bid amount
if ($bid_amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid bid amount']);
    exit;
}

// Cannot bid on own players
if ($owner_id == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot bid on your own players']);
    exit;
}

try {
    $db = getDbConnection();

    // Get bidder's budget
    $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $bidder = $result->fetchArray(SQLITE3_ASSOC);

    if (!$bidder) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bidder not found']);
        exit;
    }

    // Check if bidder has enough budget
    if ($bidder['budget'] < $bid_amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Insufficient budget']);
        exit;
    }

    // Get owner's team to verify player exists
    $stmt = $db->prepare('SELECT team FROM users WHERE id = :owner_id');
    $stmt->bindValue(':owner_id', $owner_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $owner = $result->fetchArray(SQLITE3_ASSOC);

    if (!$owner) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Owner not found']);
        exit;
    }

    $team = json_decode($owner['team'], true);
    if (!isset($team[$player_index]) || !$team[$player_index]) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Player not found in owner\'s team']);
        exit;
    }

    // Verify player data matches
    $actual_player = $team[$player_index];
    if (($actual_player['uuid'] ?? '') !== $player_uuid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Player data mismatch']);
        exit;
    }

    // Check if there's already a pending bid from this user for this player
    $stmt = $db->prepare('SELECT id FROM transfer_bids WHERE bidder_id = :bidder_id AND owner_id = :owner_id AND player_uuid = :player_uuid AND status = "pending"');
    $stmt->bindValue(':bidder_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':owner_id', $owner_id, SQLITE3_INTEGER);
    $stmt->bindValue(':player_uuid', $actual_player['uuid'] ?? '', SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($result->fetchArray()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have a pending bid for this player']);
        exit;
    }

    // Validate minimum bid (80% of player value)
    $player_value = $actual_player['value'] ?? 0;
    $min_bid = $player_value * 0.8;

    if ($bid_amount < $min_bid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bid too low. Minimum: ' . formatMarketValue($min_bid)]);
        exit;
    }

    // Create transfer_bids table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS transfer_bids (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bidder_id INTEGER NOT NULL,
        owner_id INTEGER NOT NULL,
        player_uuid TEXT NOT NULL,
        player_data TEXT NOT NULL,
        player_index INTEGER NOT NULL,
        bid_amount INTEGER NOT NULL,
        status TEXT DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        response_time DATETIME NULL,
        FOREIGN KEY (bidder_id) REFERENCES users (id),
        FOREIGN KEY (owner_id) REFERENCES users (id)
    )');

    // Insert the bid
    $stmt = $db->prepare('INSERT INTO transfer_bids (bidder_id, owner_id, player_uuid, player_data, player_index, bid_amount, status) 
                         VALUES (:bidder_id, :owner_id, :player_uuid, :player_data, :player_index, :bid_amount, "pending")');
    $stmt->bindValue(':bidder_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':owner_id', $owner_id, SQLITE3_INTEGER);
    $stmt->bindValue(':player_uuid', $actual_player['uuid'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':player_data', json_encode($actual_player), SQLITE3_TEXT);
    $stmt->bindValue(':player_index', $player_index, SQLITE3_INTEGER);
    $stmt->bindValue(':bid_amount', $bid_amount, SQLITE3_INTEGER);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit bid']);
        exit;
    }

    // Get the last insert ID before closing the connection
    $bid_id = $db->lastInsertRowID();
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Bid submitted successfully',
        'bid_id' => $bid_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>