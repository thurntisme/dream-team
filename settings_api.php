<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'constants.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

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
        case 'get_setting':
            $key = $_GET['key'] ?? '';
            if (empty($key)) {
                echo json_encode(['success' => false, 'message' => 'Setting key required']);
                exit;
            }

            $stmt = $db->prepare('SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = :key');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);

            echo json_encode([
                'success' => true,
                'value' => $row ? $row['setting_value'] : null
            ]);
            break;

        case 'set_setting':
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';

            if (empty($key)) {
                echo json_encode(['success' => false, 'message' => 'Setting key required']);
                exit;
            }

            $stmt = $db->prepare('INSERT OR REPLACE INTO user_settings (user_id, setting_key, setting_value, updated_at) VALUES (:user_id, :key, :value, datetime("now"))');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update setting']);
            }
            break;

        case 'get_all_settings':
            $stmt = $db->prepare('SELECT setting_key, setting_value FROM user_settings WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $settings = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;

        case 'delete_setting':
            $key = $_POST['key'] ?? '';
            if (empty($key)) {
                echo json_encode(['success' => false, 'message' => 'Setting key required']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM user_settings WHERE user_id = :user_id AND setting_key = :key');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Setting deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete setting']);
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