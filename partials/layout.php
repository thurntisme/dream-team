<?php
// Dream Team Layout Template
// Provides consistent navigation and footer across all pages

function renderLayout($title, $content, $currentPage = '', $showAuth = true)
{
    // Ensure constants and helpers are available
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/includes/ads.php';

    $isLoggedIn = isset($_SESSION['user_id']);
    $clubName = $_SESSION['club_name'] ?? '';
    $userName = $_SESSION['user_name'] ?? '';

    // Function to calculate team level based on various factors
    function calculateTeamLevel($budget, $fans, $team)
    {
        $level = 1;

        // Budget factor (0-3 levels)
        if ($budget >= 500000000) { // 500M+
            $level += 3;
        } elseif ($budget >= 200000000) { // 200M+
            $level += 2;
        } elseif ($budget >= 50000000) { // 50M+
            $level += 1;
        }

        // Fan factor (0-2 levels)
        if ($fans >= 100000) { // 100K+ fans
            $level += 2;
        } elseif ($fans >= 50000) { // 50K+ fans
            $level += 1;
        }

        // Team size factor (0-2 levels)
        $teamSize = count($team);
        if ($teamSize >= 20) {
            $level += 2;
        } elseif ($teamSize >= 15) {
            $level += 1;
        }

        // Load players data to calculate team quality
        if (file_exists('assets/players.json') && !empty($team)) {
            $players_json = file_get_contents('assets/players.json');
            $players_data = json_decode($players_json, true) ?? [];

            $totalRating = 0;
            $playerCount = 0;

            foreach ($team as $playerId) {
                if (isset($players_data[$playerId])) {
                    $totalRating += $players_data[$playerId]['rating'];
                    $playerCount++;
                }
            }

            if ($playerCount > 0) {
                $avgRating = $totalRating / $playerCount;

                // Team quality factor (0-3 levels)
                if ($avgRating >= 85) { // World class team
                    $level += 3;
                } elseif ($avgRating >= 80) { // Elite team
                    $level += 2;
                } elseif ($avgRating >= 75) { // Good team
                    $level += 1;
                }
            }
        }

        // Cap the level at 10
        return min($level, 10);
    }

    // Get user budget, fans, and calculate team level if logged in
    $userBudget = 0;
    $userFans = 0;
    $teamLevel = 1;
    if ($isLoggedIn && isDatabaseAvailable()) {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT budget, fans, team FROM users WHERE id = :user_id');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $userData = $result->fetchArray(SQLITE3_ASSOC);
            $userBudget = $userData['budget'] ?? 0;
            $userFans = $userData['fans'] ?? 5000;
            $userTeam = json_decode($userData['team'] ?? '[]', true);

            // Calculate team level based on multiple factors
            $teamLevel = calculateTeamLevel($userBudget, $userFans, $userTeam);

            $db->close();
        } catch (Exception $e) {
            $userBudget = 0;
            $userFans = 5000;
            $teamLevel = 1;
        }
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <?php
        // Determine page type for SEO
        $pageType = 'default';
        if (strpos($title, 'Team') !== false)
            $pageType = 'team';
        elseif (strpos($title, 'Club') !== false)
            $pageType = 'clubs';
        elseif (strpos($title, 'Match') !== false)
            $pageType = 'match';
        elseif (strpos($title, 'Welcome') !== false)
            $pageType = 'game';

        // Include meta.php if it exists
        if (file_exists('partials/meta.php')) {
            require_once 'partials/meta.php';
            generateMetaTags($pageType);
        } else {
            // Fallback meta tags
            echo '<meta charset="UTF-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            echo '<title>' . htmlspecialchars($title) . ' - Dream Team</title>';
        }
        ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    </head>

    <body class="bg-gray-50 min-h-screen flex flex-col">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm border-b">
            <div class="container mx-auto px-4 max-w-6xl">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo & Brand -->
                    <div class="flex items-center gap-4">
                        <a href="<?php echo $isLoggedIn ? 'welcome.php' : 'index.php'; ?>"
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
                            <a href="welcome.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'welcome' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="home" class="w-4 h-4"></i>
                                <span class="font-medium">Home</span>
                            </a>

                            <!-- Team Management Dropdown -->
                            <div class="relative">
                                <button
                                    class="nav-dropdown-btn flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo in_array($currentPage, ['team', 'stadium', 'staff', 'academy', 'young_player_market']) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
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

                            <a href="shop.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'shop' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
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
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($clubName); ?></div>
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
                                            <div class="flex items-center gap-1 mt-1">
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
                                                    Team Level
                                                    <?php echo $teamLevel; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <a href="settings.php"
                                        class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i data-lucide="settings" class="w-4 h-4"></i>
                                        <span>Settings</span>
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
                            <button id="mobileMenuBtn" class="md:hidden p-2 text-gray-600 hover:text-gray-900
                        hover:bg-gray-100 rounded-lg">
                                <i data-lucide="menu" class="w-5 h-5"></i>
                            </button>
                        <?php else: ?>
                            <!-- Login/Register Links -->
                            <a href="index.php"
                                class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors">
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
                            <a href="settings.php"
                                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors <?php echo $currentPage === 'settings' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="settings" class="w-5 h-5"></i>
                                <span class="font-medium">Settings</span>
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

        <!-- Main Content -->
        <main class="flex-1">
            <?php echo $content; ?>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t mt-auto">
            <?php if ($isLoggedIn): ?>
                <!-- Ads for free users -->
                <?php if (shouldShowAds($_SESSION['user_id'])): ?>
                    <div class="container mx-auto px-4 max-w-6xl py-4">
                        <?php renderBannerAd('footer', $_SESSION['user_id']); ?>
                    </div>
                <?php endif; ?>

                <!-- Logged-in User Footer -->
                <div class="container mx-auto px-4 max-w-6xl py-6">
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
                                    <span class=" font-medium text-gray-900">
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
                                    <span>Team Level:</span>
                                    <span class="font-medium text-gray-900">
                                        <?php echo $teamLevel; ?>
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
                            <span>© 2024 Dream Team</span>
                            <span>•</span>
                            <span>Football Manager Game</span>
                            <span>•</span>
                            <span>Enjoy the Game!</span>

                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Guest User Footer -->
                <div class="container mx-auto px-4 max-w-6xl py-8">
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
                            </div>

                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </footer>

        <!-- JavaScript -->
        <script>
            // Initialize Lucide icons
            lucide.createIcons();

            // Session management
            <?php if ($isLoggedIn && isset($_SESSION['expire_time'])): ?>
                const sessionExpireTime = <?php echo $_SESSION['expire_time']; ?>;
                const currentTime = Math.floor(Date.now() / 1000);
                const timeUntilExpiry = sessionExpireTime - currentTime;

                // Check session expiration every minute
                setInterval(function () {
                    const now = Math.floor(Date.now() / 1000);
                    const timeLeft = sessionExpireTime - now;

                    // Show warning 5 minutes before expiration
                    if (timeLeft <= 300 && timeLeft > 0) {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;

                        Swal.fire({
                            title: 'Session Expiring Soon',
                            text: `Your session will expire in ${minutes}:${seconds.toString().padStart(2, '0')}. Do you want to extend it?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Extend Session',
                            cancelButtonText: 'Logout'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Extend session by making a simple request
                                $.post('auth.php', { action: 'extend_session' }, function (response) {
                                    if (response.success) {
                                        location.reload();
                                    }
                                }, 'json');
                            } else {
                                // Logout
                                $.post('auth.php', { action: 'logout' }, function () {
                                    window.location.href = 'index.php';
                                }, 'json');
                            }
                        });
                    } else if (timeLeft <= 0) {
                        // Session expired
                        Swal.fire({
                            title: 'Session Expired',
                            text: 'Your session has expired. Please login again.',
                            icon: 'error',
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'Login'
                        }).then(() => {
                            window.location.href = 'index.php';

                        });
                    }
                }, 60000); // Check every minute
            <?php endif; ?>

            // Navigation dropdown functionality
            $('.nav-dropdown-btn').click(function (e) {
                e.stopPropagation();
                const dropdown = $(this).siblings('.nav-dropdown');

                // Close all other dropdowns
                $('.nav-dropdown').not(dropdown).addClass('hidden');

                // Toggle current dropdown
                dropdown.toggleClass('hidden');
            });

            // User dropdown toggle
            $('#userMenuBtn').click(function (e) {
                e.stopPropagation();
                // Close nav dropdowns
                $('.nav-dropdown').addClass('hidden');
                $('#userDropdown').toggleClass('hidden');
            });

            // Mobile menu toggle
            $('#mobileMenuBtn').click(function (e) {
                e.stopPropagation();
                $('#mobileMenu').toggleClass('hidden');
            });

            // Logout functionality (both desktop and mobile)
            $('#logoutBtn, #mobileLogoutBtn').click(function () {
                Swal.fire({
                    title: 'Logout?',
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, Logout',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('auth.php', { action: 'logout' }, function () {
                            window.location.href = 'index.php';
                        }, 'json').fail(function () {
                            // Fallback if auth.php fails
                            window.location.href = 'index.php';
                        });
                    }
                });
            });

            // Close dropdowns when clicking outside
            $(document).click(function (e) {
                // Close navigation dropdowns
                if (!$(e.target).closest('.nav-dropdown-btn, .nav-dropdown').length) {
                    $('.nav-dropdown').addClass('hidden');
                }

                // Close user dropdown
                if (!$(e.target).closest('#userMenuBtn, #userDropdown').length) {
                    $('#userDropdown').addClass('hidden');
                }

                // Close mobile menu
                if (!$(e.target).closest('#mobileMenuBtn, #mobileMenu').length) {
                    $('#mobileMenu').addClass('hidden');
                }
            });

            // Close dropdowns when pressing Escape
            $(document).keydown(function (e) {
                if (e.key === 'Escape') {
                    $('.nav-dropdown').addClass('hidden');
                    $('#userDropdown').addClass('hidden');
                    $('#mobileMenu').addClass('hidden');
                }
            });
        </script>

        <?php
        // Add analytics tracking if available
        if (file_exists('partials/analytics.php')) {
            require_once 'partials/analytics.php';
            if (shouldLoadAnalytics()) {
                renderGoogleAnalytics();
                renderFacebookPixel();
            }
        }
        ?>
    </body>

    </html>
    <?php
    return ob_get_clean();
}

// Helper function to start content capture
function startContent()
{
    ob_start();
}

// Helper function to end content capture and render layout
function endContent($title, $currentPage = '', $showAuth = true)
{
    $content = ob_get_clean();
    echo renderLayout($title, $content, $currentPage, $showAuth);
}
?>