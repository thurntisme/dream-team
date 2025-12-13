<?php
// Ads Component for Free Users
// This file contains ad display functions and components

/**
 * Render banner ad
 * 
 * @param string $position Ad position (header, sidebar, footer, content)
 * @param int $user_id User ID to check if ads should be shown
 */
function renderBannerAd($position = 'content', $user_id = null)
{
    // Check if user should see ads
    if ($user_id && !shouldShowAds($user_id)) {
        return;
    }

    $ads = getAdsByPosition($position);
    if (empty($ads)) {
        return;
    }

    $ad = $ads[array_rand($ads)]; // Random ad selection

    echo '<div class="ad-container ad-' . htmlspecialchars($position) . ' mb-4">';
    echo '<div class="text-xs text-gray-500 mb-1 text-center">Advertisement</div>';
    echo '<div class="border border-gray-200 rounded-lg p-4 bg-gray-50 text-center">';

    if ($ad['type'] === 'image') {
        echo '<a href="' . htmlspecialchars($ad['url']) . '" target="_blank" rel="noopener">';
        echo '<img src="' . htmlspecialchars($ad['image']) . '" alt="' . htmlspecialchars($ad['title']) . '" class="max-w-full h-auto mx-auto">';
        echo '</a>';
    } else {
        echo '<div class="text-lg font-semibold text-gray-900 mb-2">' . htmlspecialchars($ad['title']) . '</div>';
        echo '<div class="text-gray-600 mb-3">' . htmlspecialchars($ad['description']) . '</div>';
        echo '<a href="' . htmlspecialchars($ad['url']) . '" target="_blank" rel="noopener" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">';
        echo htmlspecialchars($ad['cta']);
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';
}

/**
 * Render upgrade prompt for free users
 * 
 * @param int $user_id User ID
 * @param string $feature Feature that triggered the prompt
 */
function renderUpgradePrompt($user_id, $feature = '')
{
    if (!shouldShowAds($user_id)) {
        return;
    }

    $featureMessages = [
        'max_academy_players' => 'Upgrade to add more academy players',
        'max_staff_members' => 'Upgrade to hire more staff members',
        'weekly_scout_limit' => 'Upgrade to scout more players per week',
        'advanced_analytics' => 'Upgrade to access advanced analytics',
        'custom_formations' => 'Upgrade to create custom formations'
    ];

    $message = $featureMessages[$feature] ?? 'Upgrade to unlock premium features';

    echo '<div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg p-4 mb-6">';
    echo '<div class="flex items-center justify-between">';
    echo '<div>';
    echo '<h3 class="font-semibold text-lg mb-1">' . htmlspecialchars($message) . '</h3>';
    echo '<p class="text-blue-100">Remove ads and unlock all features with Premium</p>';
    echo '</div>';
    echo '<div>';
    echo '<a href="plans.php" class="inline-block px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-gray-100 transition-colors font-medium">';
    echo 'View Plans';
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Get ads by position
 * 
 * @param string $position Ad position
 * @return array Ads for the position
 */
function getAdsByPosition($position)
{
    // Demo ads - in production, these would come from a database or ad network
    $ads = [
        'header' => [
            [
                'type' => 'text',
                'title' => 'Boost Your Team Performance',
                'description' => 'Professional football analytics tools',
                'url' => '#',
                'cta' => 'Learn More'
            ]
        ],
        'sidebar' => [
            [
                'type' => 'text',
                'title' => 'Football Manager Pro',
                'description' => 'Advanced team management features',
                'url' => '#',
                'cta' => 'Try Free'
            ],
            [
                'type' => 'text',
                'title' => 'Scout Network',
                'description' => 'Discover hidden talents worldwide',
                'url' => '#',
                'cta' => 'Join Now'
            ]
        ],
        'content' => [
            [
                'type' => 'text',
                'title' => 'Premium Training Facilities',
                'description' => 'Upgrade your academy with state-of-the-art facilities',
                'url' => '#',
                'cta' => 'Upgrade'
            ],
            [
                'type' => 'text',
                'title' => 'Transfer Market Insights',
                'description' => 'Get real-time market analysis and player valuations',
                'url' => '#',
                'cta' => 'Subscribe'
            ]
        ],
        'footer' => [
            [
                'type' => 'text',
                'title' => 'Dream Team Premium',
                'description' => 'Unlock all features and remove ads',
                'url' => 'plans.php',
                'cta' => 'Upgrade Now'
            ]
        ]
    ];

    return $ads[$position] ?? [];
}

/**
 * Render floating ad for mobile
 * 
 * @param int $user_id User ID
 */
function renderFloatingAd($user_id)
{
    if (!shouldShowAds($user_id)) {
        return;
    }

    echo '<div id="floatingAd" class="fixed bottom-4 right-4 z-50 max-w-sm bg-white border border-gray-200 rounded-lg shadow-lg p-4 transform translate-x-full transition-transform duration-300">';
    echo '<button onclick="closeFloatingAd()" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">';
    echo '<i data-lucide="x" class="w-4 h-4"></i>';
    echo '</button>';
    echo '<div class="text-sm font-semibold text-gray-900 mb-2">Upgrade to Premium</div>';
    echo '<div class="text-xs text-gray-600 mb-3">Remove ads and unlock all features</div>';
    echo '<a href="plans.php" class="inline-block px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">';
    echo 'View Plans';
    echo '</a>';
    echo '</div>';

    echo '<script>';
    echo 'setTimeout(() => {';
    echo '  const ad = document.getElementById("floatingAd");';
    echo '  if (ad) ad.classList.remove("translate-x-full");';
    echo '}, 3000);';
    echo 'function closeFloatingAd() {';
    echo '  const ad = document.getElementById("floatingAd");';
    echo '  if (ad) ad.classList.add("translate-x-full");';
    echo '}';
    echo '</script>';
}

/**
 * Render plan comparison widget
 * 
 * @param int $user_id User ID
 */
function renderPlanComparison($user_id)
{
    $currentPlan = getUserPlan($user_id);
    $allPlans = getAllPlans();

    echo '<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold text-gray-900 mb-4">Your Current Plan</h3>';

    if ($currentPlan) {
        echo '<div class="flex items-center justify-between mb-4">';
        echo '<div>';
        echo '<div class="font-medium text-gray-900">' . htmlspecialchars($currentPlan['name']) . '</div>';
        echo '<div class="text-sm text-gray-600">' . htmlspecialchars($currentPlan['description']) . '</div>';
        echo '</div>';
        echo '<div class="text-right">';
        echo '<div class="font-bold text-lg">' . formatPlanPrice($currentPlan['price']) . '</div>';
        if ($currentPlan['expires_at']) {
            echo '<div class="text-xs text-gray-500">Expires: ' . date('M j, Y', strtotime($currentPlan['expires_at'])) . '</div>';
        }
        echo '</div>';
        echo '</div>';

        if ($currentPlan['key'] === 'free') {
            echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">';
            foreach (['premium', 'pro'] as $planKey) {
                $plan = $allPlans[$planKey];
                echo '<div class="border border-gray-200 rounded-lg p-4">';
                echo '<div class="font-medium text-gray-900 mb-1">' . htmlspecialchars($plan['name']) . '</div>';
                echo '<div class="text-sm text-gray-600 mb-2">' . htmlspecialchars($plan['description']) . '</div>';
                echo '<div class="font-bold text-blue-600 mb-3">' . formatPlanPrice($plan['price']) . '/month</div>';
                echo '<a href="payment.php?plan=' . $planKey . '" class="inline-block px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">';
                echo 'Upgrade';
                echo '</a>';
                echo '</div>';
            }
            echo '</div>';
        }
    }

    echo '</div>';
}
?>