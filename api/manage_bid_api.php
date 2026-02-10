<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';

header('Content-Type: application/json');

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

$action = $input['action'] ?? null;
$bid_id = $input['bid_id'] ?? null;

// Validate input
if (!$action || !$bid_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($action, ['accept', 'reject', 'cancel'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $db = getDbConnection();

    // Get bid details
    $stmt = $db->prepare('SELECT * FROM transfer_bids WHERE id = :bid_id AND status = "pending"');
    $stmt->bindValue(':bid_id', $bid_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $bid = $result->fetchArray(SQLITE3_ASSOC);

    if (!$bid) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bid not found or already processed']);
        exit;
    }

    // Verify user permissions
    if ($action === 'cancel' && $bid['bidder_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only cancel your own bids']);
        exit;
    }

    if (($action === 'accept' || $action === 'reject') && $bid['owner_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only manage bids for your own players']);
        exit;
    }

    if ($action === 'cancel') {
        // Simply update bid status to cancelled with response time
        $stmt = $db->prepare('UPDATE transfer_bids SET status = "cancelled", response_time = CURRENT_TIMESTAMP WHERE id = :bid_id');
        $stmt->bindValue(':bid_id', $bid_id, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to cancel bid']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Bid cancelled successfully']);

    } elseif ($action === 'reject') {
        // Simply update bid status to rejected with response time
        $stmt = $db->prepare('UPDATE transfer_bids SET status = "rejected", response_time = CURRENT_TIMESTAMP WHERE id = :bid_id');
        $stmt->bindValue(':bid_id', $bid_id, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reject bid']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Bid rejected successfully']);

    } elseif ($action === 'accept') {
        // Handle player transfer

        // Get bidder and owner data
        $stmt = $db->prepare('SELECT budget, team FROM users WHERE id = :bidder_id');
        $stmt->bindValue(':bidder_id', $bid['bidder_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $bidder = $result->fetchArray(SQLITE3_ASSOC);

        $stmt = $db->prepare('SELECT budget, team FROM users WHERE id = :owner_id');
        $stmt->bindValue(':owner_id', $bid['owner_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $owner = $result->fetchArray(SQLITE3_ASSOC);

        if (!$bidder || !$owner) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User data not found']);
            exit;
        }

        // Check if bidder still has enough budget
        if ($bidder['budget'] < $bid['bid_amount']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bidder no longer has sufficient budget']);
            exit;
        }

        // Parse teams
        $bidder_team = json_decode($bidder['team'], true);
        $owner_team = json_decode($owner['team'], true);

        // Verify player still exists in owner's team
        $player_index = $bid['player_index'];
        if (!isset($owner_team[$player_index]) || !$owner_team[$player_index]) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Player no longer available']);
            exit;
        }

        // Verify player data matches
        $current_player = $owner_team[$player_index];
        if (($current_player['uuid'] ?? '') !== ($bid['player_uuid'] ?? '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Player data has changed']);
            exit;
        }

        // Find empty slot in bidder's team
        $empty_slot = null;
        for ($i = 0; $i < count($bidder_team); $i++) {
            if (!$bidder_team[$i]) {
                $empty_slot = $i;
                break;
            }
        }

        if ($empty_slot === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bidder\'s team is full']);
            exit;
        }

        // Begin transaction
        $db->exec('START TRANSACTION');

        try {
            // Transfer player
            $bidder_team[$empty_slot] = $current_player;
            $owner_team[$player_index] = null;

            // Update budgets
            $new_bidder_budget = $bidder['budget'] - $bid['bid_amount'];
            $new_owner_budget = $owner['budget'] + $bid['bid_amount'];

            // Update bidder's team and budget
            $stmt = $db->prepare('UPDATE users SET team = :team, budget = :budget WHERE id = :user_id');
            $stmt->bindValue(':team', json_encode($bidder_team), SQLITE3_TEXT);
            $stmt->bindValue(':budget', $new_bidder_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $bid['bidder_id'], SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update bidder data');
            }

            // Update owner's team and budget
            $stmt = $db->prepare('UPDATE users SET team = :team, budget = :budget WHERE id = :user_id');
            $stmt->bindValue(':team', json_encode($owner_team), SQLITE3_TEXT);
            $stmt->bindValue(':budget', $new_owner_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $bid['owner_id'], SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update owner data');
            }

            // Update bid status to accepted with response time
            $stmt = $db->prepare('UPDATE transfer_bids SET status = "accepted", response_time = CURRENT_TIMESTAMP WHERE id = :bid_id');
            $stmt->bindValue(':bid_id', $bid_id, SQLITE3_INTEGER);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update bid status');
            }

            // Reject all other pending bids for this player with response time
            $stmt = $db->prepare('UPDATE transfer_bids SET status = "rejected", response_time = CURRENT_TIMESTAMP WHERE owner_id = :owner_id AND player_uuid = :player_uuid AND status = "pending" AND id != :bid_id');
            $stmt->bindValue(':owner_id', $bid['owner_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':player_uuid', $bid['player_uuid'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(':bid_id', $bid_id, SQLITE3_INTEGER);
            $stmt->execute(); // Don't fail if this doesn't work

            // Commit transaction
            $db->exec('COMMIT');

            echo json_encode([
                'success' => true,
                'message' => 'Player transferred successfully',
                'transfer_details' => [
                    'player_name' => $bid_player_data['name'] ?? 'Unknown Player',
                    'amount' => $bid['bid_amount'],
                    'new_owner_budget' => $new_owner_budget,
                    'new_bidder_budget' => $new_bidder_budget
                ]
            ]);

        } catch (Exception $e) {
            // Rollback transaction
            $db->exec('ROLLBACK');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Transfer failed: ' . $e->getMessage()]);
            exit;
        }
    }

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
