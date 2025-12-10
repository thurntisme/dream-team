<?php
// Dream Team Layout Template
// Provides consistent navigation and footer across all pages

function renderLayout($title, $content, $currentPage = '', $showAuth = true)
{
    $isLoggedIn = isset($_SESSION['user_id']);
    $clubName = $_SESSION['club_name'] ?? '';
    $userName = $_SESSION['user_name'] ?? '';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Dream Team</title>
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
                        <div class="hidden md:flex items-center gap-6">
                            <a href="welcome.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'welcome' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="home" class="w-4 h-4"></i>
                                <span class="font-medium">Home</span>
                            </a>
                            <a href="team.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'team' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="users" class="w-4 h-4"></i>
                                <span class="font-medium">My Team</span>
                            </a>
                            <a href="clubs.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'clubs' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="trophy" class="w-4 h-4"></i>
                                <span class="font-medium">Other Clubs</span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- User Menu -->
                    <div class="flex items-center gap-3">
                        <?php if ($isLoggedIn && $showAuth): ?>
                            <!-- User Info -->
                            <div class="hidden sm:flex items-center gap-2 text-sm text-gray-600">
                                <i data-lucide="user" class="w-4 h-4"></i>
                                <span><?php echo htmlspecialchars($userName); ?></span>
                            </div>

                            <!-- Logout Button -->
                            <button id="logoutBtn"
                                class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">Logout</span>
                            </button>
                        <?php else: ?>
                            <!-- Login/Register Links -->
                            <a href="index.php"
                                class="flex items-center gap-2 px-3 py-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <i data-lucide="log-in" class="w-4 h-4"></i>
                                <span>Login</span>
                            </a>
                        <?php endif; ?>

                        <!-- Mobile Menu Button -->
                        <?php if ($isLoggedIn && $showAuth): ?>
                            <button id="mobileMenuBtn"
                                class="md:hidden p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                                <i data-lucide="menu" class="w-5 h-5"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mobile Navigation -->
                <?php if ($isLoggedIn && $showAuth): ?>
                    <div id="mobileMenu" class="hidden md:hidden border-t py-4">
                        <div class="flex flex-col gap-2">
                            <a href="welcome.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'welcome' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="home" class="w-4 h-4"></i>
                                <span>Home</span>
                            </a>
                            <a href="team.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'team' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="users" class="w-4 h-4"></i>
                                <span>My Team</span>
                            </a>
                            <a href="clubs.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors <?php echo $currentPage === 'clubs' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-100'; ?>">
                                <i data-lucide="trophy" class="w-4 h-4"></i>
                                <span>Other Clubs</span>
                            </a>
                            <div class="border-t pt-2 mt-2">
                                <div class="flex items-center gap-2 px-3 py-2 text-sm text-gray-600">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                    <span><?php echo htmlspecialchars($userName); ?></span>
                                </div>
                            </div>
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
                        <h3 class="font-semibold text-gray-900 mb-3">Quick Links</h3>
                        <div class="space-y-2 text-sm">
                            <?php if ($isLoggedIn): ?>
                                <a href="welcome.php" class="block text-gray-600 hover:text-blue-600 transition-colors">Home</a>
                                <a href="team.php" class="block text-gray-600 hover:text-blue-600 transition-colors">My Team</a>
                                <a href="clubs.php" class="block text-gray-600 hover:text-blue-600 transition-colors">Other
                                    Clubs</a>
                            <?php else: ?>
                                <a href="index.php" class="block text-gray-600 hover:text-blue-600 transition-colors">Login</a>
                                <a href="install.php"
                                    class="block text-gray-600 hover:text-blue-600 transition-colors">Setup</a>
                            <?php endif; ?>
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
        </footer>

        <!-- JavaScript -->
        <script>
            // Initialize Lucide icons
            lucide.createIcons();

            // Mobile menu toggle
            $('#mobileMenuBtn').click(function () {
                $('#mobileMenu').toggleClass('hidden');
            });

            // Logout functionality
            $('#logoutBtn').click(function () {
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

            // Close mobile menu when clicking outside
            $(document).click(function (e) {
                if (!$(e.target).closest('#mobileMenuBtn, #mobileMenu').length) {
                    $('#mobileMenu').addClass('hidden');
                }
            });
        </script>
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