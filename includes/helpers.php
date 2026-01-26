<?php
/**
 * Helper Functions for Dream Team Application
 * 
 * This file contains commonly used helper functions to avoid redeclaration errors
 * and provide a centralized location for utility functions.
 * 
 * Most functions have been moved to specialized files for better organization:
 * - player_functions.php: Player management, ratings, fitness, form, experience
 * - auth_functions.php: Authentication, session management, access control
 * - club_functions.php: Club management, experience points, levels
 * - utility_functions.php: General utilities, formatting, sanitization
 * - young_player_functions.php: Academy management, young player development
 * - user_plan_functions.php: User plans, subscriptions, feature access
 * - news_functions.php: News generation and management
 * - nation_calls_functions.php: National team calls and rewards
 * - player_stats_functions.php: Player statistics and match ratings
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

// Include specialized function files
require_once __DIR__ . '/staff_functions.php';
require_once __DIR__ . '/player_functions.php';
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/club_functions.php';
require_once __DIR__ . '/utility_functions.php';
require_once __DIR__ . '/young_player_functions.php';
require_once __DIR__ . '/user_plan_functions.php';
require_once __DIR__ . '/news_functions.php';
require_once __DIR__ . '/nation_calls_functions.php';
require_once __DIR__ . '/debug_logger.php';
require_once __DIR__ . '/error_handlers.php';
require_once __DIR__ . '/player_stats_functions.php';
/**
 * Generate player avatar HTML with initials
 */
if (!function_exists('getPlayerAvatar')) {
    function getPlayerAvatar($playerName, $size = 'md', $customClass = '')
    {
        // Get initials from player name
        $nameParts = explode(' ', trim($playerName));
        $initials = '';

        if (count($nameParts) >= 2) {
            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
        } else {
            $initials = strtoupper(substr($playerName, 0, 2));
        }

        // Generate consistent color based on name
        $colors = [
            ['bg' => 'bg-blue-600', 'text' => 'text-white'],
            ['bg' => 'bg-green-600', 'text' => 'text-white'],
            ['bg' => 'bg-purple-600', 'text' => 'text-white'],
            ['bg' => 'bg-red-600', 'text' => 'text-white'],
            ['bg' => 'bg-yellow-600', 'text' => 'text-white'],
            ['bg' => 'bg-indigo-600', 'text' => 'text-white'],
            ['bg' => 'bg-pink-600', 'text' => 'text-white'],
            ['bg' => 'bg-teal-600', 'text' => 'text-white'],
            ['bg' => 'bg-orange-600', 'text' => 'text-white'],
            ['bg' => 'bg-cyan-600', 'text' => 'text-white'],
        ];

        $colorIndex = abs(crc32($playerName)) % count($colors);
        $color = $colors[$colorIndex];

        // Size classes
        $sizeClasses = [
            'xs' => 'w-8 h-8 text-xs',
            'sm' => 'w-10 h-10 text-sm',
            'md' => 'w-16 h-16 text-lg',
            'lg' => 'w-20 h-20 text-xl',
            'xl' => 'w-24 h-24 text-2xl',
        ];

        $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

        return sprintf(
            '<div class="%s %s %s rounded-full flex items-center justify-center font-bold %s">%s</div>',
            $sizeClass,
            $color['bg'],
            $color['text'],
            $customClass,
            htmlspecialchars($initials)
        );
    }
}

/**
 * Generate player avatar with image fallback to initials
 */
if (!function_exists('getPlayerAvatarWithImage')) {
    function getPlayerAvatarWithImage($playerName, $imageUrl = null, $size = 'md', $customClass = '')
    {
        // Check if image URL is a local file (doesn't start with http)
        if ($imageUrl && strpos($imageUrl, 'http') !== 0) {
            $fullImagePath = PLAYER_IMAGES_BASE_PATH . $imageUrl;
            if (file_exists($fullImagePath)) {
                $imageUrl = $fullImagePath;
            }
        }

        if ($imageUrl && file_exists($imageUrl)) {
            // Size classes
            $sizeClasses = [
                'xs' => 'w-8 h-8',
                'sm' => 'w-10 h-10',
                'md' => 'w-16 h-16',
                'lg' => 'w-20 h-20',
                'xl' => 'w-24 h-24',
            ];

            $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

            return sprintf(
                '<img src="%s" alt="%s" class="%s rounded-full object-cover %s" />',
                htmlspecialchars($imageUrl),
                htmlspecialchars($playerName),
                $sizeClass,
                $customClass
            );
        }

        // Fallback to initials avatar
        return getPlayerAvatar($playerName, $size, $customClass);
    }
}