<?php
/**
 * Club Management Functions
 * 
 * This file contains functions related to club management, experience points,
 * levels, and club-related utilities.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Calculate experience points required for a specific club level
 * 
 * @param int $level Target level
 * @return int Experience points required
 */
if (!function_exists('getExpRequiredForLevel')) {
    function getExpRequiredForLevel($level)
    {
        if ($level <= 1)
            return 0;

        // Exponential growth: level^2 * 100
        return ($level - 1) * ($level - 1) * 100;
    }
}

/**
 * Calculate club level from experience points
 * 
 * @param int $exp Current experience points
 * @return int Club level
 */
if (!function_exists('getLevelFromExp')) {
    function getLevelFromExp($exp)
    {
        $level = 1;
        while ($level < 50 && getExpRequiredForLevel($level + 1) <= $exp) {
            $level++;
        }
        return $level;
    }
}

/**
 * Add experience points to a user's club
 * 
 * @param int $userId User ID
 * @param int $expGain Experience points to add
 * @param string $reason Reason for experience gain
 * @return array Result with level up information
 */
if (!function_exists('addClubExp')) {
    function addClubExp($userId, $expGain, $reason = '', $db = null)
    {
        $maxRetries = 3;
        $retryDelay = 100000; // 100ms in microseconds
        $shouldCloseDb = false;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($db === null) {
                    $db = getDbConnection();
                    $shouldCloseDb = true;
                }

                // Get current exp and level
                $stmt = $db->prepare('SELECT club_exp, club_level FROM users WHERE id = :user_id');
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $userData = $result->fetchArray(SQLITE3_ASSOC);

                if (!$userData) {
                    return ['success' => false, 'message' => 'User not found'];
                }

                $currentExp = $userData['club_exp'] ?? 0;
                $currentLevel = $userData['club_level'] ?? 1;
                $newExp = $currentExp + $expGain;
                $newLevel = getLevelFromExp($newExp);

                // Update database
                $stmt = $db->prepare('UPDATE users SET club_exp = :exp, club_level = :level WHERE id = :user_id');
                $stmt->bindValue(':exp', $newExp, SQLITE3_INTEGER);
                $stmt->bindValue(':level', $newLevel, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->execute();

                if ($shouldCloseDb) {
                    $db->close();
                }

                $leveledUp = $newLevel > $currentLevel;

                return [
                    'success' => true,
                    'exp_gained' => $expGain,
                    'new_exp' => $newExp,
                    'new_level' => $newLevel,
                    'leveled_up' => $leveledUp,
                    'levels_gained' => $newLevel - $currentLevel,
                    'reason' => $reason
                ];

            } catch (Exception $e) {
                if ($attempt < $maxRetries && strpos($e->getMessage(), 'database is locked') !== false) {
                    // Wait before retrying
                    usleep($retryDelay * $attempt); // Exponential backoff
                    continue;
                }
                return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }

        // If all retries failed
        return ['success' => false, 'message' => 'Database locked after multiple attempts'];
    }
}

/**
 * Get experience progress for current level
 * 
 * @param int $exp Current experience points
 * @param int $level Current level
 * @return array Progress information
 */
if (!function_exists('getExpProgress')) {
    function getExpProgress($exp, $level)
    {
        $currentLevelExp = getExpRequiredForLevel($level);
        $nextLevelExp = getExpRequiredForLevel($level + 1);
        $expInCurrentLevel = $exp - $currentLevelExp;
        $expNeededForNext = $nextLevelExp - $currentLevelExp;

        return [
            'current_level_exp' => $currentLevelExp,
            'next_level_exp' => $nextLevelExp,
            'exp_in_current_level' => $expInCurrentLevel,
            'exp_needed_for_next' => $expNeededForNext,
            'progress_percentage' => $expNeededForNext > 0 ? round(($expInCurrentLevel / $expNeededForNext) * 100, 1) : 100
        ];
    }
}

/**
 * Get club level name
 * 
 * @param int $level Club level
 * @return string Level name
 */
if (!function_exists('getClubLevelName')) {
    function getClubLevelName($level)
    {
        $levelNames = [
            1 => 'Amateur',
            2 => 'Semi-Pro',
            3 => 'Professional',
            4 => 'Division 3',
            5 => 'Division 2',
            6 => 'Division 1',
            7 => 'Premier',
            8 => 'Elite',
            9 => 'Champions',
            10 => 'Legendary'
        ];

        return $levelNames[$level] ?? 'Unknown';
    }
}

/**
 * Get level color for display
 * 
 * @param int $level Club level
 * @return string CSS color class
 */
if (!function_exists('getLevelColor')) {
    function getLevelColor($level)
    {
        if ($level >= 9)
            return 'text-purple-600';
        if ($level >= 7)
            return 'text-yellow-600';
        if ($level >= 5)
            return 'text-blue-600';
        if ($level >= 3)
            return 'text-green-600';
        return 'text-gray-600';
    }
}