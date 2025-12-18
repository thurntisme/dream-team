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
require_once __DIR__ . '/player_stats_functions.php';
