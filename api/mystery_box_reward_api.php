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

// Only allow POST requests
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
$user_id = $_SESSION['user_id'];

try {
    $db = getDbConnection();
    
    // Check if user has already claimed mystery box reward for this match
    $match_id = $input['match_id'] ?? null;
    if (!$match_id) {
        throw new Exception('Match ID is required');
    }
    
    $session_key = "mystery_box_claimed_{$match_id}_{$user_id}";
    if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true) {
        throw new Exception('Mystery box reward already claimed for this match');
    }
    
    // Validate reward data
    if (!isset($reward['type']) || !isset($reward['text'])) {
        throw new Exception('Invalid reward data');
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
            $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :user_id');
            $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
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
            $stmt = $db->prepare('UPDATE users SET fans = fans + :amount WHERE id = :user_id');
            $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                $success = true;
                $message = "Gained " . number_format($amount) . " new fans!";
            }
            break;
            
        case 'player':
            // For player rewards, we could add a random player to inventory
            // For now, we'll give a budget equivalent
            $equivalent_budget = 1000000; // €1M equivalent
            
            $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :user_id');
            $stmt->bindValue(':amount', $equivalent_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                $success = true;
                $message = "Received a player card worth €" . number_format($equivalent_budget) . "!";
            }
            break;
            
        case 'item':
            // For item rewards, we could add training items
            // For now, we'll give a budget equivalent
            $equivalent_budget = 500000; // €500K equivalent
            
            $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :user_id');
            $stmt->bindValue(':amount', $equivalent_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                $success = true;
                $message = "Received a training item worth €" . number_format($equivalent_budget) . "!";
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
    $stmt = $db->prepare('SELECT budget, fans FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'updated_budget' => $user_data['budget'],
        'updated_fans' => $user_data['fans']
    ]);
    
} catch (Exception $e) {
    error_log("Mystery box reward error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to apply reward: ' . $e->getMessage()
    ]);
}
?>