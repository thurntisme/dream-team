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
 * Require club name - redirect to welcome page if club name is empty
 * This should be called on pages that require a club name to function properly
 * 
 * @param string $current_page Current page identifier to avoid redirect loops
 */
if (!function_exists('requireClubName')) {
    function requireClubName($current_page = '')
    {
        // First validate the session
        validateSession($current_page);

        // Don't redirect if we're already on welcome page or login pages
        if ($current_page === 'welcome' || $current_page === 'index' || $current_page === 'install') {
            return;
        }

        // Check if user is logged in (should be true after validateSession)
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
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