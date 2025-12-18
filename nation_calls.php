<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';

// Check if user is logged in and has club name
requireClubName('nation_calls');

$db = getDbConnection();
$userId = $_SESSION['user_id'];

// Database tables are now created in install.php

// Get user data
$stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

// Get nation call history and statistics
$history = getNationCallHistory($db, $userId, 20);
$stats = getNationCallStats($db, $userId);

// Calculate matches until next nation call
$matchesPlayed = $user['matches_played'] ?? 0;
$matchesUntilNext = 8 - ($matchesPlayed % 8);
if ($matchesUntilNext === 8)
    $matchesUntilNext = 0; // Just had a nation call

$db->close();

startContent();
?>

<div class="container mx-auto py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="flag" class="w-8 h-8 text-blue-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Nation Calls</h1>
                <p class="text-gray-600">International call-ups and earnings from your players</p>
            </div>
        </div>
        <div class="text-right">
            <a href="league.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition-colors flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to League
            </a>
        </div>
    </div>

    <!-- Next Nation Call Info -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                    <i data-lucide="calendar" class="w-6 h-6 text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-blue-900">Next Nation Call</h3>
                    <?php if ($matchesUntilNext === 0): ?>
                        <p class="text-blue-700">Nation calls are triggered after every 8 matches. Check back after your
                            next match!</p>
                    <?php else: ?>
                        <p class="text-blue-700">
                            <span class="font-medium"><?php echo $matchesUntilNext; ?></span>
                            match<?php echo $matchesUntilNext > 1 ? 'es' : ''; ?> remaining until next nation call
                            opportunity
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-blue-600"><?php echo $matchesUntilNext; ?></div>
                <div class="text-sm text-blue-500">matches left</div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Calls</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_calls']; ?></p>
                </div>
                <i data-lucide="phone-call" class="w-8 h-8 text-blue-600"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Earnings</p>
                    <p class="text-2xl font-bold text-green-600">
                        <?php echo formatMarketValue($stats['total_earnings']); ?>
                    </p>
                </div>
                <i data-lucide="euro" class="w-8 h-8 text-green-600"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Average Earnings</p>
                    <p class="text-2xl font-bold text-yellow-600">
                        <?php echo formatMarketValue($stats['avg_earnings']); ?>
                    </p>
                </div>
                <i data-lucide="trending-up" class="w-8 h-8 text-yellow-600"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Players Called</p>
                    <p class="text-2xl font-bold text-purple-600"><?php echo $stats['unique_players_called']; ?></p>
                </div>
                <i data-lucide="users" class="w-8 h-8 text-purple-600"></i>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
            How Nation Calls Work
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="text-blue-600 font-bold">8</span>
                </div>
                <h4 class="font-medium mb-2">Every 8 Matches</h4>
                <p class="text-sm text-gray-600">Nation calls are triggered automatically after every 8 matches played
                </p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="star" class="w-6 h-6 text-green-600"></i>
                </div>
                <h4 class="font-medium mb-2">Best Performers</h4>
                <p class="text-sm text-gray-600">Only your best performing players (2-5) get selected for international
                    duty</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="coins" class="w-6 h-6 text-yellow-600"></i>
                </div>
                <h4 class="font-medium mb-2">Earn Rewards</h4>
                <p class="text-sm text-gray-600">Receive €50K+ per called player based on their rating and performance
                </p>
            </div>
        </div>
    </div>

    <!-- Nation Call History -->
    <div class="bg-white rounded-lg shadow border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="history" class="w-5 h-5 text-gray-600"></i>
                Nation Call History
            </h3>
        </div>

        <?php if (empty($history)): ?>
            <div class="p-8 text-center">
                <i data-lucide="flag" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Nation Calls Yet</h3>
                <p class="text-gray-600">Play more matches to trigger nation calls for your best players.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($history as $call): ?>
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="flag" class="w-5 h-5 text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold">International Call-Up</h4>
                                    <p class="text-sm text-gray-600"><?php echo $call['time_ago']; ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-green-600">
                                    +<?php echo formatMarketValue($call['total_reward']); ?>
                                </div>
                                <div class="text-sm text-gray-500">Earnings</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($call['called_players'] as $player): ?>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                                <span class="text-white text-sm font-bold"><?php echo $player['rating']; ?></span>
                                            </div>
                                            <div>
                                                <h5 class="font-medium text-sm"><?php echo htmlspecialchars($player['name']); ?>
                                                </h5>
                                                <p class="text-xs text-gray-600"><?php echo $player['position']; ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-green-600">
                                                +<?php echo formatMarketValue(calculateNationCallReward($player)); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                Score: <?php echo number_format($player['performance_score'], 1); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

<?php
endContent('Nation Calls', 'nation_calls');
?>