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
        case 'get_ticket':
            $ticket_id = $input['ticket_id'] ?? 0;

            if (!$ticket_id) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID required']);
                exit;
            }

            // Get ticket details (only user's own tickets)
            $stmt = $db->prepare('SELECT * FROM support_tickets WHERE id = :id AND user_id = :user_id');
            $stmt->bindValue(':id', $ticket_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $ticket = $result->fetchArray(SQLITE3_ASSOC);

            if (!$ticket) {
                echo json_encode(['success' => false, 'message' => 'Ticket not found']);
                exit;
            }

            echo json_encode(['success' => true, 'ticket' => $ticket]);
            break;

        case 'update_ticket_status':
            // This would typically be restricted to admin users
            $ticket_id = $input['ticket_id'] ?? 0;
            $status = $input['status'] ?? '';
            $admin_response = $input['admin_response'] ?? '';

            if (!$ticket_id || !$status) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID and status required']);
                exit;
            }

            $valid_statuses = ['open', 'in_progress', 'resolved', 'closed'];
            if (!in_array($status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }

            // Update ticket status
            $stmt = $db->prepare('UPDATE support_tickets SET status = :status, admin_response = :admin_response, updated_at = CURRENT_TIMESTAMP, last_response_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':admin_response', $admin_response, SQLITE3_TEXT);
            $stmt->bindValue(':id', $ticket_id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Ticket updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update ticket']);
            }
            break;

        case 'close_ticket':
            $ticket_id = $input['ticket_id'] ?? 0;

            if (!$ticket_id) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID required']);
                exit;
            }

            // Close user's own ticket
            $stmt = $db->prepare('UPDATE support_tickets SET status = "closed", updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
            $stmt->bindValue(':id', $ticket_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Ticket closed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to close ticket']);
            }
            break;

        case 'get_user_tickets':
            // Get all tickets for the current user
            $stmt = $db->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $tickets = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tickets[] = $row;
            }

            echo json_encode(['success' => true, 'tickets' => $tickets]);
            break;

        case 'get_all_tickets':
            // Admin function - get all tickets
            $status_filter = $input['status'] ?? '';
            $priority_filter = $input['priority'] ?? '';

            $query = 'SELECT t.*, u.name as user_name, u.email as user_email, u.club_name 
                     FROM support_tickets t 
                     JOIN users u ON t.user_id = u.id 
                     WHERE 1=1';

            $params = [];

            if ($status_filter) {
                $query .= ' AND t.status = :status';
                $params[':status'] = $status_filter;
            }

            if ($priority_filter) {
                $query .= ' AND t.priority = :priority';
                $params[':priority'] = $priority_filter;
            }

            $query .= ' ORDER BY t.created_at DESC LIMIT 100';

            $stmt = $db->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, SQLITE3_TEXT);
            }

            $result = $stmt->execute();

            $tickets = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tickets[] = $row;
            }

            echo json_encode(['success' => true, 'tickets' => $tickets]);
            break;

        case 'add_response':
            $ticket_id = $input['ticket_id'] ?? 0;
            $response = $input['response'] ?? '';

            if (!$ticket_id || !$response) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID and response required']);
                exit;
            }

            // Add admin response to ticket
            $stmt = $db->prepare('UPDATE support_tickets SET admin_response = :response, status = "in_progress", updated_at = CURRENT_TIMESTAMP, last_response_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->bindValue(':response', $response, SQLITE3_TEXT);
            $stmt->bindValue(':id', $ticket_id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Response added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add response']);
            }
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