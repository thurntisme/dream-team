<?php
// Footer Component
// Requires: $isLoggedIn, $clubName, $userName, $userBudget, $userFans, $clubLevel variables

function renderFooter($isLoggedIn, $clubName, $userName, $userBudget, $userFans, $clubLevel)
{
    ob_start();
    ?>
    <!-- Footer -->
    <footer class="bg-white border-t mt-auto">
        <?php if ($isLoggedIn): ?>
            <!-- Ads for free users -->
            <?php if (shouldShowAds($_SESSION['user_id'])): ?>
                <div class="container mx-auto py-4">
                    <?php renderBannerAd('footer', $_SESSION['user_id']); ?>
                </div>
            <?php endif; ?>

            <!-- Logged-in User Footer -->
            <div class="container mx-auto py-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Club Status -->
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="shield" class="w-5 h-5 text-gray-700"></i>
                            <h3 class="font-semibold text-gray-900">
                                <?php echo htmlspecialchars($clubName); ?>
                            </h3>
                        </div>
                        <div class="text-sm text-gray-600">
                            <div class="flex justify-between items-center">
                                <span>Manager:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($userName); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span>Budget:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo formatMarketValue($userBudget); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span>Fans:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo number_format($userFans); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span>Club Level:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo $clubLevel; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="zap" class="w-5 h-5 text-gray-700"></i>
                            <h3 class="font-semibold text-gray-900">Quick Actions</h3>
                        </div>
                        <div class="space-y-1">
                            <a href="team.php"
                                class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">
                                <i data-lucide="users" class="w-3 h-3"></i>
                                <span>Manage Team</span>
                            </a>
                            <a href="transfer.php"
                                class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">
                                <i data-lucide="shopping-cart" class="w-3 h-3"></i>
                                <span>Buy Players</span>
                            </a>
                            <a href="scouting.php"
                                class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">
                                <i data-lucide="search" class="w-3 h-3"></i>
                                <span>Scout Players</span>
                            </a>
                            <a href="match-simulator.php"
                                class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">
                                <i data-lucide="play" class="w-3 h-3"></i>
                                <span>Challenge Club</span>
                            </a>
                            <a href="shirt_numbers.php"
                                class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">
                                <i data-lucide="shirt" class="w-3 h-3"></i>
                                <span>Shirt Numbers</span>
                            </a>
                        </div>
                    </div>

                    <!-- Game Stats -->
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="trophy" class="w-5 h-5 text-gray-700"></i>
                            <h3 class="font-semibold text-gray-900">Game Progress</h3>
                        </div>
                        <div class="text-sm text-gray-600">
                            <div class="flex justify-between items-center">
                                <span>Status:</span>
                                <span class="font-medium text-gray-900">Active</span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span>Playing Since:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo date('M Y'); ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <a href="clubs.php" class="text-xs text-gray-600 hover:text-gray-900 underline">
                                    View Rankings →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Copyright -->
                <div class="border-t border-gray-200 mt-6 pt-4 text-center">
                    <div class="flex items-center justify-center gap-4 text-sm text-gray-500">
                        <span>© <?php echo date('Y'); ?> Dream Team</span>
                        <span>•</span>
                        <span>Football Manager Game</span>
                        <span>•</span>
                        <span>Enjoy the Game!</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Guest User Footer -->
            <div class="container mx-auto py-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Brand -->
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="shield" class="w-6 h-6 text-blue-600"></i>
                            <span class="font-bold text-lg text-gray-900">Dream Team</span>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">
                            Build your ultimate football team with legendary players and tactical formations.
                            Compete with other managers and create your dream lineup.
                        </p>
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span>© 2024 Dream Team</span>
                            <span>•</span>
                            <span>Football Manager Game</span>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Get Started</h3>
                        <div class="space-y-2 text-sm">
                            <a href="index.php" class="block text-gray-600 hover:text-blue-600 transition-colors">Login</a>
                            <a href="install.php" class="block text-gray-600 hover:text-blue-600 transition-colors">Setup
                                Game</a>
                        </div>
                    </div>

                    <!-- Features -->
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Features</h3>
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <i data-lucide="users" class="w-3 h-3"></i>
                                <span>250+ Players</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="layout" class="w-3 h-3"></i>
                                <span>9 Formations</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="trophy" class="w-3 h-3"></i>
                                <span>Club Rankings</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="trending-up" class="w-3 h-3"></i>
                                <span>Market Values</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="heart" class="w-3 h-3"></i>
                                <span>Injury System</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="shirt" class="w-3 h-3"></i>
                                <span>Shirt Numbers</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </footer>
    <?php
    return ob_get_clean();
}
?>