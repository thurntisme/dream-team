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

    // Injury types and their properties
    $injury_types = [
        'minor_strain' => [
            'name' => 'Minor Muscle Strain',
            'duration_days' => rand(3, 7),
            'fitness_penalty' => rand(10, 20),
            'probability' => 0.8 // 80% of injuries are minor
        ],
        'muscle_injury' => [
            'name' => 'Muscle Injury',
            'duration_days' => rand(7, 14),
            'fitness_penalty' => rand(20, 35),
            'probability' => 0.15 // 15% of injuries
        ],
        'serious_injury' => [
            'name' => 'Serious Injury',
            'duration_days' => rand(14, 28),
            'fitness_penalty' => rand(35, 50),
            'probability' => 0.05 // 5% of injuries are serious
        ]
    ];

    if ($_POST['action'] === 'check_random_injuries') {
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
        $injuries_occurred = [];

        // Check for random injuries in main team
        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i] && !isPlayerInjured($team[$i])) {
                $injury_result = checkForInjury($team[$i]);
                if ($injury_result['injured']) {
                    $team[$i] = applyInjury($team[$i], $injury_result['injury_type'], $injury_types);
                    $injuries_occurred[] = [
                        'player_name' => $team[$i]['name'],
                        'injury_type' => $injury_types[$injury_result['injury_type']]['name'],
                        'duration' => $team[$i]['injury']['duration_days'],
                        'position' => 'team'
                    ];
                }
            }
        }

        // Check for random injuries in substitutes
        for ($i = 0; $i < count($substitutes); $i++) {
            if ($substitutes[$i] && !isPlayerInjured($substitutes[$i])) {
                $injury_result = checkForInjury($substitutes[$i]);
                if ($injury_result['injured']) {
                    $substitutes[$i] = applyInjury($substitutes[$i], $injury_result['injury_type'], $injury_types);
                    $injuries_occurred[] = [
                        'player_name' => $substitutes[$i]['name'],
                        'injury_type' => $injury_types[$injury_result['injury_type']]['name'],
                        'duration' => $substitutes[$i]['injury']['duration_days'],
                        'position' => 'substitute'
                    ];
                }
            }
        }

        // Update database if injuries occurred
        if (!empty($injuries_occurred)) {
            $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
            $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
            $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();
        }

        echo json_encode([
            'success' => true,
            'injuries' => $injuries_occurred,
            'injury_count' => count($injuries_occurred)
        ]);

    } elseif ($_POST['action'] === 'process_daily_recovery') {
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

        // Process recovery for main team
        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i] && isPlayerInjured($team[$i])) {
                $recovery_result = processPlayerRecovery($team[$i]);
                $team[$i] = $recovery_result['player'];
                if ($recovery_result['recovered']) {
                    $recoveries[] = [
                        'player_name' => $team[$i]['name'],
                        'position' => 'team'
                    ];
                }
            }
        }

        // Process recovery for substitutes
        for ($i = 0; $i < count($substitutes); $i++) {
            if ($substitutes[$i] && isPlayerInjured($substitutes[$i])) {
                $recovery_result = processPlayerRecovery($substitutes[$i]);
                $substitutes[$i] = $recovery_result['player'];
                if ($recovery_result['recovered']) {
                    $recoveries[] = [
                        'player_name' => $substitutes[$i]['name'],
                        'position' => 'substitute'
                    ];
                }
            }
        }

        // Update database
        $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
        $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
        $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'recoveries' => $recoveries,
            'recovery_count' => count($recoveries)
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

    $db->close();

} catch (Exception $e) {
    error_log("Injury system error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Injury system error']);
}

// Helper functions
function checkForInjury($player) {
    $base_injury_chance = 0.02; // 2% base chance per check
    $fitness = $player['fitness'] ?? 100;
    
    // Increase injury chance for low fitness players
    if ($fitness < 40) {
        $base_injury_chance *= 3; // 6% chance for very low fitness
    } elseif ($fitness < 60) {
        $base_injury_chance *= 2; // 4% chance for low fitness
    } elseif ($fitness < 80) {
        $base_injury_chance *= 1.5; // 3% chance for moderate fitness
    }
    
    // Random check
    if (mt_rand(1, 10000) / 10000 <= $base_injury_chance) {
        // Determine injury type
        $rand = mt_rand(1, 100) / 100;
        if ($rand <= 0.8) {
            $injury_type = 'minor_strain';
        } elseif ($rand <= 0.95) {
            $injury_type = 'muscle_injury';
        } else {
            $injury_type = 'serious_injury';
        }
        
        return ['injured' => true, 'injury_type' => $injury_type];
    }
    
    return ['injured' => false];
}

function applyInjury($player, $injury_type, $injury_types) {
    $injury_data = $injury_types[$injury_type];
    
    $player['injury'] = [
        'type' => $injury_type,
        'name' => $injury_data['name'],
        'duration_days' => $injury_data['duration_days'],
        'days_remaining' => $injury_data['duration_days'],
        'fitness_penalty' => $injury_data['fitness_penalty'],
        'injury_date' => date('Y-m-d H:i:s')
    ];
    
    // Apply immediate fitness penalty
    $player['fitness'] = max(0, ($player['fitness'] ?? 100) - $injury_data['fitness_penalty']);
    
    return $player;
}

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