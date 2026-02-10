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
        $stmt = $this->db->prepare('
            SELECT 
                u.name, u.email, u.created_at,
                c.club_name, c.formation, c.team, c.budget, c.max_players
            FROM users u
            LEFT JOIN user_club c ON c.user_uuid = u.uuid
            WHERE u.uuid = :uuid
            LIMIT 1
        ');
        if ($stmt !== false) {
            $stmt->bindValue(':uuid', $this->userId, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
        } else {
            $user = null;
        }

        if (!$user) {
            throw new Exception('User not found');
        }

        // Split stored team (full squad) into lineup (first 11) and substitutes (rest)
        $storedTeam = [];
        if (isset($user['team']) && is_string($user['team'])) {
            $decoded = json_decode($user['team'], true);
            if (is_array($decoded)) {
                $storedTeam = $decoded;
            }
        }
        $lineup = array_slice($storedTeam, 0, 11);
        $substitutes = array_slice($storedTeam, 11);

        return [
            'user' => $user,
            'saved_formation' => $user['formation'] ?? '4-4-2',
            'saved_team' => json_encode($lineup),
            'saved_substitutes' => json_encode($substitutes),
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
            $stmt = $this->db->prepare('UPDATE user_club SET max_players = :max_players WHERE user_uuid = (SELECT uuid FROM users WHERE id = :user_id)');
            if ($stmt !== false) {
                $stmt->bindValue(':max_players', $maxPlayers, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $this->userId, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
        return $maxPlayers;
    }

    /**
     * Get total number of clubs for ranking
     */
    public function getTotalClubsCount()
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as total_clubs FROM user_club WHERE club_name IS NOT NULL AND club_name != ""');
        if ($stmt !== false) {
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : ['total_clubs' => 0];
            return (int)($row['total_clubs'] ?? 0);
        }
        return 0;
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
        $combined = [];
        if (is_array($teamData)) {
            $combined = $teamData;
        }
        if (is_array($substitutesData) && count($substitutesData) > 0) {
            if (count($combined) < 11) {
                $combined = array_pad($combined, 11, null);
            } else {
                $combined = array_slice($combined, 0, 11);
            }
            $combined = array_merge($combined, $substitutesData);
        } else {
            if (count($combined) > 11) {
                $combined = array_slice($combined, 0, 11);
            }
        }

        $stmtMax = $this->db->prepare('SELECT max_players FROM user_club WHERE user_uuid = :user_uuid');
        if ($stmtMax !== false) {
            $stmtMax->bindValue(':user_uuid', $this->userId, SQLITE3_TEXT);
            $resMax = $stmtMax->execute();
            $rowMax = $resMax ? $resMax->fetchArray(SQLITE3_ASSOC) : [];
            $maxPlayers = $rowMax['max_players'] ?? DEFAULT_MAX_PLAYERS;
            if (is_numeric($maxPlayers) && $maxPlayers > 0 && count($combined) > (int)$maxPlayers) {
                $combined = array_slice($combined, 0, (int)$maxPlayers);
            }
        }

        $stmt = $this->db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = :user_uuid');
        if ($stmt !== false) {
            $stmt->bindValue(':team', json_encode($combined), SQLITE3_TEXT);
            $stmt->bindValue(':user_uuid', $this->userId, SQLITE3_TEXT);
            return $stmt->execute();
        }
        return false;
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
    public function getUserRanking(int $teamValue): int
    {
        $sql = '
            SELECT uc.team 
            FROM user_club uc
            INNER JOIN users u ON uc.user_uuid = u.uuid
            WHERE uc.club_name IS NOT NULL 
              AND uc.club_name != "" 
              AND u.uuid != :user_uuid
        ';

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return 1;
        }

        $stmt->bindValue(':user_uuid', $this->userId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result) {
            return 1;
        }

        $greaterCount = 0;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($this->getTeamTotalValue($row['team'] ?? '[]') > $teamValue) {
                $greaterCount++;
            }
        }

        return $greaterCount + 1;
    }

    private function getTeamTotalValue($teamJson): int
    {
        if (!is_string($teamJson)) {
            return 0;
        }

        $players = json_decode($teamJson, true);
        if (!is_array($players)) {
            return 0;
        }

        $sum = 0;

        foreach ($players as $player) {
            if (isset($player['value'])) {
                $sum += (int) $player['value'];
            }
        }

        return $sum;
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
        $stmt = $this->db->prepare('SELECT club_level, club_exp FROM user_club WHERE user_uuid = :user_uuid');
        if ($stmt !== false) {
            $stmt->bindValue(':user_uuid', $this->userId, SQLITE3_TEXT);
            $result = $stmt->execute();
            $clubData = $result ? $result->fetchArray(SQLITE3_ASSOC) : [];
        } else {
            $clubData = [];
        }

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
        $startTime = microtime(true);
        debug_info("Starting team data processing", ['user_id' => $this->userId]);

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

        $result = array_merge($userData, [
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

        debug_performance("Team data processing", $startTime, [
            'user_id' => $this->userId,
            'team_value' => $teamValue,
            'player_count' => count(array_filter($teamData ?: [], fn($p) => $p !== null)),
            'ranking' => $ranking
        ]);

        return $result;
    }
}
