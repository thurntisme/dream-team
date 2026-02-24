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
    if ($item_uuid !== '') {
        $db = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM user_inventory WHERE user_uuid = :user_uuid AND item_uuid = :item_uuid AND quantity > 0 ORDER BY id ASC LIMIT 1');
        if ($stmt === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Failed to resolve inventory item']);
            exit;
        }
        $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':item_uuid', $item_uuid, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if ($row && isset($row['id'])) {
            $inventory_id = (int)$row['id'];
        }
        $db->close();
    } else {
        if (isset($payload['item_id'])) {
            // Compatibility: resolve by item_id if provided
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT id FROM user_inventory WHERE user_uuid = :user_uuid AND item_id = :item_id AND quantity > 0 ORDER BY id ASC LIMIT 1');
            if ($stmt) {
                $stmt->bindValue(':user_uuid', $_SESSION['user_uuid'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':item_id', (int)$payload['item_id'], SQLITE3_INTEGER);
                $res = $stmt->execute();
                $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
                if ($row && isset($row['id'])) {
                    $inventory_id = (int)$row['id'];
                }
            }
            $db->close();
        }
    }
    $user_uuid = $_SESSION['user_uuid'] ?? null;

    if ($action !== 'open_pack' || $inventory_id <= 0 || !$user_uuid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $controller = new UseItemController($user_uuid);
    $result = $controller->openPack($inventory_id);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
