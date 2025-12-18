<?php
/**
 * Error Handler Functions
 * Provides consistent error handling across the application
 */

/**
 * Show a 404 error page
 * 
 * @param string $title Custom error title (ignored - for backward compatibility)
 * @param string $message Custom error message (ignored - for backward compatibility)
 * @param string $details Additional error details (ignored - for backward compatibility)
 * @param bool $showBackButton Whether to show the back button (ignored - for backward compatibility)
 * @param string $homeUrl Custom home URL (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function show404Error($title = null, $message = null, $details = null, $showBackButton = true, $homeUrl = 'index.php', $exit = true)
{
    // Redirect to simplified 404 page
    header('Location: 404.php');
    
    if ($exit) {
        exit;
    }
}

/**
 * Show a 403 Forbidden error
 * 
 * @param string $message Custom error message (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function show403Error($message = null, $exit = true)
{
    show404Error(null, null, null, true, 'index.php', $exit);
}

/**
 * Show a generic error page for debugging disabled features
 * 
 * @param string $feature Feature name that is disabled (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function showFeatureDisabledError($feature, $exit = true)
{
    show404Error(null, null, null, true, 'index.php', $exit);
}

/**
 * Show maintenance mode error
 * 
 * @param string $message Custom maintenance message (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function showMaintenanceError($message = null, $exit = true)
{
    show404Error(null, null, null, false, 'index.php', $exit);
}

/**
 * Show database connection error
 * 
 * @param bool $exit Whether to exit after showing the error
 */
function showDatabaseError($exit = true)
{
    show404Error(null, null, null, true, 'index.php', $exit);
}

/**
 * Show authentication required error
 * 
 * @param bool $exit Whether to exit after showing the error
 */
function showAuthRequiredError($exit = true)
{
    show404Error(null, null, null, true, 'login.php', $exit);
}

/**
 * Show club required error
 * 
 * @param bool $exit Whether to exit after showing the error
 */
function showClubRequiredError($exit = true)
{
    show404Error(null, null, null, true, 'welcome.php', $exit);
}

/**
 * Show premium feature error
 * 
 * @param string $feature Feature name (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function showPremiumRequiredError($feature = 'feature', $exit = true)
{
    show404Error(null, null, null, true, 'plans.php', $exit);
}

/**
 * Show rate limit error
 * 
 * @param int $retryAfter Seconds until retry is allowed (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function showRateLimitError($retryAfter = 60, $exit = true)
{
    show404Error(null, null, null, true, 'index.php', $exit);
}

/**
 * Show file not found error
 * 
 * @param string $filename Name of the file that wasn't found (ignored - for backward compatibility)
 * @param bool $exit Whether to exit after showing the error
 */
function showFileNotFoundError($filename = null, $exit = true)
{
    show404Error(null, null, null, true, 'index.php', $exit);
}

/**
 * Render inline 404 error (without redirect)
 * Useful for AJAX responses or when you want to show error in current page
 * 
 * @param string $title Error title
 * @param string $message Error message
 * @param string $details Error details
 * @return string HTML content for the error
 */
function renderInline404Error($title = 'Not Found', $message = 'The requested resource was not found.', $details = '')
{
    ob_start();
    ?>
    <div class="max-w-md mx-auto text-center p-6">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-circle" class="w-8 h-8 text-red-500"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($title); ?></h3>
        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($message); ?></p>
        <?php if ($details): ?>
            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($details); ?></p>
        <?php endif; ?>
    </div>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Check if request is AJAX
 * 
 * @return bool True if AJAX request
 */
function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Handle error based on request type (AJAX vs regular)
 * 
 * @param string $title Error title
 * @param string $message Error message
 * @param string $details Error details
 * @param int $httpCode HTTP status code
 */
function handleError($title, $message, $details = '', $httpCode = 404)
{
    http_response_code($httpCode);
    
    if (isAjaxRequest()) {
        // Return JSON error for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => [
                'title' => $title,
                'message' => $message,
                'details' => $details,
                'code' => $httpCode
            ]
        ]);
        exit;
    } else {
        // Show simplified 404 page for regular requests
        show404Error();
    }
}