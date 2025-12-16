<?php
// Dream Team Layout Template
// Provides consistent navigation and footer across all pages

function renderLayout($title, $content, $currentPage = '', $showAuth = true, $skipDbCheck = false)
{
    // Ensure constants and helpers are available
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../includes/ads.php';
    require_once __DIR__ . '/../includes/routing.php';
    require_once __DIR__ . '/nav.php';
    require_once __DIR__ . '/footer.php';

    // Check if database is available, redirect to install if not (unless explicitly skipped)
    if (!$skipDbCheck && !isDatabaseAvailable()) {
        header('Location: install.php');
        exit;
    }

    $isLoggedIn = isset($_SESSION['user_id']);
    $clubName = $_SESSION['club_name'] ?? '';
    $userName = $_SESSION['user_name'] ?? '';



    // Get user budget, fans, and club level if logged in
    $userBudget = 0;
    $userFans = 0;
    $clubLevel = 1;
    $clubExp = 0;
    if ($isLoggedIn && isDatabaseAvailable()) {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT budget, fans, club_level, club_exp FROM users WHERE id = :user_id');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $userData = $result->fetchArray(SQLITE3_ASSOC);
            $userBudget = $userData['budget'] ?? 0;
            $userFans = $userData['fans'] ?? 5000;
            $clubLevel = $userData['club_level'] ?? 1;
            $clubExp = $userData['club_exp'] ?? 0;

            $db->close();
        } catch (Exception $e) {
            $userBudget = 0;
            $userFans = 5000;
            $clubLevel = 1;
            $clubExp = 0;
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
        if (file_exists(__DIR__ . '/meta.php')) {
            require_once __DIR__ . '/meta.php';
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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    </head>

    <body class="bg-gray-50 min-h-screen flex flex-col">
        <?php echo renderNavigation($isLoggedIn, $showAuth, $currentPage, $clubName, $userName, $userBudget, $userFans, $clubLevel); ?>

        <!-- Main Content -->
        <main class="flex-1">
            <?php echo $content; ?>
        </main>
        
        <?php echo renderFooter($isLoggedIn, $clubName, $userName, $userBudget, $userFans, $clubLevel); ?>

        <!-- JavaScript -->
        <script>
            // Initialize Lucide icons
            lucide.createIcons();

            // Global level-up notification handler
            window.handleLevelUpNotification = function (response) {
                if (response && response.level_up) {
                    const levelUp = response.level_up;
                    const levelsGained = levelUp.levels_gained || 1;
                    const newLevel = levelUp.new_level;

                    // Show celebration notification
                    Swal.fire({
                        title: '🎉 Level Up!',
                        html: `
                            <div class="text-center">
                                <div class="text-6xl mb-4">⭐</div>
                                <div class="text-xl font-bold text-purple-600 mb-2">
                                    Club Level ${newLevel}
                                </div>
                                <div class="text-gray-600 mb-4">
                                    ${levelsGained > 1 ? `Gained ${levelsGained} levels!` : 'Level up achieved!'}
                                </div>
                                <div class="bg-gradient-to-r from-purple-100 to-blue-100 rounded-lg p-3 text-sm">
                                    <div class="font-semibold text-purple-800">New Benefits Unlocked!</div>
                                    <div class="text-purple-700 mt-1">
                                        • Increased match rewards<br>
                                        • Better player development<br>
                                        • Enhanced club prestige
                                    </div>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#7c3aed',
                        confirmButtonText: 'Awesome!',
                        showClass: {
                            popup: 'animate__animated animate__bounceIn'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__bounceOut'
                        }
                    }).then(() => {
                        // Refresh the page to update level displays
                        window.location.reload();
                    });
                }
            };

            // Enhanced AJAX success handler for level-ups
            window.handleApiResponse = function (response, successCallback) {
                if (response.success) {
                    // Handle level up first if present
                    if (response.level_up) {
                        handleLevelUpNotification(response);
                    } else if (successCallback) {
                        successCallback(response);
                    }
                } else {
                    // Handle error
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            };

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
                                    window.location.href = '/login';
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
                            window.location.href = '/login';

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
                            window.location.href = '/login';
                        }, 'json').fail(function () {
                            // Fallback if auth.php fails
                            window.location.href = '/login';
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
        if (file_exists(__DIR__ . '/analytics.php')) {
            require_once __DIR__ . '/analytics.php';
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
function endContent($title, $currentPage = '', $showAuth = true, $skipDbCheck = false)
{
    $content = ob_get_clean();
    echo renderLayout($title, $content, $currentPage, $showAuth, $skipDbCheck);
}
?>