<?php
/**
 * Utility Functions
 * 
 * This file contains general utility functions for formatting, sanitization,
 * validation, and other common operations.
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
        return bin2hex(random_bytes(8));
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
            $stmt = $db->prepare('INSERT INTO user_settings (user_id, setting_key, setting_value, updated_at) VALUES (:user_id, :key, :value, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
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
 * Get time ago string
 */
if (!function_exists('getTimeAgo')) {
    function getTimeAgo($timestamp)
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Convert number to ordinal (1st, 2nd, 3rd, etc.)
 */
if (!function_exists('ordinal')) {
    function ordinal($number)
    {
        $ends = array('th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th');
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        } else {
            return $number . $ends[$number % 10];
        }
    }
}
