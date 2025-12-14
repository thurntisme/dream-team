<?php
/**
 * Application Routes
 * Centralized routing system for Dream Team
 */

// Get the requested URI and remove query string
$request_uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$request_uri = trim($request_uri, '/');

// Define routes mapping
$routes = [
    // Main Pages
    '' => 'landing.php',                    // Home page
    'home' => 'landing.php',               // Alternative home
    'play' => 'index.php',                 // Login page
    'game' => 'index.php',                 // Alternative login
    'login' => 'index.php',                // Login page
    'welcome' => 'welcome.php',            // Dashboard
    'dashboard' => 'welcome.php',          // Alternative dashboard

    // Team Management
    'team' => 'team.php',                  // Team management
    'squad' => 'team.php',                 // Alternative team
    'staff' => 'staff.php',                // Club staff
    'stadium' => 'stadium.php',            // Stadium management
    'academy' => 'academy.php',            // Young player academy

    // Player Management
    'transfer' => 'transfer.php',          // Transfer market
    'transfers' => 'transfer.php',         // Alternative transfers
    'scouting' => 'scouting.php',          // Player scouting
    'scout' => 'scouting.php',             // Alternative scouting
    'young-players' => 'young_player_market.php', // Young player market

    // Competition
    'league' => 'league.php',              // League standings
    'clubs' => 'clubs.php',                // Other clubs
    'match' => 'match-simulator.php',      // Match simulator
    'simulator' => 'match-simulator.php',  // Alternative match

    // Commerce
    'shop' => 'shop.php',                  // Item shop
    'store' => 'shop.php',                 // Alternative shop
    'plans' => 'plans.php',                // Subscription plans
    'pricing' => 'plans.php',              // Alternative pricing
    'payment' => 'payment.php',            // Payment page
    'payment-success' => 'payment_success.php', // Payment success

    // User Management
    'settings' => 'settings.php',          // User settings
    'profile' => 'settings.php',           // Alternative settings
    'feedback' => 'feedback.php',          // User feedback
    'auth' => 'auth.php',                  // Authentication
    'install' => 'install.php',            // Installation
    'setup' => 'install.php',              // Alternative install

    // API Routes (handled separately in .htaccess)
    // These are documented here for reference
    // 'api/payment' => 'api/payment_api.php',
    // 'api/plan' => 'api/plan_api.php',
    // 'api/settings' => 'api/settings_api.php',
    // 'api/young_player' => 'api/young_player_api.php',
];

// Special routes with parameters
$parameterRoutes = [
    'club' => function ($params) {
        // Route: /club/123 -> clubs.php?id=123
        if (isset($params[0]) && is_numeric($params[0])) {
            $_GET['id'] = $params[0];
            return 'clubs.php';
        }
        return 'clubs.php';
    },

    'player' => function ($params) {
        // Route: /player/456 -> transfer.php?player_id=456
        if (isset($params[0]) && is_numeric($params[0])) {
            $_GET['player_id'] = $params[0];
            return 'transfer.php';
        }
        return 'transfer.php';
    },

    'match' => function ($params) {
        // Route: /match/vs/789 -> match-simulator.php?opponent_id=789
        if (isset($params[0]) && $params[0] === 'vs' && isset($params[1]) && is_numeric($params[1])) {
            $_GET['opponent_id'] = $params[1];
            return 'match-simulator.php';
        }
        return 'match-simulator.php';
    }
];

/**
 * Route the request
 */
function routeRequest($request_uri, $routes, $parameterRoutes)
{
    // Check exact matches first
    if (isset($routes[$request_uri])) {
        return $routes[$request_uri];
    }

    // Check parameter routes
    $segments = explode('/', $request_uri);
    $base = $segments[0] ?? '';

    if (isset($parameterRoutes[$base])) {
        $params = array_slice($segments, 1);
        return $parameterRoutes[$base]($params);
    }

    // No route found
    return null;
}

/**
 * Handle 404 errors
 */
function handle404()
{
    http_response_code(404);

    // Check if we have a custom 404 page
    if (file_exists('404.php')) {
        require_once '404.php';
    } else {
        // Simple 404 response
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Dream Team</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="mb-8">
            <i data-lucide="shield-x" class="w-24 h-24 text-gray-400 mx-auto mb-4"></i>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">404</h1>
            <p class="text-xl text-gray-600 mb-4">Page Not Found</p>
            <p class="text-gray-500 mb-8">The page you are looking for does not exist.</p>
            <a href="/" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                <i data-lucide="home" class="w-4 h-4"></i>
                Go Home
            </a>
        </div>
    </div>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
</body>
</html>';
    }
    exit;
}

/**
 * Get clean URL for a route
 */
function url($route, $params = [])
{
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    // Handle parameter routes
    if (!empty($params)) {
        $route .= '/' . implode('/', $params);
    }

    return $base_url . '/' . $route;
}

/**
 * Redirect to a route
 */
function redirect($route, $params = [])
{
    header('Location: ' . url($route, $params));
    exit;
}

/**
 * Check if current route matches
 */
function isCurrentRoute($route)
{
    $current = strtok($_SERVER['REQUEST_URI'], '?');
    $current = trim($current, '/');
    return $current === $route;
}

// Only route if this file is accessed directly (not included)
if (basename($_SERVER['SCRIPT_NAME']) === 'routes.php') {
    $target_file = routeRequest($request_uri, $routes, $parameterRoutes);

    if ($target_file && file_exists($target_file)) {
        // Include the target file
        require_once $target_file;
    } else {
        // Handle 404
        handle404();
    }
}