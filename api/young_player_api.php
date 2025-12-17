<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/constants.php';



// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    $db = getDbConnection();

    switch ($action) {
        case 'get_academy_players':
            $players = getClubYoungPlayers($userId, 'academy');
            echo json_encode([
                'success' => true,
                'players' => $players
            ]);
            break;

        case 'get_available_players':
            $players = getAvailableYoungPlayers($userId);
            echo json_encode([
                'success' => true,
                'players' => $players
            ]);
            break;

        case 'get_pending_bids':
            $bids = getClubYoungPlayerBids($userId);
            echo json_encode([
                'success' => true,
                'bids' => $bids
            ]);
            break;

        case 'promote_player':
            $playerId = $_POST['player_id'] ?? 0;
            if (promoteYoungPlayer($playerId)) {
                echo json_encode(['success' => true, 'message' => 'Player promoted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to promote player']);
            }
            break;

        case 'place_bid':
            $playerId = $_POST['player_id'] ?? 0;
            $bidAmount = $_POST['bid_amount'] ?? 0;

            if (createYoungPlayerBid($playerId, $userId, $bidAmount)) {
                echo json_encode(['success' => true, 'message' => 'Bid placed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to place bid']);
            }
            break;

        case 'process_bid':
            $bidId = $_POST['bid_id'] ?? 0;
            $bidAction = $_POST['bid_action'] ?? '';

            if (in_array($bidAction, ['accept', 'reject'])) {
                if (processYoungPlayerBid($bidId, $bidAction, $userId)) {
                    $message = $bidAction === 'accept' ? 'Bid accepted successfully' : 'Bid rejected';
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to process bid']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid bid action']);
            }
            break;

        case 'update_training':
            $playerId = $_POST['player_id'] ?? 0;
            $trainingFocus = $_POST['training_focus'] ?? 'balanced';

            $stmt = $db->prepare('UPDATE young_players SET training_focus = :training_focus WHERE id = :id AND club_id = :club_id');
            $stmt->bindValue(':training_focus', $trainingFocus, SQLITE3_TEXT);
            $stmt->bindValue(':id', $playerId, SQLITE3_INTEGER);
            $stmt->bindValue(':club_id', $userId, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Training focus updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update training focus']);
            }
            break;

        case 'sell_player':
            $playerId = $_POST['player_id'] ?? 0;
            $sellPrice = $_POST['sell_price'] ?? 0;

            // Get player data
            $stmt = $db->prepare('SELECT * FROM young_players WHERE id = :id AND club_id = :club_id');
            $stmt->bindValue(':id', $playerId, SQLITE3_INTEGER);
            $stmt->bindValue(':club_id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $player = $result->fetchArray(SQLITE3_ASSOC);

            if ($player && $sellPrice > 0) {
                // Remove player from academy
                $stmt = $db->prepare('DELETE FROM young_players WHERE id = :id');
                $stmt->bindValue(':id', $playerId, SQLITE3_INTEGER);
                $stmt->execute();

                // Add money to budget
                $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :id');
                $stmt->bindValue(':amount', $sellPrice, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                $stmt->execute();

                echo json_encode(['success' => true, 'message' => 'Player sold successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to sell player']);
            }
            break;

        case 'generate_player':
            $youngPlayer = generateYoungPlayer($userId);

            $stmt = $db->prepare('INSERT INTO young_players (club_id, name, age, position, potential_rating, current_rating, development_stage, contract_years, value, training_focus) VALUES (:club_id, :name, :age, :position, :potential_rating, :current_rating, :development_stage, :contract_years, :value, :training_focus)');
            $stmt->bindValue(':club_id', $youngPlayer['club_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':name', $youngPlayer['name'], SQLITE3_TEXT);
            $stmt->bindValue(':age', $youngPlayer['age'], SQLITE3_INTEGER);
            $stmt->bindValue(':position', $youngPlayer['position'], SQLITE3_TEXT);
            $stmt->bindValue(':potential_rating', $youngPlayer['potential_rating'], SQLITE3_INTEGER);
            $stmt->bindValue(':current_rating', $youngPlayer['current_rating'], SQLITE3_INTEGER);
            $stmt->bindValue(':development_stage', $youngPlayer['development_stage'], SQLITE3_TEXT);
            $stmt->bindValue(':contract_years', $youngPlayer['contract_years'], SQLITE3_INTEGER);
            $stmt->bindValue(':value', $youngPlayer['value'], SQLITE3_INTEGER);
            $stmt->bindValue(':training_focus', $youngPlayer['training_focus'], SQLITE3_TEXT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'New young player added to academy', 'player' => $youngPlayer]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add young player']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

    $db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>