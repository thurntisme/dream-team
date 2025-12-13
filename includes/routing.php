<?php
/**
 * Routing Helper Functions
 * Helper functions for URL generation and routing
 */

/**
 * Generate a clean URL for a route (alias for url function)
 * 
 * @param string $route The route name
 * @param array $params Optional parameters
 * @return string The full URL
 */
function route($route, $params = [])
{
    // Use the url function from routes.php if available
    if (function_exists('url')) {
        return url($route, $params);
    }

    // Fallback implementation
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    // Handle parameter routes
    if (!empty($params)) {
        $route .= '/' . implode('/', $params);
    }

    return $base_url . '/' . $route;
}

/**
 * Redirect to a route
 * 
 * @param string $route The route to redirect to
 * @param array $params Optional parameters
 */
function redirectToRoute($route, $params = [])
{
    header('Location: ' . route($route, $params));
    exit;
}

/**
 * Check if current route matches (safe version)
 * 
 * @param string $route The route to check
 * @return bool True if current route matches
 */
function isCurrentRoute($route)
{
    $current = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
    $current = trim($current, '/');
    return $current === $route;
}

/**
 * Get current route (safe version)
 * 
 * @return string Current route
 */
function getCurrentRoute()
{
    $current = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
    return trim($current, '/');
}

/**
 * Generate navigation link with active state
 * 
 * @param string $route The route
 * @param string $text Link text
 * @param string $activeClass CSS class for active state
 * @param string $inactiveClass CSS class for inactive state
 * @param array $params Optional parameters
 * @return string HTML link
 */
function navLink($route, $text, $activeClass = 'active', $inactiveClass = '', $params = [])
{
    $url = route($route, $params);
    $class = isCurrentRoute($route) ? $activeClass : $inactiveClass;

    return '<a href="' . htmlspecialchars($url) . '" class="' . htmlspecialchars($class) . '">' . htmlspecialchars($text) . '</a>';
}

/**
 * Generate form action URL
 * 
 * @param string $route The route for form action
 * @param array $params Optional parameters
 * @return string The action URL
 */
function formAction($route, $params = [])
{
    return route($route, $params);
}

/**
 * Asset URL helper
 * 
 * @param string $path Asset path
 * @return string Full asset URL
 */
function asset($path)
{
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    return $base_url . '/' . ltrim($path, '/');
}

/**
 * API URL helper
 * 
 * @param string $endpoint API endpoint
 * @return string Full API URL
 */
function apiUrl($endpoint)
{
    return route('api/' . ltrim($endpoint, '/'));
}

/**
 * Breadcrumb helper
 * 
 * @param array $breadcrumbs Array of ['route' => 'Route Name', 'text' => 'Display Text']
 * @return string HTML breadcrumb
 */
function breadcrumb($breadcrumbs)
{
    $html = '<nav class="breadcrumb"><ol>';

    foreach ($breadcrumbs as $crumb) {
        if (isset($crumb['route'])) {
            $html .= '<li><a href="' . route($crumb['route']) . '">' . htmlspecialchars($crumb['text']) . '</a></li>';
        } else {
            $html .= '<li class="active">' . htmlspecialchars($crumb['text']) . '</li>';
        }
    }

    $html .= '</ol></nav>';
    return $html;
}