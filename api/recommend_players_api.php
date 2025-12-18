<?php
session_start();

require_once '../config/config.php';
require_once '../config/constants.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Define recommendation cost
define('RECOMMENDATION_COST', 2000000); // €2M per recommendation request

$db = getDbConnection();
$userId = $_SESSION['user_id'];

try {
    $startTime = microtime(true);
    debug_info("Player recommendation request started", ['user_id' => $userId]);
    
    // Check if this is a cost inquiry or actual recommendation request
    $action = $_POST['action'] ?? 'get_recommendations';
    
    if ($action === 'get_cost') {
        debug_info("Cost inquiry request", ['user_id' => $userId, 'cost' => RECOMMENDATION_COST]);
        // Return just the cost for confirmation
        echo json_encode([
            'success' => true,
            'cost' => RECOMMENDATION_COST,
            'formatted_cost' => '€' . number_format(RECOMMENDATION_COST / 1000000, 1) . 'M'
        ]);
        exit;
    }

    // Get user data
    $stmt = $db->prepare('SELECT team, substitutes, budget, formation FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Check if user has enough budget for recommendations
    if ($user['budget'] < RECOMMENDATION_COST) {
        echo json_encode([
            'success' => false, 
            'message' => 'Insufficient budget for player recommendations',
            'required' => RECOMMENDATION_COST,
            'current' => $user['budget'],
            'error_type' => 'insufficient_budget'
        ]);
        exit;
    }

    // Deduct cost from user's budget
    $newBudget = $user['budget'] - RECOMMENDATION_COST;
    $updateStmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
    $updateStmt->bindValue(':budget', $newBudget, SQLITE3_INTEGER);
    $updateStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to process payment for recommendations');
    }

    $currentTeam = json_decode($user['team'], true) ?: [];
    $currentSubs = json_decode($user['substitutes'], true) ?: [];
    $budget = $user['budget'];
    $formation = $user['formation'] ?: '4-4-2';

    // Get all available players
    $allPlayers = getDefaultPlayers();

    // Get current player names to exclude them
    $currentPlayerNames = [];
    foreach ($currentTeam as $player) {
        if ($player && isset($player['name'])) {
            $currentPlayerNames[] = $player['name'];
        }
    }
    foreach ($currentSubs as $player) {
        if ($player && isset($player['name'])) {
            $currentPlayerNames[] = $player['name'];
        }
    }

    // Analyze team composition
    $positionCounts = [];
    $positionRatings = [];
    $emptyPositions = [];

    // Get formation positions
    $formationData = FORMATIONS[$formation];
    $requiredPositions = $formationData['roles'];

    // Count current positions and ratings
    foreach ($currentTeam as $idx => $player) {
        if ($player && isset($player['position'])) {
            $pos = $player['position'];
            if (!isset($positionCounts[$pos])) {
                $positionCounts[$pos] = 0;
                $positionRatings[$pos] = [];
            }
            $positionCounts[$pos]++;
            $positionRatings[$pos][] = $player['rating'] ?? 70;
        } else {
            // Empty position
            if (isset($requiredPositions[$idx])) {
                $emptyPositions[] = $requiredPositions[$idx];
            }
        }
    }

    // Calculate average ratings per position
    $avgPositionRatings = [];
    foreach ($positionRatings as $pos => $ratings) {
        $avgPositionRatings[$pos] = array_sum($ratings) / count($ratings);
    }

    // Identify weak positions (below 75 rating)
    $weakPositions = [];
    foreach ($avgPositionRatings as $pos => $avgRating) {
        if ($avgRating < 75) {
            $weakPositions[] = $pos;
        }
    }

    // Prioritize recommendations
    $priorityPositions = array_unique(array_merge($emptyPositions, $weakPositions));

    // Generate recommendations
    $recommendations = [];
    $maxRecommendations = 10;

    // Filter available players
    foreach ($allPlayers as $player) {
        // Skip if already in team
        if (in_array($player['name'], $currentPlayerNames)) {
            continue;
        }

        // Skip if too expensive
        if ($player['value'] > $budget) {
            continue;
        }

        $score = 0;
        $reason = '';

        // Check if fills empty position
        if (in_array($player['position'], $emptyPositions)) {
            $score += 100;
            $reason = 'Fills empty ' . $player['position'] . ' position';
        }
        // Check if improves weak position
        elseif (in_array($player['position'], $weakPositions)) {
            $currentAvg = $avgPositionRatings[$player['position']] ?? 0;
            if ($player['rating'] > $currentAvg) {
                $score += 50 + ($player['rating'] - $currentAvg);
                $reason = 'Upgrades ' . $player['position'] . ' (current avg: ' . round($currentAvg, 1) . ')';
            }
        }
        // Check if needed position
        elseif (in_array($player['position'], $requiredPositions)) {
            $score += 30;
            $reason = 'Strengthens ' . $player['position'] . ' depth';
        }

        // Bonus for high rating
        $score += $player['rating'] * 0.5;

        // Bonus for good value (rating vs price)
        $valueRatio = $player['rating'] / ($player['value'] / 1000000);
        $score += $valueRatio * 2;

        if ($score > 0) {
            $recommendations[] = [
                'player' => $player,
                'score' => $score,
                'reason' => $reason ?: 'Quality addition to squad',
                'priority' => in_array($player['position'], $emptyPositions) ? 'high' : 
                             (in_array($player['position'], $weakPositions) ? 'medium' : 'low')
            ];
        }
    }

    // Sort by score
    usort($recommendations, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    // Limit to max recommendations
    $recommendations = array_slice($recommendations, 0, $maxRecommendations);

    // Add a small delay to make the progress bar feel more realistic
    // This simulates the time needed for complex AI analysis
    usleep(500000); // 0.5 second delay

    $response = [
        'success' => true,
        'recommendations' => $recommendations,
        'budget' => $newBudget, // Return updated budget
        'cost_paid' => RECOMMENDATION_COST,
        'emptyPositions' => $emptyPositions,
        'weakPositions' => $weakPositions,
        'teamAnalysis' => [
            'totalPlayers' => count(array_filter($currentTeam, fn($p) => $p !== null)),
            'avgRating' => count($avgPositionRatings) > 0 ? 
                round(array_sum($avgPositionRatings) / count($avgPositionRatings), 1) : 0,
            'positionCounts' => $positionCounts
        ]
    ];
    
    debug_performance("Player recommendation generation", $startTime, [
        'user_id' => $userId,
        'recommendations_count' => count($recommendations),
        'empty_positions' => count($emptyPositions),
        'weak_positions' => count($weakPositions),
        'cost_paid' => RECOMMENDATION_COST,
        'new_budget' => $newBudget
    ]);
    
    debug_user_action($userId, "Generated player recommendations", [
        'cost' => RECOMMENDATION_COST,
        'recommendations' => count($recommendations)
    ]);
    
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
