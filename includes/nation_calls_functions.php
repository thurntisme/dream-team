<?php
/**
 * Nation Calls Management Functions
 * 
 * This file contains functions related to national team calls,
 * player selection, rewards, and statistics.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Process nation calls after every 8 matches
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Nation call results
 */
if (!function_exists('processNationCalls')) {
    function processNationCalls($db, $user_id)
    {
        // Get user data
        $stmt = $db->prepare('SELECT matches_played, team, substitutes FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);

        if (!$userData) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $matchesPlayed = $userData['matches_played'] ?? 0;

        // Check if nation calls should be triggered (every 8 matches)
        if ($matchesPlayed > 0 && $matchesPlayed % 8 === 0) {
            // Get all players
            $team = json_decode($userData['team'] ?? '[]', true) ?: [];
            $substitutes = json_decode($userData['substitutes'] ?? '[]', true) ?: [];
            $allPlayers = array_merge(array_filter($team), array_filter($substitutes));

            if (empty($allPlayers)) {
                return ['success' => false, 'message' => 'No players available'];
            }

            // Select best performing players for nation calls
            $calledPlayers = selectPlayersForNationCall($allPlayers);

            if (empty($calledPlayers)) {
                return ['success' => false, 'message' => 'No players selected for nation call'];
            }

            // Calculate budget reward
            $totalReward = 0;
            foreach ($calledPlayers as $player) {
                $reward = calculateNationCallReward($player);
                $totalReward += $reward;
            }

            // Update user budget
            $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :id');
            $stmt->bindValue(':reward', $totalReward, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();

            // Save nation call record
            saveNationCallRecord($db, $user_id, $calledPlayers, $totalReward);

            return [
                'success' => true,
                'called_players' => $calledPlayers,
                'total_reward' => $totalReward,
                'matches_milestone' => $matchesPlayed
            ];
        }

        return ['success' => false, 'message' => 'Nation calls not triggered yet'];
    }
}

/**
 * Select best performing players for nation call
 * 
 * @param array $players All available players
 * @return array Selected players for nation call
 */
if (!function_exists('selectPlayersForNationCall')) {
    function selectPlayersForNationCall($players)
    {
        $eligiblePlayers = [];

        // Calculate performance score for each player
        foreach ($players as $player) {
            if (!$player || !isset($player['rating']))
                continue;

            $performanceScore = calculatePlayerPerformanceScore($player);

            // Only consider players with good performance (score > 70)
            if ($performanceScore > 70) {
                $player['performance_score'] = $performanceScore;
                $eligiblePlayers[] = $player;
            }
        }

        // Sort by performance score (highest first)
        usort($eligiblePlayers, function ($a, $b) {
            return $b['performance_score'] <=> $a['performance_score'];
        });

        // Select top 2-5 players (random within this range)
        $maxPlayers = min(rand(2, 5), count($eligiblePlayers));
        return array_slice($eligiblePlayers, 0, $maxPlayers);
    }
}

/**
 * Calculate player performance score for nation call selection
 * 
 * @param array $player Player data
 * @return float Performance score
 */
if (!function_exists('calculatePlayerPerformanceScore')) {
    function calculatePlayerPerformanceScore($player)
    {
        $baseRating = $player['rating'] ?? 70;
        $fitness = $player['fitness'] ?? 100;
        $form = $player['form'] ?? 7;
        $level = $player['level'] ?? 1;
        $cardLevel = $player['card_level'] ?? 1;

        // Base score from rating
        $score = $baseRating;

        // Fitness bonus (up to +10)
        $score += ($fitness / 100) * 10;

        // Form bonus (up to +15)
        $score += (($form - 5) / 5) * 15;

        // Level bonus (+0.5 per level)
        $score += ($level - 1) * 0.5;

        // Card level bonus (+2 per card level)
        $score += ($cardLevel - 1) * 2;

        // Random factor for variety (-5 to +5)
        $score += rand(-5, 5);

        return max(0, $score);
    }
}

/**
 * Calculate nation call reward for a player
 * 
 * @param array $player Player data
 * @return int Reward amount
 */
if (!function_exists('calculateNationCallReward')) {
    function calculateNationCallReward($player)
    {
        $baseReward = 50000; // €50K base reward
        $rating = $player['rating'] ?? 70;
        $performanceScore = $player['performance_score'] ?? 70;

        // Rating multiplier (higher rated players earn more)
        $ratingMultiplier = 1 + (($rating - 70) / 100);

        // Performance multiplier
        $performanceMultiplier = 1 + (($performanceScore - 70) / 200);

        $totalReward = $baseReward * $ratingMultiplier * $performanceMultiplier;

        return (int) $totalReward;
    }
}

/**
 * Save nation call record to database
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @param array $calledPlayers Called players
 * @param int $totalReward Total reward amount
 * @return bool Success status
 */
if (!function_exists('saveNationCallRecord')) {
    function saveNationCallRecord($db, $user_id, $calledPlayers, $totalReward)
    {
        try {
            if (DB_DRIVER === 'mysql') {
                $stmt = $db->prepare('
                    INSERT INTO nation_calls (user_id, called_players, total_reward, call_date)
                    VALUES (:user_id, :called_players, :total_reward, NOW())
                ');
            } else {
                $stmt = $db->prepare('
                    INSERT INTO nation_calls (user_id, called_players, total_reward, call_date)
                    VALUES (:user_id, :called_players, :total_reward, datetime("now"))
                ');
            }

            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':called_players', json_encode($calledPlayers), SQLITE3_TEXT);
            $stmt->bindValue(':total_reward', $totalReward, SQLITE3_INTEGER);

            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get nation call history for a user
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @param int $limit Number of records to fetch
 * @return array Nation call history
 */
if (!function_exists('getNationCallHistory')) {
    function getNationCallHistory($db, $user_id, $limit = 10)
    {
        try {
            $stmt = $db->prepare('
                SELECT * FROM nation_calls 
                WHERE user_id = :user_id 
                ORDER BY call_date DESC 
                LIMIT :limit
            ');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);

            $result = $stmt->execute();
            $history = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $row['called_players'] = json_decode($row['called_players'], true);
                $row['time_ago'] = getTimeAgo(strtotime($row['call_date']));
                $history[] = $row;
            }

            return $history;
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Get nation call statistics for a user
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Statistics
 */
if (!function_exists('getNationCallStats')) {
    function getNationCallStats($db, $user_id)
    {
        try {
            $stmt = $db->prepare('
                SELECT 
                    COUNT(*) as total_calls,
                    SUM(total_reward) as total_earnings,
                    AVG(total_reward) as avg_earnings,
                    MAX(total_reward) as best_earnings
                FROM nation_calls 
                WHERE user_id = :user_id
            ');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $stats = $result->fetchArray(SQLITE3_ASSOC);

            // Count unique players called
            $stmt = $db->prepare('SELECT called_players FROM nation_calls WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $uniquePlayers = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $players = json_decode($row['called_players'], true);
                foreach ($players as $player) {
                    $uniquePlayers[$player['name']] = true;
                }
            }

            $stats['unique_players_called'] = count($uniquePlayers);
            $stats['total_earnings'] = $stats['total_earnings'] ?? 0;
            $stats['avg_earnings'] = $stats['avg_earnings'] ?? 0;
            $stats['best_earnings'] = $stats['best_earnings'] ?? 0;

            return $stats;
        } catch (Exception $e) {
            return [
                'total_calls' => 0,
                'total_earnings' => 0,
                'avg_earnings' => 0,
                'best_earnings' => 0,
                'unique_players_called' => 0
            ];
        }
    }
}

/**
 * Manually trigger nation calls for testing (admin function)
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Nation call results
 */
if (!function_exists('triggerNationCallsManually')) {
    function triggerNationCallsManually($db, $user_id)
    {
        // Get user data
        $stmt = $db->prepare('SELECT team, substitutes FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);

        if (!$userData) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Get all players
        $team = json_decode($userData['team'] ?? '[]', true) ?: [];
        $substitutes = json_decode($userData['substitutes'] ?? '[]', true) ?: [];
        $allPlayers = array_merge(array_filter($team), array_filter($substitutes));

        if (empty($allPlayers)) {
            return ['success' => false, 'message' => 'No players available'];
        }

        // Select best performing players for nation calls
        $calledPlayers = selectPlayersForNationCall($allPlayers);

        if (empty($calledPlayers)) {
            return ['success' => false, 'message' => 'No players selected for nation call'];
        }

        // Calculate budget reward
        $totalReward = 0;
        foreach ($calledPlayers as $player) {
            $reward = calculateNationCallReward($player);
            $totalReward += $reward;
        }

        // Update user budget
        $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :id');
        $stmt->bindValue(':reward', $totalReward, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Save nation call record
        saveNationCallRecord($db, $user_id, $calledPlayers, $totalReward);

        return [
            'success' => true,
            'called_players' => $calledPlayers,
            'total_reward' => $totalReward,
            'manual_trigger' => true
        ];
    }
}
