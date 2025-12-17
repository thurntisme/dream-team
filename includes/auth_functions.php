<?php
/**
 * Authentication and Session Management Functions
 * 
 * This file contains functions related to user authentication, session management,
 * login validation, and access control.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
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