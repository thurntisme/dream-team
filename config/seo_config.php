<?php
/**
 * SEO Configuration
 * Central configuration for SEO settings, tracking IDs, and meta data
 */

// Analytics Configuration
define('GOOGLE_ANALYTICS_ID', ''); // Add your GA tracking ID here: 'GA_MEASUREMENT_ID'
define('FACEBOOK_PIXEL_ID', '');   // Add your Facebook Pixel ID here: '1234567890123456'

// SEO Settings
define('SITE_NAME', 'Dream Team');
define('SITE_DESCRIPTION', 'Ultimate football manager game where you build your dream team and compete against other clubs');
define('SITE_KEYWORDS', 'football manager, soccer game, dream team, online game, football simulation, team management, sports game, free game');
define('SITE_AUTHOR', 'Dream Team');

// Social Media Settings
define('TWITTER_HANDLE', '@dreamteamgame'); // Your Twitter handle
define('FACEBOOK_PAGE', 'https://facebook.com/dreamteamgame'); // Your Facebook page URL

// Domain Settings (auto-detected, but can be overridden)
function getSiteUrl()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}

function getCurrentUrl()
{
    return getSiteUrl() . $_SERVER['REQUEST_URI'];
}

// Enable/Disable Features
define('ENABLE_ANALYTICS', true);
define('ENABLE_STRUCTURED_DATA', true);
define('ENABLE_SOCIAL_META', true);

// Default Images (create these in an assets folder)
define('DEFAULT_OG_IMAGE', getSiteUrl() . '/assets/og-image.jpg');
define('DEFAULT_TWITTER_IMAGE', getSiteUrl() . '/assets/twitter-image.jpg');

/**
 * Get page-specific configuration
 */
function getPageConfig($page = 'default')
{
    $configs = [
        'landing' => [
            'title_suffix' => ' | Build Your Dream Squad',
            'priority' => 1.0,
            'changefreq' => 'weekly'
        ],
        'game' => [
            'title_suffix' => ' | Play Now',
            'priority' => 0.9,
            'changefreq' => 'daily'
        ],
        'team' => [
            'title_suffix' => ' | Team Management',
            'priority' => 0.8,
            'changefreq' => 'daily'
        ],
        'clubs' => [
            'title_suffix' => ' | Club Challenges',
            'priority' => 0.8,
            'changefreq' => 'daily'
        ],
        'match' => [
            'title_suffix' => ' | Live Match',
            'priority' => 0.7,
            'changefreq' => 'hourly'
        ],
        'default' => [
            'title_suffix' => '',
            'priority' => 0.5,
            'changefreq' => 'monthly'
        ]
    ];

    return $configs[$page] ?? $configs['default'];
}

/**
 * Check if analytics should be loaded
 */
function shouldLoadAnalytics()
{
    return ENABLE_ANALYTICS && (GOOGLE_ANALYTICS_ID || FACEBOOK_PIXEL_ID);
}

/**
 * Get tracking IDs (only if not empty)
 */
function getGoogleAnalyticsId()
{
    return !empty(GOOGLE_ANALYTICS_ID) ? GOOGLE_ANALYTICS_ID : null;
}

function getFacebookPixelId()
{
    return !empty(FACEBOOK_PIXEL_ID) ? FACEBOOK_PIXEL_ID : null;
}
?>