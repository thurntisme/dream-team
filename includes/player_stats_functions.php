<?php
/**
 * Player Statistics Functions
 * 
 * This file contains functions related to player statistics tracking,
 * match ratings, goals, assists, and performance metrics.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Update player statistics after a match
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @param array $players Array of players who played
 * @param string $result Match result (win/draw/loss)
 * @param int $goals_for Goals scored by team
 * @param int $goals_against Goals conceded by team
 * @return bool Success status
 */
if (!function_exists('updatePlayerStatistics')) {
    function updatePlayerStatistics($db, $user_id, $players, $result, $goals_for = 0, $goals_against = 0)
    {
        try {
            foreach ($players as $player) {
                if (!$player || !isset($player['name']))
                    continue;

                $playerId = $player['id'] ?? $player['name'];
                $position = $player['position'] ?? 'Unknown';

                // Generate random match statistics
                $matchRating = generateMatchRating($player, $result);
                $goals = generatePlayerGoals($position, $result);
                $assists = generatePlayerAssists($position, $result);
                $yellowCard = rand(1, 100) <= 8 ? 1 : 0; // 8% chance
                $redCard = rand(1, 100) <= 2 ? 1 : 0; // 2% chance

                // Goalkeeper specific stats
                $cleanSheet = 0;
                $saves = 0;
                if ($position === 'GK') {
                    $cleanSheet = $goals_against === 0 ? 1 : 0;
                    $saves = rand(2, 8); // Random saves for GK
                }

                // Update or insert player statistics
                $stmt = $db->prepare('
                    INSERT INTO player_stats (user_id, player_id, player_name, position, matches_played, goals, assists, yellow_cards, red_cards, total_rating, clean_sheets, saves)
                    VALUES (:user_id, :player_id, :player_name, :position, 1, :goals, :assists, :yellow_cards, :red_cards, :rating, :clean_sheets, :saves)
                    ON CONFLICT(user_id, player_id) DO UPDATE SET
                        matches_played = matches_played + 1,
                        goals = goals + :goals,
                        assists = assists + :assists,
                        yellow_cards = yellow_cards + :yellow_cards,
                        red_cards = red_cards + :red_cards,
                        total_rating = total_rating + :rating,
                        clean_sheets = clean_sheets + :clean_sheets,
                        saves = saves + :saves,
                        updated_at = datetime("now")
                ');

                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':player_id', $playerId, SQLITE3_TEXT);
                $stmt->bindValue(':player_name', $player['name'], SQLITE3_TEXT);
                $stmt->bindValue(':position', $position, SQLITE3_TEXT);
                $stmt->bindValue(':goals', $goals, SQLITE3_INTEGER);
                $stmt->bindValue(':assists', $assists, SQLITE3_INTEGER);
                $stmt->bindValue(':yellow_cards', $yellowCard, SQLITE3_INTEGER);
                $stmt->bindValue(':red_cards', $redCard, SQLITE3_INTEGER);
                $stmt->bindValue(':rating', $matchRating, SQLITE3_FLOAT);
                $stmt->bindValue(':clean_sheets', $cleanSheet, SQLITE3_INTEGER);
                $stmt->bindValue(':saves', $saves, SQLITE3_INTEGER);

                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            error_log("Update player statistics error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Generate match rating for a player based on performance
 * 
 * @param array $player Player data
 * @param string $result Match result
 * @return float Match rating (1.0-10.0 scale)
 */
if (!function_exists('generateMatchRating')) {
    function generateMatchRating($player, $result)
    {
        $baseRating = $player['rating'] ?? 70;
        $fitness = $player['fitness'] ?? 100;
        $form = $player['form'] ?? 7;

        // Base match rating (5-9 range)
        $matchRating = 5.0 + ($baseRating - 50) / 10.0;

        // Fitness impact
        $matchRating += ($fitness - 75) / 25.0;

        // Form impact
        $matchRating += ($form - 5) / 2.0;

        // Result impact
        switch ($result) {
            case 'win':
                $matchRating += rand(0, 15) / 10.0; // 0-1.5 bonus
                break;
            case 'draw':
                $matchRating += rand(-5, 10) / 10.0; // -0.5 to 1.0
                break;
            case 'loss':
                $matchRating -= rand(0, 10) / 10.0; // 0-1.0 penalty
                break;
        }

        // Random factor
        $matchRating += rand(-5, 5) / 10.0;

        // Ensure rating is between 1.0-10.0 and return as float
        return round(max(1.0, min(10.0, $matchRating)), 1);
    }
}

/**
 * Generate goals scored by a player in a match
 * 
 * @param string $position Player position
 * @param string $result Match result
 * @return int Goals scored
 */
if (!function_exists('generatePlayerGoals')) {
    function generatePlayerGoals($position, $result)
    {
        $goalChance = 0;

        // Position-based goal chances
        switch ($position) {
            case 'ST':
                $goalChance = 25; // 25% chance
                break;
            case 'LW':
            case 'RW':
            case 'CAM':
                $goalChance = 15; // 15% chance
                break;
            case 'CM':
            case 'CDM':
                $goalChance = 8; // 8% chance
                break;
            case 'LB':
            case 'RB':
            case 'CB':
                $goalChance = 3; // 3% chance
                break;
            case 'GK':
                $goalChance = 0.5; // 0.5% chance (very rare)
                break;
            default:
                $goalChance = 10;
                break;
        }

        // Result modifier
        if ($result === 'win') {
            $goalChance *= 1.5;
        } elseif ($result === 'loss') {
            $goalChance *= 0.7;
        }

        $goals = 0;

        // Check for first goal
        if (rand(1, 100) <= $goalChance) {
            $goals = 1;

            // Small chance for second goal (much lower)
            if (rand(1, 100) <= $goalChance / 4) {
                $goals = 2;

                // Very small chance for hat-trick
                if (rand(1, 100) <= 2) {
                    $goals = 3;
                }
            }
        }

        return $goals;
    }
}

/**
 * Generate assists by a player in a match
 * 
 * @param string $position Player position
 * @param string $result Match result
 * @return int Assists made
 */
if (!function_exists('generatePlayerAssists')) {
    function generatePlayerAssists($position, $result)
    {
        $assistChance = 0;

        // Position-based assist chances
        switch ($position) {
            case 'CAM':
            case 'CM':
                $assistChance = 20; // 20% chance
                break;
            case 'LW':
            case 'RW':
                $assistChance = 18; // 18% chance
                break;
            case 'ST':
                $assistChance = 12; // 12% chance
                break;
            case 'CDM':
                $assistChance = 10; // 10% chance
                break;
            case 'LB':
            case 'RB':
                $assistChance = 8; // 8% chance
                break;
            case 'CB':
                $assistChance = 4; // 4% chance
                break;
            case 'GK':
                $assistChance = 1; // 1% chance
                break;
            default:
                $assistChance = 10;
                break;
        }

        // Result modifier
        if ($result === 'win') {
            $assistChance *= 1.3;
        } elseif ($result === 'loss') {
            $assistChance *= 0.8;
        }

        $assists = 0;

        // Check for first assist
        if (rand(1, 100) <= $assistChance) {
            $assists = 1;

            // Small chance for second assist
            if (rand(1, 100) <= $assistChance / 3) {
                $assists = 2;
            }
        }

        return $assists;
    }
}