<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'layout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle plan changes (only for downgrades to free)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'downgrade_to_free') {
        $planKey = $_POST['plan'] ?? '';

        if ($planKey === 'free' && upgradeUserPlan($userId, $planKey)) {
            $message = 'Plan downgraded to Free successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to downgrade plan. Please try again.';
            $messageType = 'error';
        }
    }
}

// Get current user plan
$currentPlan = getUserPlan($userId);
$allPlans = getAllPlans();

startContent();
?>

<div class="container mx-auto px-4 max-w-6xl py-8">
    <!-- Header -->
    <div class="mb-8 text-center">
        <div class="flex items-center justify-center gap-3 mb-4">
            <i data-lucide="crown" class="w-8 h-8 text-yellow-600"></i>
            <h1 class="text-3xl font-bold text-gray-900">Choose Your Plan</h1>
        </div>
        <p class="text-gray-600 text-lg">Unlock premium features and enhance your Dream Team experience</p>
    </div>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Current Plan Status -->
    <?php if ($currentPlan): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-blue-900">Current Plan:
                        <?php echo htmlspecialchars($currentPlan['name']); ?>
                    </h3>
                    <p class="text-blue-700"><?php echo htmlspecialchars($currentPlan['description']); ?></p>
                    <?php if ($currentPlan['expires_at']): ?>
                        <p class="text-sm text-blue-600 mt-1">
                            <?php if ($currentPlan['is_active']): ?>
                                Expires on <?php echo date('F j, Y', strtotime($currentPlan['expires_at'])); ?>
                            <?php else: ?>
                                <span class="text-red-600">Expired on
                                    <?php echo date('F j, Y', strtotime($currentPlan['expires_at'])); ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-blue-900"><?php echo formatPlanPrice($currentPlan['price']); ?>
                    </div>
                    <?php if ($currentPlan['duration_days'] > 0): ?>
                        <div class="text-sm text-blue-600">per month</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
        <?php foreach ($allPlans as $planKey => $plan): ?>
            <div
                class="bg-white rounded-lg shadow-lg border-2 <?php echo $currentPlan && $currentPlan['key'] === $planKey ? 'border-blue-500' : 'border-gray-200'; ?> relative">
                <?php if ($planKey === 'premium'): ?>
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <span class="bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-medium">Most Popular</span>
                    </div>
                <?php endif; ?>

                <?php if ($currentPlan && $currentPlan['key'] === $planKey): ?>
                    <div class="absolute -top-3 right-4">
                        <span class="bg-green-600 text-white px-3 py-1 rounded-full text-sm font-medium">Current</span>
                    </div>
                <?php endif; ?>

                <div class="p-6">
                    <!-- Plan Header -->
                    <div class="text-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($plan['name']); ?>
                        </h3>
                        <div class="text-4xl font-bold text-gray-900 mb-2">
                            <?php echo formatPlanPrice($plan['price']); ?>
                            <?php if ($plan['duration_days'] > 0): ?>
                                <span class="text-lg text-gray-600">/month</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-600"><?php echo htmlspecialchars($plan['description']); ?></p>
                    </div>

                    <!-- Features List -->
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3">
                            <i data-lucide="users" class="w-5 h-5 text-green-600"></i>
                            <span class="text-gray-700">
                                <?php echo $plan['features']['max_academy_players']; ?> Academy Players
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i data-lucide="user-check" class="w-5 h-5 text-green-600"></i>
                            <span class="text-gray-700">
                                <?php echo $plan['features']['max_staff_members']; ?> Staff Members
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i data-lucide="building" class="w-5 h-5 text-green-600"></i>
                            <span class="text-gray-700">
                                <?php echo number_format($plan['features']['max_stadium_capacity']); ?> Stadium Capacity
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i data-lucide="search" class="w-5 h-5 text-green-600"></i>
                            <span class="text-gray-700">
                                <?php echo $plan['features']['weekly_scout_limit']; ?> Scouts per Week
                            </span>
                        </div>

                        <?php if (!$plan['features']['show_ads']): ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="shield-check" class="w-5 h-5 text-green-600"></i>
                                <span class="text-gray-700">Ad-Free Experience</span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="eye" class="w-5 h-5 text-gray-400"></i>
                                <span class="text-gray-500">Includes Ads</span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($plan['features']['priority_support']) && $plan['features']['priority_support']): ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="headphones" class="w-5 h-5 text-green-600"></i>
                                <span class="text-gray-700">Priority Support</span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($plan['features']['advanced_analytics']) && $plan['features']['advanced_analytics']): ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="bar-chart" class="w-5 h-5 text-green-600"></i>
                                <span class="text-gray-700">Advanced Analytics</span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($plan['features']['custom_formations']) && $plan['features']['custom_formations']): ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="layout" class="w-5 h-5 text-green-600"></i>
                                <span class="text-gray-700">Custom Formations</span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($plan['features']['exclusive_players']) && $plan['features']['exclusive_players']): ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="star" class="w-5 h-5 text-green-600"></i>
                                <span class="text-gray-700">Exclusive Players</span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($plan['features']['tournament_access']) && $plan['features']['tournament_access']): ?>
                            <div class="flex items-center gap-3">
                                <i data-lucide="trophy" class="w-5 h-5 text-green-600"></i>
                                <span class="text-gray-700">Tournament Access</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Button -->
                    <div class="text-center">
                        <?php if ($currentPlan && $currentPlan['key'] === $planKey && $currentPlan['is_active']): ?>
                            <button class="w-full px-6 py-3 bg-gray-100 text-gray-500 rounded-lg cursor-not-allowed" disabled>
                                Current Plan
                            </button>
                        <?php elseif ($planKey === 'free'): ?>
                            <?php if ($currentPlan && $currentPlan['key'] !== 'free'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="downgrade_to_free">
                                    <input type="hidden" name="plan" value="free">
                                    <button type="submit"
                                        class="w-full px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                        Downgrade to Free
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="w-full px-6 py-3 bg-gray-100 text-gray-500 rounded-lg cursor-not-allowed" disabled>
                                    Current Plan
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="payment.php?plan=<?php echo $planKey; ?>"
                                class="block w-full px-6 py-3 <?php echo $planKey === 'premium' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-purple-600 hover:bg-purple-700'; ?> text-white rounded-lg transition-colors font-medium text-center">
                                <?php echo $currentPlan && $currentPlan['key'] === 'free' ? 'Upgrade Now' : 'Switch Plan'; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-xl font-semibold text-gray-900 mb-6">Frequently Asked Questions</h3>

        <div class="space-y-6">
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Can I change my plan anytime?</h4>
                <p class="text-gray-600">Yes, you can upgrade or downgrade your plan at any time. Changes take effect
                    immediately.</p>
            </div>

            <div>
                <h4 class="font-medium text-gray-900 mb-2">What happens to my data if I downgrade?</h4>
                <p class="text-gray-600">Your data is preserved, but some features may be limited based on your new
                    plan's restrictions.</p>
            </div>

            <div>
                <h4 class="font-medium text-gray-900 mb-2">Are there any setup fees?</h4>
                <p class="text-gray-600">No, there are no setup fees. You only pay the monthly subscription price.</p>
            </div>

            <div>
                <h4 class="font-medium text-gray-900 mb-2">How do I cancel my subscription?</h4>
                <p class="text-gray-600">You can downgrade to the free plan at any time. Your premium features will
                    remain active until the end of your billing period.</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();
</script>

<?php
endContent('Plans & Pricing', 'plans');
?>