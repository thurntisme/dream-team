<?php
session_start();
require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/player_functions.php';
require_once __DIR__ . '/../controllers/use-item-controller.php';

header('Content-Type: application/json');

try {
    if (!isDatabaseAvailable()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database not available']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $action = $payload['action'] ?? 'open_pack';
    $inventory_id = 0;
    $item_uuid = isset($payload['item_uuid']) ? trim((string)$payload['item_uuid']) : '';
    $user_uuid = $_SESSION['user_uuid'] ?? null;

    if ($item_uuid !== '' && $user_uuid) {
        $db = getDbConnection();
        // Resolve inventory id and item id by item_uuid
        $stmt = $db->prepare('SELECT id, item_id FROM user_inventory WHERE user_uuid = :user_uuid AND item_uuid = :item_uuid AND quantity > 0 ORDER BY id ASC LIMIT 1');
        if ($stmt === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Failed to resolve inventory item']);
            exit;
        }
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $stmt->bindValue(':item_uuid', $item_uuid, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if (!$row || !isset($row['id']) || !isset($row['item_id'])) {
            $db->close();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Item not found in your inventory']);
            exit;
        }
        $inventory_id = (int)$row['id'];
        $shop_id = (int)$row['item_id'];

        // Check effect_type for the item_id
        $stmt2 = $db->prepare('SELECT effect_type FROM shop_items WHERE id = :item_id');
        if ($stmt2 === false) {
            $db->close();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Failed to load item details']);
            exit;
        }
        $stmt2->bindValue(':item_id', $shop_id, SQLITE3_INTEGER);
        $res2 = $stmt2->execute();
        $row2 = $res2 ? $res2->fetchArray(SQLITE3_ASSOC) : null;
        if (!$row2 || ($row2['effect_type'] ?? '') !== 'player_pack') {
            $db->close();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This item is not a player pack']);
            exit;
        }
        $db->close();
    }

    if ($action !== 'open_pack' || $shop_id <= 0 || !$user_uuid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $controller = new UseItemController($user_uuid);
    $result = $controller->openPack($inventory_id, $shop_id);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
