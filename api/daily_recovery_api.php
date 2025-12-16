<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

try {
    $db = getDbConnection();
    $user_id = $_SESSION['user_id'];

    // Check if daily recovery has already been processed today
    $today = date('Y-m-d');
    $last_recovery_key = 'last_daily_recovery_' . $user_id;
    
    if (isset($_SESSION[$last_recovery_key]) && $_SESSION[$last_recovery_key] === $today) {
        echo json_encode([
            'success' => false, 
            'message' => 'Daily recovery already processed today',
            'already_processed' => true
        ]);
        exit;
    }

    // Get user data
    $stmt = $db->prepare('SELECT team, substitutes FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $team = json_decode($user['team'], true) ?: [];
    $substitutes = json_decode($user['substitutes'], true) ?: [];
    $recoveries = [];
    $fitness_improvements = [];

    // Process recovery for main team
    for ($i = 0; $i < count($team); $i++) {
        if ($team[$i]) {
            if (isPlayerInjured($team[$i])) {
                $recovery_result = processPlayerRecovery($team[$i]);
                $team[$i] = $recovery_result['player'];
                if ($recovery_result['recovered']) {
                    $recoveries[] = [
                        'player_name' => $team[$i]['name'],
                        'position' => 'team'
                    ];
                }
            } else {
                // Natural fitness recovery for non-injured players
                $fitness_gain = rand(1, 3);
                $old_fitness = $team[$i]['fitness'] ?? 100;
                $team[$i]['fitness'] = min(100, $old_fitness + $fitness_gain);
                
                if ($team[$i]['fitness'] > $old_fitness) {
                    $fitness_improvements[] = [
                        'player_name' => $team[$i]['name'],
                        'fitness_gain' => $fitness_gain,
                        'new_fitness' => $team[$i]['fitness']
                    ];
                }
            }
        }
    }

    // Process recovery for substitutes
    for ($i = 0; $i < count($substitutes); $i++) {
        if ($substitutes[$i]) {
            if (isPlayerInjured($substitutes[$i])) {
                $recovery_result = processPlayerRecovery($substitutes[$i]);
                $substitutes[$i] = $recovery_result['player'];
                if ($recovery_result['recovered']) {
                    $recoveries[] = [
                        'player_name' => $substitutes[$i]['name'],
                        'position' => 'substitute'
                    ];
                }
            } else {
                // Natural fitness recovery for non-injured players
                $fitness_gain = rand(1, 3);
                $old_fitness = $substitutes[$i]['fitness'] ?? 100;
                $substitutes[$i]['fitness'] = min(100, $old_fitness + $fitness_gain);
                
                if ($substitutes[$i]['fitness'] > $old_fitness) {
                    $fitness_improvements[] = [
                        'player_name' => $substitutes[$i]['name'],
                        'fitness_gain' => $fitness_gain,
                        'new_fitness' => $substitutes[$i]['fitness']
                    ];
                }
            }
        }
    }

    // Update database
    $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
    $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
    $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Mark daily recovery as processed
    $_SESSION[$last_recovery_key] = $today;

    echo json_encode([
        'success' => true,
        'recoveries' => $recoveries,
        'fitness_improvements' => $fitness_improvements,
        'recovery_count' => count($recoveries),
        'fitness_improvement_count' => count($fitness_improvements)
    ]);

    $db->close();

} catch (Exception $e) {
    error_log("Daily recovery error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Daily recovery system error']);
}

// Helper functions
function isPlayerInjured($player) {
    return isset($player['injury']) && $player['injury']['days_remaining'] > 0;
}

function processPlayerRecovery($player) {
    if (!isPlayerInjured($player)) {
        return ['player' => $player, 'recovered' => false];
    }
    
    $player['injury']['days_remaining']--;
    
    // Check if player has recovered
    if ($player['injury']['days_remaining'] <= 0) {
        // Remove injury
        unset($player['injury']);
        
        // Gradual fitness recovery (not full recovery immediately)
        $player['fitness'] = min(100, ($player['fitness'] ?? 50) + rand(10, 20));
        
        return ['player' => $player, 'recovered' => true];
    }
    
    // Gradual fitness improvement during recovery
    $player['fitness'] = min(100, ($player['fitness'] ?? 50) + rand(1, 3));
    
    return ['player' => $player, 'recovered' => false];
}
?>