<?php
/**
 * Young Player/Academy Management Functions
 * 
 * This file contains functions related to young player management, academy operations,
 * bidding system, and player development.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Generate a random young player for academy
 * 
 * @param int $club_id Club ID
 * @return array Young player data
 */
if (!function_exists('generateYoungPlayer')) {
    function generateYoungPlayer($club_id)
    {
        $positions = ['GK', 'CB', 'LB', 'RB', 'CM', 'LM', 'RM', 'CAM', 'ST', 'LW', 'RW'];
        $position = $positions[array_rand($positions)];

        // Age between 16-19
        $age = rand(16, 19);

        // Current rating based on age (younger = lower current rating)
        $baseCurrentRating = 45 + ($age - 16) * 5; // 45-60 range
        $currentRating = $baseCurrentRating + rand(-5, 10);
        $currentRating = max(35, min(65, $currentRating));

        // Potential rating (always higher than current)
        $potentialRating = $currentRating + rand(15, 35);
        $potentialRating = max(60, min(95, $potentialRating));

        // Value based on potential
        $baseValue = ($potentialRating - 50) * 50000;
        $value = max(100000, $baseValue + rand(-50000, 100000));

        return [
            'club_id' => $club_id,
            'name' => generateRandomPlayerName(),
            'age' => $age,
            'position' => $position,
            'potential_rating' => $potentialRating,
            'current_rating' => $currentRating,
            'development_stage' => 'academy',
            'contract_years' => rand(2, 4),
            'value' => $value,
            'training_focus' => 'balanced'
        ];
    }
}

/**
 * Get young players for a club
 * 
 * @param int $club_id Club ID
 * @param string $stage Development stage filter
 * @return array Young players
 */
