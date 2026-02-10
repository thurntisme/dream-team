<?php
// Dream Team Layout Template
// Provides consistent navigation and footer across all pages

function renderLayout($title, $content, $currentPage = '', $showAuth = true, $skipDbCheck = false, $requireClub = false, $requireAuth = true)
{
    // Ensure constants and helpers are available
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../includes/helpers.php';
    require_once __DIR__ . '/../includes/ads.php';
    require_once __DIR__ . '/../includes/routing.php';
    require_once __DIR__ . '/../includes/auth_functions.php';
    require_once __DIR__ . '/nav.php';
    require_once __DIR__ . '/footer.php';

    // Require authentication if specified (default: true)
    // if ($requireAuth) {
    //     requireAuth($currentPage);
    // }

    // Require club name if specified
    if ($requireClub) {
        requireClubName($currentPage);
    }

    $isLoggedIn = isset($_SESSION['user_id']);
    $clubName = $_SESSION['club_name'] ?? '';
    $userName = $_SESSION['user_name'] ?? '';

    // Get user budget, fans, and club level if logged in
    $userBudget = 0;
    $userFans = 0;
    $clubLevel = 1;
    $clubExp = 0;
    if ($isLoggedIn && isDatabaseAvailable()) {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT budget, fans, club_level, club_exp FROM users WHERE id = :user_id');
            if ($stmt !== false) {
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $result = $stmt->execute();
                if ($result) {
                    $userData = $result->fetchArray(SQLITE3_ASSOC);
                    $userBudget = $userData['budget'] ?? 0;
                    $userFans = $userData['fans'] ?? 5000;
                    $clubLevel = $userData['club_level'] ?? 1;
                    $clubExp = $userData['club_exp'] ?? 0;
                }
            }

            $db->close();
        } catch (Exception $e) {
            $userBudget = 0;
            $userFans = 5000;
            $clubLevel = 1;
            $clubExp = 0;
        }
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <?php
        // Determine page type for SEO
        $pageType = 'default';
        if (strpos($title, 'Team') !== false)
            $pageType = 'team';
        elseif (strpos($title, 'Club') !== false)
            $pageType = 'clubs';
        elseif (strpos($title, 'Match') !== false)
            $pageType = 'match';
        elseif (strpos($title, 'Welcome') !== false)
            $pageType = 'game';

        // Include meta.php if it exists
        if (file_exists(__DIR__ . '/meta.php')) {
            require_once __DIR__ . '/meta.php';
            generateMetaTags($pageType);
        } else {
            // Fallback meta tags
            echo '<meta charset="UTF-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            echo '<title>' . htmlspecialchars($title) . ' - Dream Team</title>';
        }
        ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    </head>

    <body class="bg-gray-50 min-h-screen flex flex-col">
        <?php if (isDatabaseAvailable()): ?>
            <?php echo renderNavigation($isLoggedIn, $showAuth, $currentPage, $clubName, $userName, $userBudget, $userFans, $clubLevel); ?>
        <?php endif; ?>

        <!-- Main Content -->
        <main class="flex-1">
            <?php echo $content; ?>
        </main>
        
        <?php if (isDatabaseAvailable()): ?>
            <?php echo renderFooter($isLoggedIn, $clubName, $userName, $userBudget, $userFans, $clubLevel); ?>
        <?php endif; ?>

        <!-- Floating Ad for Free Users -->
        <?php if ($isLoggedIn && shouldShowAds($_SESSION['user_id'])): ?>
            <?php renderFloatingAd($_SESSION['user_id']); ?>
        <?php endif; ?>

        <!-- JavaScript -->
        <script src="assets/js/layout.js"></script>
        <script>
            // Initialize session management if user is logged in
            <?php if ($isLoggedIn && isset($_SESSION['expire_time'])): ?>
                initSessionManagement(<?php echo $_SESSION['expire_time']; ?>);
            <?php endif; ?>
        </script>

        <?php
        // Add analytics tracking if available
        if (file_exists(__DIR__ . '/analytics.php')) {
            require_once __DIR__ . '/analytics.php';
            if (shouldLoadAnalytics()) {
                renderGoogleAnalytics();
                renderFacebookPixel();
            }
        }
        ?>
    </body>

    </html>
    <?php
    return ob_get_clean();
}

// Helper function to start content capture
function startContent()
{
    ob_start();
}

// Helper function to end content capture and render layout
function endContent($title, $currentPage = '', $showAuth = true, $skipDbCheck = false, $requireClub = false, $requireAuth = true)
{
    $content = ob_get_clean();
    echo renderLayout($title, $content, $currentPage, $showAuth, $skipDbCheck, $requireClub, $requireAuth);
}
?>
