<?php

/**
 * Team Controller
 * Handles team management business logic and data operations
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

class TeamController
{
    private $db;
    private $userId;

    public function __construct($userId)
    {
        $this->db = getDbConnection();
        $this->userId = $userId;
    }

    /**
     * Get comprehensive user team data
     */
    public function getUserTeamData()
    {
        $stmt = $this->db->prepare('SELECT name, email, club_name, formation, team, substitutes, budget, max_players, created_at FROM users WHERE id = :id');
        $stmt->bindValue(':id', $this->userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            throw new Exception('User not found');
        }

        return [
            'user' => $user,
            'saved_formation' => $user['formation'] ?? '4-4-2',
            'saved_team' => $user['team'] ?? '[]',
            'saved_substitutes' => $user['substitutes'] ?? '[]',
            'user_budget' => $user['budget'] ?? DEFAULT_BUDGET,
            'max_players' => $user['max_players'] ?? DEFAULT_MAX_PLAYERS
        ];
    }

    /**
     * Initialize max_players for existing users
     */
    public function ensureMaxPlayersSet($maxPlayers)
    {
        if ($maxPlayers === null) {
            $maxPlayers = DEFAULT_MAX_PLAYERS;
            $stmt = $this->db->prepare('UPDATE users SET max_players = :max_players WHERE id = :user_id');
            $stmt->bindValue(':max_players', $maxPlayers, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $this->userId, SQLITE3_INTEGER);
            $stmt->execute();
        }
        return $maxPlayers;
    }

    /**
     * Get total number of clubs for ranking
     */
    public function getTotalClubsCount()
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as total_clubs FROM users WHERE club_name IS NOT NULL AND club_name != ""');
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC)['total_clubs'];
    }

    /**
     * Initialize and update player conditions (fitness and form)
     */
    public function updatePlayerConditions($teamData, $substitutesData)
    {
        $teamUpdated = false;
        $subsUpdated = false;

        // Initialize fitness and form for main team
        if (is_array($teamData)) {
            for ($i = 0; $i < count($teamData); $i++) {
                if ($teamData[$i]) {
                    $originalPlayer = $teamData[$i];
                    $teamData[$i] = initializePlayerCondition($teamData[$i]);
                    if ($teamData[$i] !== $originalPlayer) {
                        $teamUpdated = true;
                    }
                }
            }
        }

        // Initialize fitness and form for substitutes
        if (is_array($substitutesData)) {
            for ($i = 0; $i < count($substitutesData); $i++) {
                if ($substitutesData[$i]) {
                    $originalPlayer = $substitutesData[$i];
                    $substitutesData[$i] = initializePlayerCondition($substitutesData[$i]);
                    if ($substitutesData[$i] !== $originalPlayer) {
                        $subsUpdated = true;
                    }
                }
            }
        }

        return [
            'team_data' => $teamData,
            'substitutes_data' => $substitutesData,
            'team_updated' => $teamUpdated,
            'subs_updated' => $subsUpdated
        ];
    }

    /**
     * Apply staff bonuses to team and substitutes
     */
    public function applyStaffBonuses($teamData, $substitutesData)
    {
        $userStaff = getUserStaff($this->db, $this->userId);
        $teamUpdated = false;

        if (!empty($userStaff)) {
            $teamData = applyStaffBonuses($teamData, $userStaff);
            $substitutesData = applyStaffBonuses($substitutesData, $userStaff);
            $teamUpdated = true;
        }

        return [
            'team_data' => $teamData,
            'substitutes_data' => $substitutesData,
            'team_updated' => $teamUpdated
        ];
    }

    /**
     * Update team and substitutes in database
     */
    public function updateTeamInDatabase($teamData, $substitutesData)
    {
        $stmt = $this->db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
        $stmt->bindValue(':team', json_encode($teamData), SQLITE3_TEXT);
        $stmt->bindValue(':substitutes', json_encode($substitutesData), SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $this->userId, SQLITE3_INTEGER);
        return $stmt->execute();
    }

    /**
     * Calculate total team value
     */
    public function calculateTeamValue($teamData)
    {
        $teamValue = 0;
        if (is_array($teamData)) {
            foreach ($teamData as $player) {
                if ($player && isset($player['value'])) {
                    $teamValue += $player['value'];
                }
            }
        }
        return $teamValue;
    }

    /**
     * Calculate team average rating
     */
    public function calculateTeamAverageRating($teamData)
    {
        $totalRating = 0;
        $playerCount = 0;

        if (is_array($teamData)) {
            foreach ($teamData as $player) {
                if ($player && isset($player['rating'])) {
                    $totalRating += $player['rating'];
                    $playerCount++;
                }
            }
        }

        return $playerCount > 0 ? round($totalRating / $playerCount, 1) : 0;
    }

    /**
     * Get user's current ranking
     */
    public function getUserRanking($teamValue)
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) + 1 as ranking FROM users WHERE club_name IS NOT NULL AND club_name != "" AND id != :user_id AND (
            SELECT COALESCE(SUM(
                CASE 
                    WHEN json_extract(value, "$.value") IS NOT NULL 
                    THEN CAST(json_extract(value, "$.value") AS INTEGER)
                    ELSE 0 
                END
            ), 0)
            FROM json_each(COALESCE(team, "[]"))
        ) > :team_value');

        $stmt->bindValue(':user_id', $this->userId, SQLITE3_INTEGER);
        $stmt->bindValue(':team_value', $teamValue, SQLITE3_INTEGER);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC)['ranking'];
    }

    /**
     * Get club level name based on level
     */
    public function getClubLevelName($level)
    {
        if ($level >= 40)
            return 'Legendary';
        if ($level >= 35)
            return 'Mythical';
        if ($level >= 30)
            return 'Elite Master';
        if ($level >= 25)
            return 'Elite';
        if ($level >= 20)
            return 'Professional Master';
        if ($level >= 15)
            return 'Professional';
        if ($level >= 10)
            return 'Semi-Professional';
        if ($level >= 5)
            return 'Amateur';
        return 'Beginner';
    }

    /**
     * Get level color classes
     */
    public function getLevelColor($level)
    {
        if ($level >= 40)
            return 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white border-yellow-500';
        if ($level >= 35)
            return 'bg-gradient-to-r from-purple-500 to-pink-500 text-white border-purple-500';
        if ($level >= 30)
            return 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white border-indigo-500';
        if ($level >= 25)
            return 'bg-purple-100 text-purple-800 border-purple-200';
        if ($level >= 20)
            return 'bg-indigo-100 text-indigo-800 border-indigo-200';
        if ($level >= 15)
            return 'bg-blue-100 text-blue-800 border-blue-200';
        if ($level >= 10)
            return 'bg-green-100 text-green-800 border-green-200';
        if ($level >= 5)
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }

    /**
     * Get club level data
     */
    public function getClubLevelData()
    {
        $stmt = $this->db->prepare('SELECT club_level, club_exp FROM users WHERE id = :user_id');
        $stmt->bindValue(':user_id', $this->userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $clubData = $result->fetchArray(SQLITE3_ASSOC);

        $club_level = $clubData['club_level'] ?? 1;
        $club_exp = $clubData['club_exp'] ?? 0;
        $level_name = $this->getClubLevelName($club_level);

        return [
            'club_level' => $club_level,
            'club_exp' => $club_exp,
            'level_name' => $level_name
        ];
    }

    /**
     * Process complete team data with all calculations
     */
    public function processTeamData()
    {
        // Get user data
        $userData = $this->getUserTeamData();
        $userData['max_players'] = $this->ensureMaxPlayersSet($userData['max_players']);

        // Parse team data
        $teamData = json_decode($userData['saved_team'], true);
        $substitutesData = json_decode($userData['saved_substitutes'], true);

        // Update player conditions
        $conditionUpdate = $this->updatePlayerConditions($teamData, $substitutesData);
        $teamData = $conditionUpdate['team_data'];
        $substitutesData = $conditionUpdate['substitutes_data'];

        // Apply staff bonuses
        $staffUpdate = $this->applyStaffBonuses($teamData, $substitutesData);
        $teamData = $staffUpdate['team_data'];
        $substitutesData = $staffUpdate['substitutes_data'];

        // Update database if needed
        if ($conditionUpdate['team_updated'] || $conditionUpdate['subs_updated'] || $staffUpdate['team_updated']) {
            $this->updateTeamInDatabase($teamData, $substitutesData);
            $userData['saved_team'] = json_encode($teamData);
            $userData['saved_substitutes'] = json_encode($substitutesData);
        }

        // Calculate team statistics
        $teamValue = $this->calculateTeamValue($teamData);
        $avgRating = $this->calculateTeamAverageRating($teamData);
        $ranking = $this->getUserRanking($teamValue);
        $totalClubs = $this->getTotalClubsCount();

        // Get club level data
        $clubLevelData = $this->getClubLevelData();

        return array_merge($userData, [
            'team_data' => $teamData,
            'substitutes_data' => $substitutesData,
            'team_value' => $teamValue,
            'avg_rating' => $avgRating,
            'ranking' => $ranking,
            'total_clubs' => $totalClubs,
            'club_level' => $clubLevelData['club_level'],
            'club_exp' => $clubLevelData['club_exp'],
            'level_name' => $clubLevelData['level_name']
        ]);
    }
}