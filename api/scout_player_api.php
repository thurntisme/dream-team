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

if (!$input || !isset($input['player_uuid']) || !isset($input['scout_type']) || !isset($input['cost'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];
    $playerUuid = $input['player_uuid'];
    $scoutType = $input['scout_type'];
    $cost = intval($input['cost']);

    // Validate scout type and cost
    $valid_scouts = [
        'basic' => 100000,
        'detailed' => 250000,
        'premium' => 500000
    ];

    if (!isset($valid_scouts[$scoutType]) || $valid_scouts[$scoutType] !== $cost) {
        echo json_encode(['success' => false, 'message' => 'Invalid scout type or cost']);
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

    // Check if user can afford the scout
    if ($currentBudget < $cost) {
        echo json_encode(['success' => false, 'message' => 'Insufficient budget']);
        exit;
    }

    // Check if player is already scouted
    $stmt = $db->prepare('SELECT report_quality FROM scouting_reports WHERE user_id = :user_id AND player_uuid = :player_uuid');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':player_uuid', $playerUuid, SQLITE3_TEXT);
    $result = $stmt->execute();
    $existingReport = $result->fetchArray(SQLITE3_ASSOC);

    // Determine report quality
    $report_quality = 1; // Basic
    if ($scoutType === 'detailed') {
        $report_quality = 2;
    } elseif ($scoutType === 'premium') {
        $report_quality = 3;
    }

    // If player is already scouted, only allow upgrades
    if ($existingReport) {
        if ($existingReport['report_quality'] >= $report_quality) {
            echo json_encode(['success' => false, 'message' => 'Player already has this level of scouting or better']);
            exit;
        }
    }

    // Load players data to validate player exists
    $players_data = [];
    if (file_exists('../assets/json/players.json')) {
        $players_json = file_get_contents('../assets/json/players.json');
        $players_data = json_decode($players_json, true) ?? [];
    }

    $found_players = array_filter($players_data, function ($player) use ($playerUuid) {
        return $player['uuid'] === $playerUuid;
    });
    if (count($found_players) > 0) {
        $player = array_values($found_players)[0];
    } else {
        $player = null;
        echo json_encode(['success' => false, 'message' => 'Player not found']);
    }

    // Begin transaction
    $db->exec('BEGIN TRANSACTION');

    try {
        // Update user budget
        $stmt = $db->prepare('UPDATE users SET budget = budget - :cost WHERE id = :user_id');
        $stmt->bindValue(':cost', $cost, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->execute();

        // Insert or update scouting report
        if ($existingReport) {
            // Update existing report
            $stmt = $db->prepare('UPDATE scouting_reports SET report_quality = :quality, scouted_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND player_uuid = :player_uuid');
            $stmt->bindValue(':quality', $report_quality, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':player_uuid', $playerUuid, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            // Insert new report
            $stmt = $db->prepare('INSERT INTO scouting_reports (user_id, player_uuid, report_quality) VALUES (:user_id, :player_uuid, :quality)');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':player_uuid', $playerUuid, SQLITE3_TEXT);
            $stmt->bindValue(':quality', $report_quality, SQLITE3_INTEGER);
            $stmt->execute();
        }

        // Commit transaction first
        $db->exec('COMMIT');

        // Close the database connection to prevent locks
        $db->close();

        // Small delay to ensure database connection is fully released
        usleep(50000); // 50ms

        // Award experience for scouting (after transaction is complete and connection closed)
        require_once '../includes/helpers.php';
        $expGain = match ($scoutType) {
            'premium' => 10,
            'detailed' => 6,
            'basic' => 3,
            default => 3
        };
        $expResult = addClubExp($userId, $expGain, "Player scouted ($scoutType report)");

        $response = [
            'success' => true,
            'message' => 'Player scouted successfully',
            'scout_type' => $scoutType,
            'report_quality' => $report_quality,
            'remaining_budget' => $currentBudget - $cost
        ];

        // Add level up information if applicable
        if ($expResult['success'] && $expResult['leveled_up']) {
            $response['level_up'] = [
                'new_level' => $expResult['new_level'],
                'levels_gained' => $expResult['levels_gained']
            ];
        }

        echo json_encode($response);

    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        $db->close();
        echo json_encode(['success' => false, 'message' => 'Failed to scout player']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}