<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';

header('Content-Type: application/json');



// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? '';

try {
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];

    switch ($action) {
        case 'approve_feedback':
            // This would typically be restricted to admin users
            // For now, we'll simulate admin approval for testing
            $feedback_id = $input['feedback_id'] ?? 0;

            if (!$feedback_id) {
                echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
                exit;
            }

            // Get feedback details
            $stmt = $db->prepare('SELECT * FROM user_feedback WHERE id = :id');
            $stmt->bindValue(':id', $feedback_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $feedback = $result->fetchArray(SQLITE3_ASSOC);

            if (!$feedback) {
                echo json_encode(['success' => false, 'message' => 'Feedback not found']);
                exit;
            }

            if ($feedback['status'] !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'Feedback already processed']);
                exit;
            }

            // Calculate additional reward (1400 - 140 = 1260)
            $additional_reward = 1260;
            $total_reward = 1400;

            // Begin transaction
            $db->exec('BEGIN TRANSACTION');

            try {
                // Update feedback status
                $stmt = $db->prepare('UPDATE user_feedback SET status = "approved", reward_amount = :total_reward, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $stmt->bindValue(':total_reward', $total_reward, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $feedback_id, SQLITE3_INTEGER);
                $stmt->execute();

                // Award additional money to user
                $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :user_id');
                $stmt->bindValue(':reward', $additional_reward, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $feedback['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                // Award experience for approved feedback
                require_once '../includes/helpers.php';
                $expResult = addClubExp($feedback['user_id'], 25, 'Feedback approved', $db);

                $db->exec('COMMIT');

                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback approved successfully',
                    'additional_reward' => $additional_reward,
                    'total_reward' => $total_reward
                ]);

            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                echo json_encode(['success' => false, 'message' => 'Failed to approve feedback']);
            }
            break;

        case 'reject_feedback':
            $feedback_id = $input['feedback_id'] ?? 0;
            $reason = $input['reason'] ?? '';

            if (!$feedback_id) {
                echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
                exit;
            }

            // Update feedback status
            $stmt = $db->prepare('UPDATE user_feedback SET status = "rejected", admin_response = :reason, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->bindValue(':reason', $reason, SQLITE3_TEXT);
            $stmt->bindValue(':id', $feedback_id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Feedback rejected']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject feedback']);
            }
            break;

        case 'get_feedback_list':
            // Get all feedback (admin function)
            $stmt = $db->prepare('
                SELECT f.*, u.name as user_name, u.club_name 
                FROM user_feedback f 
                JOIN users u ON f.user_id = u.id 
                ORDER BY f.created_at DESC 
                LIMIT 50
            ');
            $result = $stmt->execute();

            $feedback_list = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $feedback_list[] = $row;
            }

            echo json_encode(['success' => true, 'feedback' => $feedback_list]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

    $db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>