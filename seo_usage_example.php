<?php
/**
 * SEO Usage Examples
 * This file shows how to use the SEO helpers in your pages
 */

// Example 1: Basic page with SEO
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    require_once 'partials/meta.php';
    generateMetaTags('game'); // Use 'landing', 'game', 'team', 'clubs', 'match', or 'default'
    ?>

    <!-- Your other head content -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <!-- Your page content -->
    <h1>Your Page Content</h1>

    <!-- Analytics before closing body tag -->
    <?php
    require_once 'partials/analytics.php';
    if (shouldLoadAnalytics()) {
        renderGoogleAnalytics(); // Uses config from seo_config.php
        renderFacebookPixel();   // Uses config from seo_config.php
    }
    ?>
</body>

</html>

<?php
// Example 2: Track custom events
// trackEvent('button_click', ['button_name' => 'play_now']);
// trackEvent('game_start', ['club_name' => 'Manchester United']);

// Example 3: Generate structured data
// generateStructuredData('WebApplication'); // or 'Game'

// Example 4: Configuration setup
// To use analytics, edit seo_config.php and add your tracking IDs:
/*
define('GOOGLE_ANALYTICS_ID', 'G-XXXXXXXXXX');
define('FACEBOOK_PIXEL_ID', '1234567890123456');
*/

// Example 5: Page-specific meta tags
/*
Available page types:
- 'landing' - Landing page with full SEO optimization
- 'game' - Game/play pages
- 'team' - Team management pages  
- 'clubs' - Club listing and challenge pages
- 'match' - Match simulation pages
- 'default' - Generic pages
*/
?>