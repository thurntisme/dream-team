<?php
/**
 * SEO Meta Tags Helper
 * Centralized meta tags management for Dream Team
 */

function generateMetaTags($page = 'default')
{
    require_once 'seo_config.php';

    $baseUrl = getSiteUrl();
    $currentUrl = getCurrentUrl();
    $pageConfig = getPageConfig($page);

    $metaData = [
        'default' => [
            'title' => 'Dream Team - Ultimate Football Manager Game',
            'description' => 'Build your dream football team, challenge other clubs, and compete for glory in this exciting online football management game. Free to play!',
            'keywords' => 'football manager, soccer game, dream team, online game, football simulation, team management, sports game, free game',
            'image' => $baseUrl . '/assets/og-image.jpg'
        ],
        'landing' => [
            'title' => 'Dream Team - Ultimate Football Manager Game | Build Your Dream Squad',
            'description' => 'Dream Team - The ultimate football manager game. Build your dream team, challenge other clubs, and become the champion. Free to play online football management simulation.',
            'keywords' => 'football manager, soccer game, dream team, online game, football simulation, team management, sports game, free game, build team, challenge clubs',
            'image' => $baseUrl . '/assets/og-image.jpg'
        ],
        'game' => [
            'title' => 'Play Dream Team - Football Manager Game',
            'description' => 'Start playing Dream Team now! Create your club, build your squad, and challenge other players in this exciting football management game.',
            'keywords' => 'play football manager, start game, create club, build squad, football game online',
            'image' => $baseUrl . '/assets/game-screenshot.jpg'
        ],
        'team' => [
            'title' => 'Team Management - Dream Team',
            'description' => 'Manage your football team, select players, choose formations, and optimize your squad for maximum performance.',
            'keywords' => 'team management, football squad, player selection, formations, tactics',
            'image' => $baseUrl . '/assets/team-management.jpg'
        ],
        'clubs' => [
            'title' => 'Club Challenges - Dream Team',
            'description' => 'Challenge other football clubs, compete in matches, and climb the global rankings to become the ultimate champion.',
            'keywords' => 'club challenges, football matches, global rankings, competitions, football clubs',
            'image' => $baseUrl . '/assets/club-challenges.jpg'
        ],
        'match' => [
            'title' => 'Live Match Simulation - Dream Team',
            'description' => 'Watch your team play in real-time with dynamic match simulation, live events, and tactical decisions that affect the outcome.',
            'keywords' => 'live match, football simulation, real-time events, match tactics, football match',
            'image' => $baseUrl . '/assets/match-simulation.jpg'
        ]
    ];

    $meta = $metaData[$page] ?? $metaData['default'];

    echo "
    <!-- SEO Meta Tags -->
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <meta name=\"description\" content=\"{$meta['description']}\">
    <meta name=\"keywords\" content=\"{$meta['keywords']}\">
    <meta name=\"author\" content=\"Dream Team\">
    <meta name=\"robots\" content=\"index, follow\">
    <meta name=\"language\" content=\"English\">
    
    <!-- Open Graph / Facebook -->
    <meta property=\"og:type\" content=\"website\">
    <meta property=\"og:url\" content=\"{$currentUrl}\">
    <meta property=\"og:title\" content=\"{$meta['title']}\">
    <meta property=\"og:description\" content=\"{$meta['description']}\">
    <meta property=\"og:image\" content=\"{$meta['image']}\">
    <meta property=\"og:image:width\" content=\"1200\">
    <meta property=\"og:image:height\" content=\"630\">
    
    <!-- Twitter -->
    <meta property=\"twitter:card\" content=\"summary_large_image\">
    <meta property=\"twitter:url\" content=\"{$currentUrl}\">
    <meta property=\"twitter:title\" content=\"{$meta['title']}\">
    <meta property=\"twitter:description\" content=\"{$meta['description']}\">
    <meta property=\"twitter:image\" content=\"{$meta['image']}\">
    
    <!-- Canonical URL -->
    <link rel=\"canonical\" href=\"{$currentUrl}\">
    
    <!-- Favicon -->
    <link rel=\"icon\" type=\"image/x-icon\" href=\"/favicon.ico\">
    <link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"/apple-touch-icon.png\">
    <link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"/favicon-32x32.png\">
    <link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"/favicon-16x16.png\">
    
    <title>{$meta['title']}</title>
    ";
}

function generateStructuredData($type = 'WebApplication')
{
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    $structuredData = [
        'WebApplication' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'Dream Team',
            'description' => 'Ultimate football manager game where you build your dream team and compete against other clubs',
            'url' => $baseUrl,
            'applicationCategory' => 'Game',
            'operatingSystem' => 'Web Browser',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD'
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => '4.8',
                'ratingCount' => '1250'
            ],
            'author' => [
                '@type' => 'Organization',
                'name' => 'Dream Team'
            ]
        ],
        'Game' => [
            '@context' => 'https://schema.org',
            '@type' => 'VideoGame',
            'name' => 'Dream Team',
            'description' => 'Football manager simulation game',
            'genre' => 'Sports, Simulation, Strategy',
            'gamePlatform' => 'Web Browser',
            'applicationCategory' => 'Game',
            'operatingSystem' => 'Any',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD'
            ]
        ]
    ];

    $data = $structuredData[$type] ?? $structuredData['WebApplication'];

    echo '<script type="application/ld+json">' . json_encode($data, JSON_PRETTY_PRINT) . '</script>';
}
?>