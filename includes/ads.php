<?php
// Ads Component for Free Users
// This file contains ad display functions and components

// Floating Ad Configuration
define('FLOATING_AD_SHOW_DELAY', 3000);      // 3 seconds delay before showing
define('FLOATING_AD_AUTO_HIDE_DELAY', 30000); // 30 seconds auto-hide
define('FLOATING_AD_SLIDE_DELAY', 500);       // 0.5 seconds slide animation

// Floating Ad CSS Classes
define('FLOATING_AD_CONTAINER_CLASSES', 'fixed bottom-4 right-4 z-50 max-w-sm bg-white border border-gray-200 rounded-lg shadow-xl p-4 transform translate-x-full opacity-0 transition-all duration-500 hover:shadow-2xl');
define('FLOATING_AD_CLOSE_BUTTON_CLASSES', 'absolute top-2 right-2 text-gray-400 hover:text-gray-600 transition-colors');
define('FLOATING_AD_CTA_BASE_CLASSES', 'block w-full text-center px-4 py-2 text-white text-sm rounded-lg hover:opacity-90 transition-all duration-200 font-medium');

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
        echo '<a href="' . htmlspecialchars($ad['url']) . '">';
        echo '<img src="' . htmlspecialchars($ad['image']) . '" alt="' . htmlspecialchars($ad['title']) . '" class="max-w-full h-auto mx-auto">';
        echo '</a>';
    } else {
        echo '<div class="text-lg font-semibold text-gray-900 mb-2">' . htmlspecialchars($ad['title']) . '</div>';
        echo '<div class="text-gray-600 mb-3">' . htmlspecialchars($ad['description']) . '</div>';
        echo '<a href="' . htmlspecialchars($ad['url']) . '" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">';
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
 * Render floating ad for mobile and desktop
 * 
 * @param int $user_id User ID
 */
function renderFloatingAd($user_id)
{
    if (!shouldShowAds($user_id)) {
        return;
    }

    // Get a random ad variant
    $ad = getFloatingAdVariant();

    // Render the floating ad HTML
    renderFloatingAdHtml($ad);
    
    // Render the floating ad JavaScript
    renderFloatingAdScript();
}

/**
 * Get floating ad variants configuration
 * 
 * @return array Array of ad variants
 */
function getFloatingAdVariants()
{
    return [
        'premium_upgrade' => [
            'title' => 'Upgrade to Premium',
            'description' => 'Remove ads and unlock all features',
            'cta' => 'View Plans',
            'icon' => 'crown',
            'gradient' => 'from-blue-500 to-purple-600',
            'weight' => 40 // Higher weight = more likely to show
        ],
        'ad_free' => [
            'title' => 'Go Ad-Free',
            'description' => 'Enjoy uninterrupted gameplay',
            'cta' => 'Upgrade Now',
            'icon' => 'zap',
            'gradient' => 'from-green-500 to-blue-600',
            'weight' => 35
        ],
        'unlock_features' => [
            'title' => 'Unlock Premium',
            'description' => 'Advanced features await',
            'cta' => 'See Plans',
            'icon' => 'star',
            'gradient' => 'from-purple-500 to-pink-600',
            'weight' => 25
        ]
    ];
}

/**
 * Get a random floating ad variant based on weights
 * 
 * @return array Selected ad variant
 */
function getFloatingAdVariant()
{
    $variants = getFloatingAdVariants();
    
    // Calculate total weight
    $totalWeight = array_sum(array_column($variants, 'weight'));
    
    // Generate random number
    $random = mt_rand(1, $totalWeight);
    
    // Select variant based on weight
    $currentWeight = 0;
    foreach ($variants as $key => $variant) {
        $currentWeight += $variant['weight'];
        if ($random <= $currentWeight) {
            return $variant;
        }
    }
    
    // Fallback to first variant
    return reset($variants);
}

/**
 * Render floating ad HTML structure
 * 
 * @param array $ad Ad configuration array
 */
function renderFloatingAdHtml($ad)
{
    $adId = 'floatingAd';
    $containerClasses = FLOATING_AD_CONTAINER_CLASSES;
    $closeButtonClasses = FLOATING_AD_CLOSE_BUTTON_CLASSES;
    $iconContainerClasses = 'w-10 h-10 bg-gradient-to-r ' . $ad['gradient'] . ' rounded-full flex items-center justify-center';
    $ctaClasses = FLOATING_AD_CTA_BASE_CLASSES . ' bg-gradient-to-r ' . $ad['gradient'];
    
    ?>
    <div id="<?php echo $adId; ?>" class="<?php echo $containerClasses; ?>">
        <!-- Close Button -->
        <button onclick="closeFloatingAd()" class="<?php echo $closeButtonClasses; ?>" aria-label="Close advertisement">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
        
        <!-- Ad Content -->
        <div class="flex items-center gap-3 mb-3">
            <!-- Icon -->
            <div class="<?php echo $iconContainerClasses; ?>">
                <i data-lucide="<?php echo htmlspecialchars($ad['icon']); ?>" class="w-5 h-5 text-white"></i>
            </div>
            
            <!-- Text Content -->
            <div class="flex-1">
                <div class="text-sm font-semibold text-gray-900">
                    <?php echo htmlspecialchars($ad['title']); ?>
                </div>
                <div class="text-xs text-gray-600">
                    <?php echo htmlspecialchars($ad['description']); ?>
                </div>
            </div>
        </div>
        
        <!-- Call to Action Button -->
        <a href="plans.php" class="<?php echo $ctaClasses; ?>" role="button">
            <?php echo htmlspecialchars($ad['cta']); ?>
        </a>
        
        <!-- Advertisement Label -->
        <div class="text-xs text-gray-400 text-center mt-2">Advertisement</div>
    </div>
    <?php
}

/**
 * Render floating ad JavaScript functionality
 */
function renderFloatingAdScript()
{
    $showDelay = FLOATING_AD_SHOW_DELAY;
    $autoHideDelay = FLOATING_AD_AUTO_HIDE_DELAY;
    $slideOutDelay = FLOATING_AD_SLIDE_DELAY;
    
    ?>
    <script>
    (function() {
        'use strict';
        
        // Prevent multiple initializations
        if (typeof window.floatingAdInitialized !== 'undefined') {
            return;
        }
        
        window.floatingAdInitialized = true;
        
        // Configuration
        const config = {
            adId: 'floatingAd',
            showDelay: <?php echo $showDelay; ?>,
            autoHideDelay: <?php echo $autoHideDelay; ?>,
            slideOutDelay: <?php echo $slideOutDelay; ?>,
            hiddenClass: 'translate-x-full'
        };
        
        // Get ad element
        function getAdElement() {
            return document.getElementById(config.adId);
        }
        
        // Show the floating ad
        function showFloatingAd() {
            const ad = getAdElement();
            if (ad && ad.classList.contains(config.hiddenClass)) {
                ad.classList.remove(config.hiddenClass);
                ad.classList.remove('opacity-0')
                
                // Ensure Lucide icons are rendered
                if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                    lucide.createIcons();
                }
                
                // Set up auto-hide timer
                setTimeout(autoHideFloatingAd, config.autoHideDelay);
            }
        }
        
        // Hide the floating ad
        function hideFloatingAd() {
            const ad = getAdElement();
            if (ad && !ad.classList.contains(config.hiddenClass)) {
                ad.classList.add(config.hiddenClass);
                
                // Remove from DOM after animation completes
                setTimeout(() => {
                    if (ad && ad.parentNode) {
                        ad.parentNode.removeChild(ad);
                    }
                }, config.slideOutDelay);
            }
        }
        
        // Auto-hide the ad if user hasn't interacted
        function autoHideFloatingAd() {
            const ad = getAdElement();
            if (ad && !ad.classList.contains(config.hiddenClass)) {
                hideFloatingAd();
            }
        }
        
        // Global close function for onclick handler
        window.closeFloatingAd = function() {
            hideFloatingAd();
        };
        
        // Initialize the floating ad
        function initFloatingAd() {
            // Show ad after delay
            setTimeout(showFloatingAd, config.showDelay);
        }
        
        // Start initialization when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFloatingAd);
        } else {
            initFloatingAd();
        }
        
    })();
    </script>
    <?php
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