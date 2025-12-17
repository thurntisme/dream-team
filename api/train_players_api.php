<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/helpers.php';



// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

try {
    $db = getDbConnection();
    $user_id = $_SESSION['user_id'];

    // Get user data
    $stmt = $db->prepare('SELECT team, substitutes, budget FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Check if user has enough budget
    $training_cost = 2000000; // €2M
    if ($user['budget'] < $training_cost) {
        echo json_encode(['success' => false, 'message' => 'Insufficient budget for training']);
        exit;
    }

    // Check training cooldown (24 hours)
    $last_training_key = 'last_training_' . $user_id;
    if (isset($_SESSION[$last_training_key])) {
        $last_training = $_SESSION[$last_training_key];
        $cooldown_hours = 24;
        $time_since_training = (time() - $last_training) / 3600;

        if ($time_since_training < $cooldown_hours) {
            $remaining_hours = ceil($cooldown_hours - $time_since_training);
            echo json_encode([
                'success' => false,
                'message' => "Training on cooldown. Try again in {$remaining_hours} hours."
            ]);
            exit;
        }
    }

    // Process training
    if ($_POST['action'] === 'train_all') {
        $team = json_decode($user['team'], true) ?: [];
        $substitutes = json_decode($user['substitutes'], true) ?: [];

        $players_trained = 0;
        $total_improvement = 0;

        // Train main team players
        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i]) {
                $fitness_before = $team[$i]['fitness'] ?? 100;
                $improvement = rand(5, 15);
                $team[$i]['fitness'] = min(100, $fitness_before + $improvement);

                // Small form boost for training
                if (isset($team[$i]['form'])) {
                    $team[$i]['form'] = min(10, $team[$i]['form'] + 0.1);
                }

                // Award experience for training
                $team[$i] = addPlayerExperience($team[$i], 5); // 5 XP for training

                $players_trained++;
                $total_improvement += $improvement;
            }
        }

        // Train substitute players
        for ($i = 0; $i < count($substitutes); $i++) {
            if ($substitutes[$i]) {
                $fitness_before = $substitutes[$i]['fitness'] ?? 100;
                $improvement = rand(5, 15);
                $substitutes[$i]['fitness'] = min(100, $fitness_before + $improvement);

                // Small form boost for training
                if (isset($substitutes[$i]['form'])) {
                    $substitutes[$i]['form'] = min(10, $substitutes[$i]['form'] + 0.1);
                }

                // Award experience for training
                $substitutes[$i] = addPlayerExperience($substitutes[$i], 5); // 5 XP for training

                $players_trained++;
                $total_improvement += $improvement;
            }
        }

        if ($players_trained > 0) {
            // Update database
            $new_budget = $user['budget'] - $training_cost;
            $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes, budget = :budget WHERE id = :user_id');
            $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
            $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
            $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();

            // Set training cooldown
            $_SESSION[$last_training_key] = time();

            // Award experience for training players
            $expResult = addClubExp($user_id, 20, 'Training session completed', $db);

            $avg_improvement = round($total_improvement / $players_trained, 1);

            $response = [
                'success' => true,
                'players_trained' => $players_trained,
                'avg_improvement' => $avg_improvement,
                'cost' => $training_cost,
                'new_budget' => $new_budget
            ];

            // Add level up information if applicable
            if ($expResult['success'] && $expResult['leveled_up']) {
                $response['level_up'] = [
                    'new_level' => $expResult['new_level'],
                    'levels_gained' => $expResult['levels_gained']
                ];
            }

            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'No players to train']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

    $db->close();

} catch (Exception $e) {
    error_log("Training error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Training system error']);
}
?>