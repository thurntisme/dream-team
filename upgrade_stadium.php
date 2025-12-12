<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];

    if ($input['action'] === 'upgrade') {
        // Stadium upgrade logic
        $cost = intval($input['cost']);
        $newLevel = intval($input['level']);

        // Validate input
        if ($cost <= 0 || $newLevel < 2 || $newLevel > 5) {
            echo json_encode(['success' => false, 'message' => 'Invalid upgrade parameters']);
            exit;
        }

        // Get user's current budget
        $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $currentBudget = $userData['budget'];

        // Check if user can afford the upgrade
        if ($currentBudget < $cost) {
            echo json_encode(['success' => false, 'message' => 'Insufficient budget']);
            exit;
        }

        // Get current stadium data
        $stmt = $db->prepare('SELECT level, capacity FROM stadiums WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $stadiumData = $result->fetchArray(SQLITE3_ASSOC);

        if (!$stadiumData) {
            echo json_encode(['success' => false, 'message' => 'Stadium not found']);
            exit;
        }

        $currentLevel = $stadiumData['level'];

        // Validate upgrade sequence
        if ($newLevel !== $currentLevel + 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid upgrade level']);
            exit;
        }

        // Stadium level configurations
        $stadiumLevels = [
            2 => ['capacity' => 20000],
            3 => ['capacity' => 35000],
            4 => ['capacity' => 50000],
            5 => ['capacity' => 75000]
        ];

        $newCapacity = $stadiumLevels[$newLevel]['capacity'];

        // Begin transaction
        $db->exec('BEGIN TRANSACTION');

        try {
            // Update user budget
            $stmt = $db->prepare('UPDATE users SET budget = budget - :cost WHERE id = :user_id');
            $stmt->bindValue(':cost', $cost, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->execute();

            // Update stadium
            $stmt = $db->prepare('UPDATE stadiums SET level = :level, capacity = :capacity, last_upgrade = CURRENT_TIMESTAMP WHERE user_id = :user_id');
            $stmt->bindValue(':level', $newLevel, SQLITE3_INTEGER);
            $stmt->bindValue(':capacity', $newCapacity, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->execute();

            // Commit transaction
            $db->exec('COMMIT');

            echo json_encode([
                'success' => true,
                'message' => 'Stadium upgraded successfully',
                'new_level' => $newLevel,
                'new_capacity' => $newCapacity,
                'remaining_budget' => $currentBudget - $cost
            ]);

        } catch (Exception $e) {
            $db->exec('ROLLBACK');
            echo json_encode(['success' => false, 'message' => 'Failed to upgrade stadium']);
        }

    } elseif ($input['action'] === 'rename') {
        // Stadium rename logic
        $newName = trim($input['name']);

        // Validate name
        if (empty($newName) || strlen($newName) > 100) {
            echo json_encode(['success' => false, 'message' => 'Invalid stadium name']);
            exit;
        }

        // Update stadium name
        $stmt = $db->prepare('UPDATE stadiums SET name = :name WHERE user_id = :user_id');
        $stmt->bindValue(':name', $newName, SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Stadium renamed successfully',
                'new_name' => $newName
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to rename stadium']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

    $db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>