if (!function_exists('getClubYoungPlayers')) {
    function getClubYoungPlayers($club_id, $stage = null)
    {
        try {
            $db = getDbConnection();

            $sql = 'SELECT * FROM young_players WHERE club_id = :club_id';
            if ($stage) {
                $sql .= ' AND development_stage = :stage';
            }
            $sql .= ' ORDER BY age ASC, potential_rating DESC';

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
            if ($stage) {
                $stmt->bindValue(':stage', $stage, SQLITE3_TEXT);
            }

            $result = $stmt->execute();
            $players = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $players[] = $row;
            }

            $db->close();
            return $players;
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Promote young player to main team
 * 
 * @param int $young_player_id Young player ID
 * @return bool Success status
 */
if (!function_exists('promoteYoungPlayer')) {
    function promoteYoungPlayer($young_player_id)
    {
        try {
            $db = getDbConnection();

            // Get young player data
            $stmt = $db->prepare('SELECT * FROM young_players WHERE id = :id');
            $stmt->bindValue(':id', $young_player_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $youngPlayer = $result->fetchArray(SQLITE3_ASSOC);

            if (!$youngPlayer) {
                $db->close();
                return false;
            }

            // Create main team player data
            $playerData = [
                'id' => 'yp_' . $young_player_id,
                'name' => $youngPlayer['name'],
                'position' => $youngPlayer['position'],
                'rating' => $youngPlayer['current_rating'],
                'potential' => $youngPlayer['potential_rating'],
                'age' => $youngPlayer['age'],
                'value' => $youngPlayer['value'],
                'fitness' => 100,
                'form' => 50,
                'contract_years' => $youngPlayer['contract_years'],
                'is_youth_graduate' => true
            ];

            // Get user's current team
            $stmt = $db->prepare('SELECT team FROM users WHERE id = :id');
            $stmt->bindValue(':id', $youngPlayer['club_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $userData = $result->fetchArray(SQLITE3_ASSOC);

            $team = json_decode($userData['team'] ?? '[]', true);
            $team[] = $playerData;

            // Update user's team
            $stmt = $db->prepare('UPDATE users SET team = :team WHERE id = :id');
            $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
            $stmt->bindValue(':id', $youngPlayer['club_id'], SQLITE3_INTEGER);
            $stmt->execute();

            // Update young player status
            $stmt = $db->prepare(DB_DRIVER === 'mysql' ? 'UPDATE young_players SET development_stage = "promoted", promoted_at = NOW() WHERE id = :id' : 'UPDATE young_players SET development_stage = "promoted", promoted_at = datetime("now") WHERE id = :id');
            $stmt->bindValue(':id', $young_player_id, SQLITE3_INTEGER);
            $stmt->execute();

            $db->close();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Create bid for young player
 * 
 * @param int $young_player_id Young player ID
 * @param int $bidder_club_id Bidder club ID
 * @param int $bid_amount Bid amount
 * @return bool Success status
 */
if (!function_exists('createYoungPlayerBid')) {
    function createYoungPlayerBid($young_player_id, $bidder_club_id, $bid_amount)
    {
        try {
            $db = getDbConnection();

            // Get young player data
            $stmt = $db->prepare('SELECT club_id FROM young_players WHERE id = :id AND development_stage = "academy"');
            $stmt->bindValue(':id', $young_player_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $youngPlayer = $result->fetchArray(SQLITE3_ASSOC);

            if (!$youngPlayer || $youngPlayer['club_id'] == $bidder_club_id) {
                $db->close();
                return false;
            }

            // Check if bidder has enough budget
            $stmt = $db->prepare('SELECT budget FROM users WHERE id = :id');
            $stmt->bindValue(':id', $bidder_club_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $bidderData = $result->fetchArray(SQLITE3_ASSOC);

            if (!$bidderData || $bidderData['budget'] < $bid_amount) {
                $db->close();
                return false;
            }

            // Create bid (expires in 48 hours)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));

            $stmt = $db->prepare('INSERT INTO young_player_bids (young_player_id, bidder_club_id, owner_club_id, bid_amount, expires_at) VALUES (:young_player_id, :bidder_club_id, :owner_club_id, :bid_amount, :expires_at)');
            $stmt->bindValue(':young_player_id', $young_player_id, SQLITE3_INTEGER);
            $stmt->bindValue(':bidder_club_id', $bidder_club_id, SQLITE3_INTEGER);
            $stmt->bindValue(':owner_club_id', $youngPlayer['club_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':bid_amount', $bid_amount, SQLITE3_INTEGER);
            $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_TEXT);

            $result = $stmt->execute();
            $db->close();

            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get pending bids for a club's young players
 * 
 * @param int $club_id Club ID
 * @return array Pending bids
 */
if (!function_exists('getClubYoungPlayerBids')) {
    function getClubYoungPlayerBids($club_id)
    {
        try {
            $db = getDbConnection();

            if (DB_DRIVER === 'mysql') {
                $stmt = $db->prepare('
                    SELECT b.*, yp.name as player_name, yp.position, yp.age, yp.potential_rating, 
                           u.club_name as bidder_club_name, u.name as bidder_name
                    FROM young_player_bids b
                    JOIN young_players yp ON b.young_player_id = yp.id
                    JOIN users u ON b.bidder_club_id = u.id
                    WHERE b.owner_club_id = :club_id AND b.status = "pending" AND b.expires_at > NOW()
                    ORDER BY b.created_at DESC
                ');
            } else {
                $stmt = $db->prepare('
                    SELECT b.*, yp.name as player_name, yp.position, yp.age, yp.potential_rating, 
                           u.club_name as bidder_club_name, u.name as bidder_name
                    FROM young_player_bids b
                    JOIN young_players yp ON b.young_player_id = yp.id
                    JOIN users u ON b.bidder_club_id = u.id
                    WHERE b.owner_club_id = :club_id AND b.status = "pending" AND b.expires_at > datetime("now")
                    ORDER BY b.created_at DESC
                ');
            }
            $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);

            $result = $stmt->execute();
            $bids = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $bids[] = $row;
            }

            $db->close();
            return $bids;
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Accept or reject young player bid
 * 
 * @param int $bid_id Bid ID
 * @param string $action 'accept' or 'reject'
 * @param int $club_id Club ID (for verification)
 * @return bool Success status
 */
if (!function_exists('processYoungPlayerBid')) {
    function processYoungPlayerBid($bid_id, $action, $club_id)
    {
        try {
            $db = getDbConnection();

            // Get bid data
            $stmt = $db->prepare('
                SELECT b.*, yp.name, yp.position, yp.age, yp.potential_rating, yp.current_rating, yp.value
                FROM young_player_bids b
                JOIN young_players yp ON b.young_player_id = yp.id
                WHERE b.id = :bid_id AND b.owner_club_id = :club_id AND b.status = "pending"
            ');
            $stmt->bindValue(':bid_id', $bid_id, SQLITE3_INTEGER);
            $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $bid = $result->fetchArray(SQLITE3_ASSOC);

            if (!$bid) {
                $db->close();
                return false;
            }

            if ($action === 'accept') {
                // Transfer young player to bidder
                $stmt = $db->prepare('UPDATE young_players SET club_id = :new_club_id WHERE id = :id');
                $stmt->bindValue(':new_club_id', $bid['bidder_club_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':id', $bid['young_player_id'], SQLITE3_INTEGER);
                $stmt->execute();

                // Update budgets
                $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :id');
                $stmt->bindValue(':amount', $bid['bid_amount'], SQLITE3_INTEGER);
                $stmt->bindValue(':id', $club_id, SQLITE3_INTEGER);
                $stmt->execute();

                $stmt = $db->prepare('UPDATE users SET budget = budget - :amount WHERE id = :id');
                $stmt->bindValue(':amount', $bid['bid_amount'], SQLITE3_INTEGER);
                $stmt->bindValue(':id', $bid['bidder_club_id'], SQLITE3_INTEGER);
                $stmt->execute();
            }

            // Update bid status
            $stmt = $db->prepare('UPDATE young_player_bids SET status = :status WHERE id = :id');
            $stmt->bindValue(':status', $action === 'accept' ? 'accepted' : 'rejected', SQLITE3_TEXT);
            $stmt->bindValue(':id', $bid_id, SQLITE3_INTEGER);
            $stmt->execute();

            $db->close();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get available young players from other clubs for bidding
 * 
 * @param int $club_id Current club ID (to exclude own players)
 * @return array Available young players
 */
if (!function_exists('getAvailableYoungPlayers')) {
    function getAvailableYoungPlayers($club_id)
    {
        try {
            $db = getDbConnection();

            $stmt = $db->prepare('
                SELECT yp.*, u.club_name as owner_club_name
                FROM young_players yp
                JOIN users u ON yp.club_id = u.id
                WHERE yp.club_id != :club_id AND yp.development_stage = "academy"
                ORDER BY yp.potential_rating DESC, yp.age ASC
            ');
            $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);

            $result = $stmt->execute();
            $players = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $players[] = $row;
            }

            $db->close();
            return $players;
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Process weekly young player development
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Development results
 */
if (!function_exists('processWeeklyYoungPlayerDevelopment')) {
    function processWeeklyYoungPlayerDevelopment($db, $user_id)
    {
        $results = [
            'players_developed' => 0,
            'players_promoted' => 0,
            'development_details' => []
        ];

        try {
            // Get all academy players for the user
            $stmt = $db->prepare('SELECT * FROM young_players WHERE club_id = :club_id AND development_stage = "academy"');
            $stmt->bindValue(':club_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();

            while ($player = $result->fetchArray(SQLITE3_ASSOC)) {
                $development = [];
                $development['name'] = $player['name'];
                $development['old_rating'] = $player['current_rating'];

                // Calculate development based on training focus and age
                $developmentRate = calculateDevelopmentRate($player);
                $ratingIncrease = rand(0, $developmentRate);

                // Apply development
                $newRating = min($player['current_rating'] + $ratingIncrease, $player['potential_rating']);
                $development['new_rating'] = $newRating;
                $development['improvement'] = $newRating - $player['current_rating'];

                // Age the player (weekly aging - 1/52 of a year)
                $ageIncrease = rand(0, 1) / 52; // Very small chance of aging up

                // Update player in database
                $stmt2 = $db->prepare('UPDATE young_players SET current_rating = :rating WHERE id = :id');
                $stmt2->bindValue(':rating', $newRating, SQLITE3_INTEGER);
                $stmt2->bindValue(':id', $player['id'], SQLITE3_INTEGER);
                $stmt2->execute();

                if ($development['improvement'] > 0) {
                    $results['players_developed']++;
                }

                // Check if player should be auto-promoted (high rating + age)
                if ($newRating >= 70 && $player['age'] >= 18) {
                    if (rand(1, 100) <= 20) { // 20% chance of auto-promotion
                        promoteYoungPlayer($player['id']);
                        $development['promoted'] = true;
                        $results['players_promoted']++;
                    }
                }

                $results['development_details'][] = $development;
            }

        } catch (Exception $e) {
            // Log error but don't break the maintenance process
            error_log("Young player development error: " . $e->getMessage());
        }

        return $results;
    }
}

/**
 * Calculate development rate for young player
 * 
 * @param array $player Young player data
 * @return int Maximum development points per week
 */
if (!function_exists('calculateDevelopmentRate')) {
    function calculateDevelopmentRate($player)
    {
        $baseRate = 2; // Base development points

        // Age factor (younger players develop faster)
        if ($player['age'] <= 17) {
            $ageBonus = 2;
        } elseif ($player['age'] <= 18) {
            $ageBonus = 1;
        } else {
            $ageBonus = 0;
        }

        // Training focus bonus
        $trainingBonus = 0;
        switch ($player['training_focus']) {
            case 'technical':
            case 'physical':
            case 'mental':
                $trainingBonus = 1;
                break;
            case 'balanced':
            default:
                $trainingBonus = 0;
                break;
        }

        // Potential factor (higher potential = faster development)
        $potentialBonus = 0;
        if ($player['potential_rating'] >= 85) {
            $potentialBonus = 2;
        } elseif ($player['potential_rating'] >= 75) {
            $potentialBonus = 1;
        }

        // Diminishing returns as player approaches potential
        $potentialGap = $player['potential_rating'] - $player['current_rating'];
        if ($potentialGap < 5) {
            $baseRate = max(1, $baseRate - 1);
        }

        return $baseRate + $ageBonus + $trainingBonus + $potentialBonus;
    }
}
