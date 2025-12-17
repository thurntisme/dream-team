<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';





$userId = $_SESSION['user_id'];

// Get plan from URL parameter
$planKey = $_GET['plan'] ?? '';
$allPlans = getAllPlans();

// Validate plan
if (!isset($allPlans[$planKey])) {
    header('Location: plans.php');
    exit;
}

$plan = $allPlans[$planKey];
$currentPlan = getUserPlan($userId);

startContent();
?>

<div class="container mx-auto px-4 max-w-4xl py-8">
    <!-- Success Header -->
    <div class="text-center mb-8">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="check-circle" class="w-12 h-12 text-green-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
        <p class="text-lg text-gray-600">Welcome to <?php echo htmlspecialchars($plan['name']); ?></p>
    </div>

    <!-- Success Details -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Plan Details -->
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Your New Plan</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Plan:</span>
                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Price:</span>
                        <span
                            class="font-medium text-gray-900"><?php echo formatPlanPrice($plan['price']); ?>/month</span>
                    </div>
                    <?php if ($currentPlan && $currentPlan['expires_at']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Next Billing:</span>
                            <span
                                class="font-medium text-gray-900"><?php echo date('F j, Y', strtotime($currentPlan['expires_at'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="font-medium text-green-600">Active</span>
                    </div>
                </div>
            </div>

            <!-- Features Unlocked -->
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Features Unlocked</h2>
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <i data-lucide="users" class="w-5 h-5 text-green-600"></i>
                        <span class="text-gray-700"><?php echo $plan['features']['max_academy_players']; ?> Academy
                            Players</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="user-check" class="w-5 h-5 text-green-600"></i>
                        <span class="text-gray-700"><?php echo $plan['features']['max_staff_members']; ?> Staff
                            Members</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="building" class="w-5 h-5 text-green-600"></i>
                        <span
                            class="text-gray-700"><?php echo number_format($plan['features']['max_stadium_capacity']); ?>
                            Stadium Capacity</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i data-lucide="search" class="w-5 h-5 text-green-600"></i>
                        <span class="text-gray-700"><?php echo $plan['features']['weekly_scout_limit']; ?> Scouts per
                            Week</span>
                    </div>
                    <?php if (!$plan['features']['show_ads']): ?>
                        <div class="flex items-center gap-3">
                            <i data-lucide="shield-check" class="w-5 h-5 text-green-600"></i>
                            <span class="text-gray-700">Ad-Free Experience</span>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Next Steps -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
        <h3 class="text-lg font-semibold text-blue-900 mb-4">What's Next?</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="graduation-cap" class="w-6 h-6 text-blue-600"></i>
                </div>
                <h4 class="font-medium text-blue-900 mb-2">Expand Your Academy</h4>
                <p class="text-sm text-blue-700">Scout more young players and build your academy</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="user-check" class="w-6 h-6 text-blue-600"></i>
                </div>
                <h4 class="font-medium text-blue-900 mb-2">Hire More Staff</h4>
                <p class="text-sm text-blue-700">Add coaches and staff to improve your team</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="building" class="w-6 h-6 text-blue-600"></i>
                </div>
                <h4 class="font-medium text-blue-900 mb-2">Upgrade Stadium</h4>
                <p class="text-sm text-blue-700">Increase capacity and generate more revenue</p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="text-center space-y-4">
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="welcome.php"
                class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <div class="flex items-center justify-center gap-2">
                    <i data-lucide="home" class="w-5 h-5"></i>
                    <span>Go to Dashboard</span>
                </div>
            </a>
            <a href="academy.php"
                class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors">
                <div class="flex items-center justify-center gap-2">
                    <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                    <span>Visit Academy</span>
                </div>
            </a>
            <a href="plans.php"
                class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-center gap-2">
                    <i data-lucide="crown" class="w-5 h-5"></i>
                    <span>Manage Plan</span>
                </div>
            </a>
        </div>

        <div class="text-sm text-gray-500">
            Need help? <a href="mailto:support@dreamteam.com" class="text-blue-600 hover:text-blue-800">Contact
                Support</a>
        </div>
    </div>

    <!-- Confirmation Email Notice -->
    <div class="mt-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
        <div class="flex items-start gap-3">
            <i data-lucide="mail" class="w-5 h-5 text-gray-600 mt-0.5"></i>
            <div class="text-sm text-gray-700">
                <div class="font-medium mb-1">Confirmation Email Sent</div>
                <div>We've sent a confirmation email with your receipt and plan details. If you don't see it in your
                    inbox, please check your spam folder.</div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/common.js"></script>
<script>
    // Confetti animation (optional)
    function createConfetti() {
        const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];
        const confettiCount = 50;

        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-10px';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '9999';
            confetti.style.borderRadius = '50%';

            document.body.appendChild(confetti);

            const animation = confetti.animate([
                { transform: 'translateY(-10px) rotate(0deg)', opacity: 1 },
                { transform: `translateY(100vh) rotate(${Math.random() * 360}deg)`, opacity: 0 }
            ], {
                duration: Math.random() * 2000 + 1000,
                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
            });

            animation.onfinish = () => confetti.remove();
        }
    }

    // Trigger confetti on page load
    setTimeout(createConfetti, 500);
</script>

<?php
endContent('Payment Successful', 'payment_success');
?>