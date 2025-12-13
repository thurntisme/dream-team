<?php
/**
 * Helper Functions for Dream Team Application
 * 
 * This file contains commonly used helper functions to avoid redeclaration errors
 * and provide a centralized location for utility functions.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

// Include staff functions
require_once __DIR__ . '/staff_functions.php';

/**
 * Format market value for display
 * 
 * @param int $value The value to format
 * @return string Formatted value with currency symbol
 */
if (!function_exists('formatMarketValue')) {
    function formatMarketValue($value)
    {
        if ($value >= 1000000) {
            return '€' . number_format($value / 1000000, 1) . 'M';
        } elseif ($value >= 1000) {
            return '€' . number_format($value / 1000, 0) . 'K';
        } else {
            return '€' . number_format($value, 0);
        }
    }
}

/**
 * Calculate player value based on rating and position
 * 
 * @param array $player Player data array
 * @return int Calculated player value
 */
if (!function_exists('calculatePlayerValue')) {
    function calculatePlayerValue($player)
    {
        $rating = $player['rating'] ?? 50;
        $position = $player['position'] ?? 'CM';

        // Base value calculation
        $base_value = $rating * 100000;

        // Position multipliers
        $position_multipliers = [
            'GK' => 0.8,
            'CB' => 0.9,
            'LB' => 0.95,
            'RB' => 0.95,
            'CDM' => 1.0,
            'CM' => 1.0,
            'CAM' => 1.1,
            'LW' => 1.15,
            'RW' => 1.15,
            'ST' => 1.2
        ];

        $multiplier = $position_multipliers[$position] ?? 1.0;

        return (int) ($base_value * $multiplier);
    }
}

/**
 * Get position display name
 * 
 * @param string $position Position code
 * @return string Full position name
 */
if (!function_exists('getPositionName')) {
    function getPositionName($position)
    {
        $positions = [
            'GK' => 'Goalkeeper',
            'CB' => 'Centre Back',
            'LB' => 'Left Back',
            'RB' => 'Right Back',
            'CDM' => 'Defensive Midfielder',
            'CM' => 'Central Midfielder',
            'CAM' => 'Attacking Midfielder',
            'LW' => 'Left Winger',
            'RW' => 'Right Winger',
            'ST' => 'Striker'
        ];

        return $positions[$position] ?? $position;
    }
}

/**
 * Generate random player name
 * 
 * @return string Random player name
 */
if (!function_exists('generateRandomPlayerName')) {
    function generateRandomPlayerName()
    {
        $first_names = [
            'Alex',
            'Bruno',
            'Carlos',
            'David',
            'Eduardo',
            'Fernando',
            'Gabriel',
            'Hugo',
            'Ivan',
            'João',
            'Kevin',
            'Lucas',
            'Miguel',
            'Nicolas',
            'Oscar',
            'Pedro',
            'Rafael',
            'Samuel',
            'Thiago',
            'Victor',
            'William',
            'Xavier',
            'Yuri',
            'Zack'
        ];

        $last_names = [
            'Silva',
            'Santos',
            'Oliveira',
            'Souza',
            'Rodriguez',
            'Fernandez',
            'Lopez',
            'Martinez',
            'Garcia',
            'Gonzalez',
            'Perez',
            'Sanchez',
            'Ramirez',
            'Cruz',
            'Flores',
            'Torres',
            'Rivera',
            'Gomez',
            'Diaz',
            'Morales',
            'Jimenez',
            'Herrera'
        ];

        return $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
    }
}

/**
 * Sanitize and validate input
 * 
 * @param mixed $input Input to sanitize
 * @param string $type Type of validation (string, int, email, etc.)
 * @return mixed Sanitized input
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input, $type = 'string')
    {
        switch ($type) {
            case 'int':
                return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
}

/**
 * Generate secure random string
 * 
 * @param int $length Length of the string
 * @return string Random string
 */
if (!function_exists('generateRandomString')) {
    function generateRandomString($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }
}

