<?php
// Navigation Component
// Requires: $isLoggedIn, $showAuth, $currentPage, $clubName, $userName, $userBudget, $userFans, $clubLevel variables

function renderNavigation($isLoggedIn, $showAuth, $currentPage, $clubName, $userName, $userBudget, $userFans, $clubLevel)
{
    ob_start();
    ?>
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="container mx-auto">
            <div class="flex justify-between items-center h-16">
                <!-- Logo & Brand -->
                <div class="flex items-center gap-4">
                    <a href="<?php echo $isLoggedIn ? route('welcome') : route('login'); ?>"
                        class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                        <i data-lucide="shield" class="w-8 h-8 text-blue-600"></i>
                        <div>
                            <div class="font-bold text-xl text-gray-900">Dream Team</div>
                            <?php if ($clubName): ?>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($clubName); ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>

                <!-- Main Navigation -->
                <?php if ($isLoggedIn && $showAuth): ?>
                    <div class="hidden md:flex items-center gap-1">
                        <a href="<?php echo route('welcome'); ?>"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'welcome' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="home" class="w-4 h-4"></i>
                            <span class="font-medium">Home</span>
                        </a>

                        <!-- Team Management Dropdown -->
                        <div class="relative">
                            <button
                                class="nav-dropdown-btn flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo in_array($currentPage, ['team', 'stadium', 'staff', 'academy', 'young_player_market', 'shirt_numbers', 'contracts']) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="users" class="w-4 h-4"></i>
                                <span class="font-medium">Team</span>
                                <i data-lucide="chevron-down" class="w-3 h-3"></i>
                            </button>
                            <div
                                class="nav-dropdown hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                                <a href="team.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                    <span>My Team</span>
                                </a>
                                <a href="contracts.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                    <span>Contracts</span>
                                </a>
                                <a href="shirt_numbers.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="shirt" class="w-4 h-4"></i>
                                    <span>Shirt Numbers</span>
                                </a>
                                <a href="staff.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="user-check" class="w-4 h-4"></i>
                                    <span>Club Staff</span>
                                </a>
                                <a href="stadium.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="building" class="w-4 h-4"></i>
                                    <span>Stadium</span>
                                </a>
                                <a href="academy.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                                    <span>Academy</span>
                                </a>
                            </div>
                        </div>

                        <!-- Players Dropdown -->
                        <div class="relative">
                            <button
                                class="nav-dropdown-btn flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo in_array($currentPage, ['transfer', 'scouting']) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                                <span class="font-medium">Players</span>
                                <i data-lucide="chevron-down" class="w-3 h-3"></i>
                            </button>
                            <div
                                class="nav-dropdown hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                                <a href="transfer.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                                    <span>Transfer Market</span>
                                </a>
                                <a href="scouting.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="search" class="w-4 h-4"></i>
                                    <span>Scouting</span>
                                </a>
                            </div>
                        </div>

                        <!-- Competition Dropdown -->
                        <div class="relative">
                            <button
                                class="nav-dropdown-btn flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo in_array($currentPage, ['league', 'clubs']) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="trophy" class="w-4 h-4"></i>
                                <span class="font-medium">Competition</span>
                                <i data-lucide="chevron-down" class="w-3 h-3"></i>
                            </button>
                            <div
                                class="nav-dropdown hidden absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                                <a href="league.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="trophy" class="w-4 h-4"></i>
                                    <span>League</span>
                                </a>
                                <a href="clubs.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                    <span>Other Clubs</span>
                                </a>
                            </div>
                        </div>

                        <a href="news.php"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors
                    <?php echo $currentPage === 'news' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="newspaper" class="w-4 h-4"></i>
                            <span class="font-medium">News</span>
                        </a>

                        <a href="nation_calls.php"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors
                    <?php echo $currentPage === 'nation_calls' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="flag" class="w-4 h-4"></i>
                            <span class="font-medium">Nation Calls</span>
                        </a>

                        <a href="shop.php"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors
                    <?php echo $currentPage === 'shop' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                            <span class="font-medium">Shop</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- User Menu -->
                <div class="flex items-center gap-3">
                    <?php if ($isLoggedIn && $showAuth): ?>
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userMenuBtn"
                                class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                    <i data-lucide="user" class="w-4 h-4 text-white"></i>
                                </div>
                                <div class="hidden sm:block text-left">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($userName); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($clubName); ?>
                                    </div>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="userDropdown"
                                class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($userName); ?>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($clubName); ?></div>
                                    <?php if ($isLoggedIn): ?>
                                        <div class=" flex items-center gap-1 mt-1">
                                            <i data-lucide="wallet" class="w-3 h-3 text-green-600"></i>
                                            <span class="text-xs font-medium text-green-600">
                                                <?php echo formatMarketValue($userBudget); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i data-lucide="users" class="w-3 h-3 text-blue-600"></i>
                                            <span class="text-xs font-medium text-blue-600">
                                                <?php echo number_format($userFans); ?> fans
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i data-lucide="star" class="w-3 h-3 text-yellow-600"></i>
                                            <span class="text-xs font-medium text-yellow-600">
                                                Club Level
                                                <?php echo $clubLevel; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <a href="settings.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    <span>Settings</span>
                                </a>

                                <a href="feedback.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                                    <span>Feedback</span>
                                    <span class="ml-auto text-xs bg-green-100 text-green-600 px-2 py-1 rounded-full">💰
                                        Earn</span>
                                </a>

                                <a href="support.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="help-circle" class="w-4 h-4"></i>
                                    <span>Support Tickets</span>
                                </a>

                                <a href="plans.php"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i data-lucide="crown" class="w-4 h-4"></i>
                                    <span>Plans & Pricing</span>
                                </a>

                                <div class="border-t border-gray-100 my-1"></div>

                                <button id="logoutBtn"
                                    class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 w-full text-left">
                                    <i data-lucide="log-out" class="w-4 h-4"></i>
                                    <span>Logout</span>
                                </button>
                            </div>
                        </div>

                        <!-- Mobile Menu Button -->
                        <button id="mobileMenuBtn"
                            class="md:hidden p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                    <?php else: ?>
                        <!-- Login/Register Links -->
                        <a href="index.php" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg
                    transition-colors">
                            <i data-lucide="log-in" class="w-4 h-4"></i>
                            <span>Login</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <?php if ($isLoggedIn && $showAuth): ?>
                <div id="mobileMenu" class="hidden md:hidden border-t py-4">
                    <div class="flex flex-col gap-1">
                        <a href="welcome.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'welcome' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="home" class="w-5 h-5"></i>
                            <span class="font-medium">Home</span>
                        </a>

                        <a href="team.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'team' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="users" class="w-5 h-5"></i>
                            <span class="font-medium">My Team</span>
                        </a>

                        <a href="transfer.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'transfer' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                            <span class="font-medium">Transfer Market</span>
                        </a>

                        <a href="league.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'league' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="trophy" class="w-5 h-5"></i>
                            <span class="font-medium">League</span>
                        </a>

                        <a href="news.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'news' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="newspaper" class="w-5 h-5"></i>
                            <span class="font-medium">News</span>
                        </a>

                        <a href="nation_calls.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'nation_calls' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="flag" class="w-5 h-5"></i>
                            <span class="font-medium">Nation Calls</span>
                        </a>

                        <a href="shop.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'shop' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                            <span class="font-medium">Shop</span>
                        </a>

                        <div class="border-t border-gray-200 my-2"></div>

                        <a href="settings.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'settings' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="settings" class="w-5 h-5"></i>
                            <span class="font-medium">Settings</span>
                        </a>

                        <a href="feedback.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'feedback' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="message-circle" class="w-5 h-5"></i>
                            <span class="font-medium">Feedback</span>
                            <span class="ml-auto text-xs bg-green-100 text-green-600 px-2 py-1 rounded-full">💰 Earn</span>
                        </a>

                        <a href="support.php"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'support' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                            <i data-lucide="help-circle" class="w-5 h-5"></i>
                            <span class="font-medium">Support Tickets</span>
                        </a>

                        <div class="border-t border-gray-200 my-2"></div>

                        <button id="mobileLogoutBtn"
                            class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors w-full text-left">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                            <span class="font-medium">Logout</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}
?>