/**
 * Generate UUID v4
 * 
 * @return string UUID string
 */
if (!function_exists('generateUUID')) {
    function generateUUID()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Check if user has a valid club name
 * 
 * @return bool True if club name exists and is not empty, false otherwise
 */
if (!function_exists('hasClubName')) {
    function hasClubName()
    {
        return isset($_SESSION['club_name']) && !empty(trim($_SESSION['club_name']));
    }
}

/**
 * Check if session is valid and not expired
 * 
 * @return bool True if session is valid, false otherwise
 */
if (!function_exists('isSessionValid')) {
    function isSessionValid()
    {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check if session has expiration time set
        if (!isset($_SESSION['expire_time'])) {
            return false;
        }

        // Check if session has expired
        if (time() > $_SESSION['expire_time']) {
            return false;
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();

        return true;
    }
}

/**
 * Validate session and redirect to login if invalid
 * 
 * @param string $current_page Current page identifier
 */
if (!function_exists('validateSession')) {
    function validateSession($current_page = '')
    {
        // Don't validate on login/install pages
        if ($current_page === 'index' || $current_page === 'install') {
            return;
        }

        // Check if session is valid
        if (!isSessionValid()) {
            // Clear invalid session
            session_unset();
            session_destroy();

            // Redirect to login
            header('Location: index.php');
            exit;
        }
    }
}

/**
 * Require authentication - redirect to login if not authenticated
 * This should be called on pages that require user authentication
 * 
 * @param string $current_page Current page identifier to avoid redirect loops
 */
if (!function_exists('requireAuth')) {
    function requireAuth($current_page = '')
    {
        // Validate session first
        validateSession($current_page);

        // Don't redirect if we're already on login/install pages
        if ($current_page === 'index' || $current_page === 'install') {
            return;
        }

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
    }
}

/**
 * Require club name - redirect to welcome page if club name is empty
 * This should be called on pages that require a club name to function properly
 * 
 * @param string $current_page Current page identifier to avoid redirect loops
 */
if (!function_exists('requireClubName')) {
    function requireClubName($current_page = '')
    {
        // First require authentication
        requireAuth($current_page);

        // Don't redirect if we're already on welcome page or login pages
        if ($current_page === 'welcome' || $current_page === 'index' || $current_page === 'install') {
            return;
        }

        // Check if club name is empty or not set
        if (!hasClubName()) {
            header('Location: welcome.php');
            exit;
        }
    }
}

/**
 * Redirect with message
 * 
 * @param string $url URL to redirect to
 * @param string $message Optional message
 * @param string $type Message type (success, error, info)
 */
if (!function_exists('redirectWithMessage')) {
    function redirectWithMessage($url, $message = '', $type = 'info')
    {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message data or null
 */
if (!function_exists('getFlashMessage')) {
    function getFlashMessage()
    {
        if (isset($_SESSION['flash_message'])) {
            $message = [
                'message' => $_SESSION['flash_message'],
                'type' => $_SESSION['flash_type'] ?? 'info'
            ];
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            return $message;
        }
        return null;
    }
}

/**
 * Get session information
 * 
 * @return array Session information
 */
if (!function_exists('getSessionInfo')) {
    function getSessionInfo()
    {
        if (!isLoggedIn()) {
            return null;
        }

        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_name' => $_SESSION['user_name'] ?? null,
            'club_name' => $_SESSION['club_name'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null,
            'expire_time' => $_SESSION['expire_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'time_until_expiry' => isset($_SESSION['expire_time']) ? $_SESSION['expire_time'] - time() : null,
            'is_valid' => isSessionValid()
        ];
    }
}

/**
 * Calculate club level based on team quality
 * 
 * @param array $team Team array
 * @return int Club level (1-10)
 */
if (!function_exists('calculateClubLevel')) {
    function calculateClubLevel($team)
    {
        if (!is_array($team)) {
            return 1;
        }

        $totalRating = 0;
        $playerCount = 0;

        foreach ($team as $player) {
            if ($player && isset($player['rating'])) {
                $totalRating += $player['rating'];
                $playerCount++;
            }
        }

        if ($playerCount === 0) {
            return 1;
        }

        $averageRating = $totalRating / $playerCount;

        // Convert average rating to club level (1-10)
        if ($averageRating >= 90)
            return 10;
        if ($averageRating >= 85)
            return 9;
        if ($averageRating >= 80)
            return 8;
        if ($averageRating >= 75)
            return 7;
        if ($averageRating >= 70)
            return 6;
        if ($averageRating >= 65)
            return 5;
        if ($averageRating >= 60)
            return 4;
        if ($averageRating >= 55)
            return 3;
        if ($averageRating >= 50)
            return 2;
        return 1;
    }
}

/**
 * Calculate team value
 * 
 * @param array $team Team array
 * @return int Total team value
 */
if (!function_exists('calculateTeamValue')) {
    function calculateTeamValue($team)
    {
        if (!is_array($team)) {
            return 0;
        }

        $totalValue = 0;
        foreach ($team as $player) {
            if ($player && isset($player['value'])) {
                $totalValue += $player['value'];
            }
        }

        return $totalValue;
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

/**
 * Initialize player fitness and form if not set
 * 
 * @param array $player Player data
 * @return array Player data with fitness and form
 */
if (!function_exists('initializePlayerCondition')) {
    function initializePlayerCondition($player)
    {
        if (!isset($player['fitness'])) {
            $player['fitness'] = rand(75, 100); // Start with good fitness
        }
        if (!isset($player['form'])) {
            $player['form'] = rand(6, 8); // Start with decent form (1-10 scale)
        }
        if (!isset($player['matches_played'])) {
            $player['matches_played'] = 0;
        }
        if (!isset($player['last_match_date'])) {
            $player['last_match_date'] = null;
        }
        if (!isset($player['contract_matches'])) {
            $player['contract_matches'] = rand(15, 50); // Contract for 15-50 matches
        }
        if (!isset($player['contract_matches_remaining'])) {
            $player['contract_matches_remaining'] = $player['contract_matches'];
        }
        if (!isset($player['level'])) {
            $player['level'] = 1; // Default level for players joining the club
        }
        if (!isset($player['experience'])) {
            $player['experience'] = 0; // Experience points for leveling up
        }
        if (!isset($player['card_level'])) {
            $player['card_level'] = 1; // Default card level for players joining the club
        }
        if (!isset($player['base_salary'])) {
            // Calculate base salary from player value (weekly salary = 0.1% of value)
            $player['base_salary'] = max(1000, ($player['value'] ?? 1000000) * 0.001);
        }
        return $player;
    }
}

/**
 * Update player fitness based on activity
 * 
 * @param array $player Player data
 * @param bool $played_match Whether player played a match
 * @param int $days_since_last_match Days since last match
 * @return array Updated player data
 */
if (!function_exists('updatePlayerFitness')) {
    function updatePlayerFitness($player, $played_match = false, $days_since_last_match = 0)
    {
        $fitness = $player['fitness'] ?? 100;

        if ($played_match) {
            // Fitness decreases after playing
            $fitness -= rand(5, 15);
        } else {
            // Fitness recovers when resting
            $recovery = min(3 + ($days_since_last_match * 2), 10);
            $fitness += $recovery;
        }

        // Keep fitness between 0 and 100
        $player['fitness'] = max(0, min(100, $fitness));

        return $player;
    }
}

/**
 * Update player form based on performance
 * 
 * @param array $player Player data
 * @param string $performance Performance rating (excellent, good, average, poor)
 * @return array Updated player data
 */
if (!function_exists('updatePlayerForm')) {
    function updatePlayerForm($player, $performance = 'average')
    {
        $form = $player['form'] ?? 7;

        switch ($performance) {
            case 'excellent':
                $form += rand(1, 2);
                break;
            case 'good':
                $form += rand(0, 1);
                break;
            case 'average':
                $form += rand(-1, 1) * 0.5;
                break;
            case 'poor':
                $form -= rand(1, 2);
                break;
        }

        // Keep form between 1 and 10
        $player['form'] = max(1, min(10, $form));

        return $player;
    }
}

/**
 * Get fitness status text and color
 * 
 * @param int $fitness Fitness level (0-100)
 * @return array Status info with text and color
 */
if (!function_exists('getFitnessStatus')) {
    function getFitnessStatus($fitness)
    {
        if ($fitness >= 90) {
            return ['text' => 'Excellent', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
        } elseif ($fitness >= 75) {
            return ['text' => 'Good', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'];
        } elseif ($fitness >= 60) {
            return ['text' => 'Average', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'];
        } elseif ($fitness >= 40) {
            return ['text' => 'Poor', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100'];
        } else {
            return ['text' => 'Injured', 'color' => 'text-red-600', 'bg' => 'bg-red-100'];
        }
    }
}

/**
 * Get form status text and color
 * 
 * @param float $form Form level (1-10)
 * @return array Status info with text and color
 */
if (!function_exists('getFormStatus')) {
    function getFormStatus($form)
    {
        if ($form >= 8.5) {
            return ['text' => 'Superb', 'color' => 'text-purple-600', 'bg' => 'bg-purple-100'];
        } elseif ($form >= 7.5) {
            return ['text' => 'Excellent', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
        } elseif ($form >= 6.5) {
            return ['text' => 'Good', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'];
        } elseif ($form >= 5.5) {
            return ['text' => 'Average', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'];
        } elseif ($form >= 4) {
            return ['text' => 'Poor', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100'];
        } else {
            return ['text' => 'Terrible', 'color' => 'text-red-600', 'bg' => 'bg-red-100'];
        }
    }
}

/**
 * Calculate effective player rating based on fitness, form, level, and card level
 * 
 * @param array $player Player data
 * @return int Effective rating
 */
if (!function_exists('getEffectiveRating')) {
    function getEffectiveRating($player)
    {
        $base_rating = $player['rating'] ?? 70;
        $fitness = $player['fitness'] ?? 100;
        $form = $player['form'] ?? 7;
        $level = $player['level'] ?? 1;
        $card_level = $player['card_level'] ?? 1;

        // Fitness affects rating (0.5-1.0 multiplier)
        $fitness_multiplier = 0.5 + ($fitness / 200);

        // Form affects rating (-5 to +5 points)
        $form_bonus = ($form - 7) * 0.7;

        // Level affects rating (+0.5 points per level above 1)
        $level_bonus = ($level - 1) * 0.5;

        // Card level affects rating (+1 point per card level above 1)
        $card_level_bonus = ($card_level - 1) * 1.0;

        $effective_rating = ($base_rating * $fitness_multiplier) + $form_bonus + $level_bonus + $card_level_bonus;

        return max(1, min(99, round($effective_rating)));
    }
}

/**
 * Calculate experience required for next level
 * 
 * @param int $level Current level
 * @return int Experience required for next level
 */
if (!function_exists('getExperienceForLevel')) {
    function getExperienceForLevel($level)
    {
        // Experience required increases exponentially: level * 100 + (level-1) * 50
        return $level * 100 + ($level - 1) * 50;
    }
}

/**
 * Calculate total experience required to reach a specific level
 * 
 * @param int $target_level Target level
 * @return int Total experience required
 */
if (!function_exists('getTotalExperienceForLevel')) {
    function getTotalExperienceForLevel($target_level)
    {
        $total = 0;
        for ($i = 1; $i < $target_level; $i++) {
            $total += getExperienceForLevel($i + 1);
        }
        return $total;
    }
}

/**
 * Add experience to player and handle level ups
 * 
 * @param array $player Player data
 * @param int $experience Experience points to add
 * @return array Updated player data with level up information
 */
if (!function_exists('addPlayerExperience')) {
    function addPlayerExperience($player, $experience)
    {
        $current_level = $player['level'] ?? 1;
        $current_experience = $player['experience'] ?? 0;

        $new_experience = $current_experience + $experience;
        $new_level = $current_level;

        // Check for level ups
        while ($new_level < 50) { // Max level 50
            $required_exp = getExperienceForLevel($new_level + 1);
            $total_required = getTotalExperienceForLevel($new_level + 1);

            if ($new_experience >= $total_required) {
                $new_level++;
            } else {
                break;
            }
        }

        $player['experience'] = $new_experience;
        $player['level'] = $new_level;

        // Add level up information if leveled up
        if ($new_level > $current_level) {
            $player['level_up_info'] = [
                'previous_level' => $current_level,
                'new_level' => $new_level,
                'levels_gained' => $new_level - $current_level
            ];
        }

        return $player;
    }
}

/**
 * Get player level status information
 * 
 * @param array $player Player data
 * @return array Level status with progress information
 */
if (!function_exists('getPlayerLevelStatus')) {
    function getPlayerLevelStatus($player)
    {
        $level = $player['level'] ?? 1;
        $experience = $player['experience'] ?? 0;

        if ($level >= 50) {
            return [
                'level' => $level,
                'experience' => $experience,
                'experience_for_next' => 0,
                'experience_progress' => 0,
                'progress_percentage' => 100,
                'is_max_level' => true
            ];
        }

        $total_required_current = getTotalExperienceForLevel($level);
        $total_required_next = getTotalExperienceForLevel($level + 1);
        $experience_for_next = $total_required_next - $experience;
        $experience_in_current_level = $experience - $total_required_current;
        $experience_needed_for_level = $total_required_next - $total_required_current;

        $progress_percentage = $experience_needed_for_level > 0
            ? ($experience_in_current_level / $experience_needed_for_level) * 100
            : 0;

        return [
            'level' => $level,
            'experience' => $experience,
            'experience_for_next' => $experience_for_next,
            'experience_progress' => $experience_in_current_level,
            'experience_needed' => $experience_needed_for_level,
            'progress_percentage' => min(100, max(0, $progress_percentage)),
            'is_max_level' => false
        ];
    }
}

/**
 * Get level display information
 * 
 * @param int $level Player level
 * @return array Level display info with colors and text
 */
if (!function_exists('getLevelDisplayInfo')) {
    function getLevelDisplayInfo($level)
    {
        if ($level >= 40) {
            return [
                'text' => 'Legendary',
                'color' => 'text-purple-600',
                'bg' => 'bg-purple-100',
                'border' => 'border-purple-200'
            ];
        } elseif ($level >= 30) {
            return [
                'text' => 'Elite',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-200'
            ];
        } elseif ($level >= 20) {
            return [
                'text' => 'Expert',
                'color' => 'text-blue-600',
                'bg' => 'bg-blue-100',
                'border' => 'border-blue-200'
            ];
        } elseif ($level >= 10) {
            return [
                'text' => 'Professional',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'border' => 'border-green-200'
            ];
        } elseif ($level >= 5) {
            return [
                'text' => 'Experienced',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-200'
            ];
        } else {
            return [
                'text' => 'Rookie',
                'color' => 'text-gray-600',
                'bg' => 'bg-gray-100',
                'border' => 'border-gray-200'
            ];
        }
    }
}

/**
 * Calculate card level upgrade cost
 * 
 * @param int $current_level Current card level
 * @param int $player_value Player's market value
 * @return int Upgrade cost
 */
if (!function_exists('getCardLevelUpgradeCost')) {
    function getCardLevelUpgradeCost($current_level, $player_value)
    {
        // Base cost increases exponentially with level
        $base_cost = $current_level * 500000; // €0.5M per level

        // Player value multiplier (higher value players cost more to upgrade)
        $value_multiplier = 1 + ($player_value / 50000000); // +1 for every €50M value

        return (int) ($base_cost * $value_multiplier);
    }
}

/**
 * Get card level display information
 * 
 * @param int $card_level Player card level
 * @return array Card level display info with colors and text
 */
if (!function_exists('getCardLevelDisplayInfo')) {
    function getCardLevelDisplayInfo($card_level)
    {
        if ($card_level >= 10) {
            return [
                'text' => 'Diamond',
                'color' => 'text-cyan-600',
                'bg' => 'bg-cyan-100',
                'border' => 'border-cyan-200',
                'icon' => 'diamond'
            ];
        } elseif ($card_level >= 8) {
            return [
                'text' => 'Platinum',
                'color' => 'text-purple-600',
                'bg' => 'bg-purple-100',
                'border' => 'border-purple-200',
                'icon' => 'star'
            ];
        } elseif ($card_level >= 6) {
            return [
                'text' => 'Gold',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-200',
                'icon' => 'award'
            ];
        } elseif ($card_level >= 4) {
            return [
                'text' => 'Silver',
                'color' => 'text-gray-600',
                'bg' => 'bg-gray-100',
                'border' => 'border-gray-200',
                'icon' => 'medal'
            ];
        } elseif ($card_level >= 2) {
            return [
                'text' => 'Bronze',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-200',
                'icon' => 'shield'
            ];
        } else {
            return [
                'text' => 'Basic',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'border' => 'border-green-200',
                'icon' => 'user'
            ];
        }
    }
}

/**
 * Calculate player weekly salary based on card level
 * 
 * @param array $player Player data
 * @return int Weekly salary
 */
if (!function_exists('calculatePlayerSalary')) {
    function calculatePlayerSalary($player)
    {
        $base_salary = $player['base_salary'] ?? max(1000, ($player['value'] ?? 1000000) * 0.001);
        $card_level = $player['card_level'] ?? 1;

        // Salary increases by 20% per card level above 1
        $salary_multiplier = 1 + (($card_level - 1) * 0.2);

        return (int) ($base_salary * $salary_multiplier);
    }
}

/**
 * Get card level benefits
 * 
 * @param int $card_level Player card level
 * @return array Benefits information
 */
if (!function_exists('getCardLevelBenefits')) {
    function getCardLevelBenefits($card_level)
    {
        $rating_bonus = ($card_level - 1) * 1.0;
        $fitness_bonus = ($card_level - 1) * 2; // +2 max fitness per level
        $salary_increase = ($card_level - 1) * 20; // +20% salary per level

        return [
            'rating_bonus' => $rating_bonus,
            'fitness_bonus' => $fitness_bonus,
            'salary_increase_percent' => $salary_increase,
            'max_fitness' => min(100, 100 + $fitness_bonus)
        ];
    }
}

/**
 * Calculate card level upgrade success rate
 * 
 * @param int $current_level Current card level
 * @return int Success rate percentage (30-85%)
 */
if (!function_exists('getCardLevelUpgradeSuccessRate')) {
    function getCardLevelUpgradeSuccessRate($current_level)
    {
        $base_success_rate = 85; // 85% base success rate
        $level_penalty = ($current_level - 1) * 10; // -10% per level above 1
        return max(30, $base_success_rate - $level_penalty); // Minimum 30% success rate
    }
}

/**
 * Update player fitness with card level bonus
 * 
 * @param array $player Player data
 * @param bool $played_match Whether player played a match
 * @param int $days_since_last_match Days since last match
 * @return array Updated player data
 */
if (!function_exists('updatePlayerFitnessWithCardLevel')) {
    function updatePlayerFitnessWithCardLevel($player, $played_match = false, $days_since_last_match = 0)
    {
        $fitness = $player['fitness'] ?? 100;
        $card_level = $player['card_level'] ?? 1;
        $benefits = getCardLevelBenefits($card_level);
        $max_fitness = $benefits['max_fitness'];

        if ($played_match) {
            // Fitness decreases after playing
            $fitness -= rand(5, 15);
        } else {
            // Fitness recovers when resting
            $recovery = min(3 + ($days_since_last_match * 2), 10);
            $fitness += $recovery;
        }

        // Keep fitness between 0 and max fitness (based on card level)
        $player['fitness'] = max(0, min($max_fitness, $fitness));

        return $player;
    }
}

/**
 * Get contract status information
 * 
 * @param array $player Player data
 * @return array Contract status with text, color, and urgency level
 */
if (!function_exists('getContractStatus')) {
    function getContractStatus($player)
    {
        $remaining = $player['contract_matches_remaining'] ?? ($player['contract_matches'] ?? 25);

        if ($remaining <= 0) {
            return [
                'text' => 'Expired',
                'color' => 'text-red-600',
                'bg' => 'bg-red-100',
                'border' => 'border-red-200',
                'urgency' => 'critical'
            ];
        } elseif ($remaining <= 3) {
            return [
                'text' => 'Expiring Soon',
                'color' => 'text-red-600',
                'bg' => 'bg-red-100',
                'border' => 'border-red-200',
                'urgency' => 'high'
            ];
        } elseif ($remaining <= 8) {
            return [
                'text' => 'Renewal Needed',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-200',
                'urgency' => 'medium'
            ];
        } elseif ($remaining <= 15) {
            return [
                'text' => 'Active',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-200',
                'urgency' => 'low'
            ];
        } else {
            return [
                'text' => 'Secure',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'border' => 'border-green-200',
                'urgency' => 'none'
            ];
        }
    }
}

/**
 * Get user setting value
 * 
 * @param int $user_id User ID
 * @param string $key Setting key
 * @param string $default Default value if setting doesn't exist
 * @return string Setting value
 */
if (!function_exists('getUserSetting')) {
    function getUserSetting($user_id, $key, $default = '')
    {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = :key');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();

            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

/**
 * Set user setting value
 * 
 * @param int $user_id User ID
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool Success status
 */
if (!function_exists('setUserSetting')) {
    function setUserSetting($user_id, $key, $value)
    {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('INSERT OR REPLACE INTO user_settings (user_id, setting_key, setting_value, updated_at) VALUES (:user_id, :key, :value, datetime("now"))');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $result = $stmt->execute();
            $db->close();

            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get all user settings as associative array
 * 
 * @param int $user_id User ID
 * @return array All user settings
 */
if (!function_exists('getAllUserSettings')) {
    function getAllUserSettings($user_id)
    {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT setting_key, setting_value FROM user_settings WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $settings = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $db->close();
            return $settings;
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Delete user setting
 * 
 * @param int $user_id User ID
 * @param string $key Setting key
 * @return bool Success status
 */
if (!function_exists('deleteUserSetting')) {
    function deleteUserSetting($user_id, $key)
    {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('DELETE FROM user_settings WHERE user_id = :user_id AND setting_key = :key');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $result = $stmt->execute();
            $db->close();

            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
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
            $stmt = $db->prepare('UPDATE young_players SET development_stage = "promoted", promoted_at = datetime("now") WHERE id = :id');
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

            $stmt = $db->prepare('
                SELECT b.*, yp.name as player_name, yp.position, yp.age, yp.potential_rating, 
                       u.club_name as bidder_club_name, u.name as bidder_name
                FROM young_player_bids b
                JOIN young_players yp ON b.young_player_id = yp.id
                JOIN users u ON b.bidder_club_id = u.id
                WHERE b.owner_club_id = :club_id AND b.status = "pending" AND b.expires_at > datetime("now")
                ORDER BY b.created_at DESC
            ');
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
/**
 * Get user's current plan information
 * 
 * @param int $user_id User ID
 * @return array User plan information
 */
if (!function_exists('getUserPlan')) {
    function getUserPlan($user_id)
    {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT user_plan, plan_expires_at FROM users WHERE id = :user_id');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $userData = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();

            if (!$userData) {
                return null;
            }

            $planKey = $userData['user_plan'] ?? DEFAULT_USER_PLAN;
            $plans = USER_PLANS;

            if (!isset($plans[$planKey])) {
                $planKey = DEFAULT_USER_PLAN;
            }

            $planInfo = $plans[$planKey];
            $planInfo['key'] = $planKey;
            $planInfo['expires_at'] = $userData['plan_expires_at'];
            $planInfo['is_active'] = isPlanActive($userData['plan_expires_at']);

            return $planInfo;
        } catch (Exception $e) {
            return null;
        }
    }
}

/**
 * Check if user's plan is active
 * 
 * @param string $expires_at Plan expiration date
 * @return bool True if plan is active
 */
if (!function_exists('isPlanActive')) {
    function isPlanActive($expires_at)
    {
        if (!$expires_at) {
            return true; // Free plan never expires
        }

        return strtotime($expires_at) > time();
    }
}

/**
 * Check if user has a specific feature
 * 
 * @param int $user_id User ID
 * @param string $feature Feature key
 * @return bool|int Feature value (boolean or integer limit)
 */
if (!function_exists('userHasFeature')) {
    function userHasFeature($user_id, $feature)
    {
        $plan = getUserPlan($user_id);

        if (!$plan || !$plan['is_active']) {
            // If plan expired, fall back to free plan
            $plan = USER_PLANS[DEFAULT_USER_PLAN];
        }

        return $plan['features'][$feature] ?? false;
    }
}

/**
 * Check if user should see ads
 * 
 * @param int $user_id User ID
 * @return bool True if ads should be shown
 */
if (!function_exists('shouldShowAds')) {
    function shouldShowAds($user_id)
    {
        return userHasFeature($user_id, 'show_ads');
    }
}

/**
 * Get user's feature limit
 * 
 * @param int $user_id User ID
 * @param string $feature Feature key
 * @return int Feature limit
 */
if (!function_exists('getUserFeatureLimit')) {
    function getUserFeatureLimit($user_id, $feature)
    {
        $value = userHasFeature($user_id, $feature);
        return is_numeric($value) ? (int) $value : 0;
    }
}

/**
 * Upgrade user plan
 * 
 * @param int $user_id User ID
 * @param string $plan_key Plan key
 * @return bool Success status
 */
if (!function_exists('upgradeUserPlan')) {
    function upgradeUserPlan($user_id, $plan_key)
    {
        $plans = USER_PLANS;

        if (!isset($plans[$plan_key])) {
            return false;
        }

        try {
            $db = getDbConnection();

            $plan = $plans[$plan_key];
            $expires_at = null;

            if ($plan['duration_days'] > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));
            }

            $stmt = $db->prepare('UPDATE users SET user_plan = :plan, plan_expires_at = :expires_at WHERE id = :user_id');
            $stmt->bindValue(':plan', $plan_key, SQLITE3_TEXT);
            $stmt->bindValue(':expires_at', $expires_at, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

            $result = $stmt->execute();
            $db->close();

            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get all available plans
 * 
 * @return array All plans
 */
if (!function_exists('getAllPlans')) {
    function getAllPlans()
    {
        return USER_PLANS;
    }
}

/**
 * Format plan price for display
 * 
 * @param int $price_cents Price in cents
 * @return string Formatted price
 */
if (!function_exists('formatPlanPrice')) {
    function formatPlanPrice($price_cents)
    {
        if ($price_cents === 0) {
            return 'Free';
        }

        return '€' . number_format($price_cents / 100, 2);
    }
}

/**
 * Check if user can perform action based on limits
 * 
 * @param int $user_id User ID
 * @param string $feature Feature to check
 * @param int $current_count Current usage count
 * @return bool True if action is allowed
 */
if (!function_exists('canPerformAction')) {
    function canPerformAction($user_id, $feature, $current_count)
    {
        $limit = getUserFeatureLimit($user_id, $feature);

        if ($limit === 0) {
            return false; // Feature not available
        }

        return $current_count < $limit;
    }
}