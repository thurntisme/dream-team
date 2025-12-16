<?php
require_once 'partials/meta.php';
require_once 'partials/analytics.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php generateMetaTags('landing'); ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/landing.css">

    <?php generateStructuredData('WebApplication'); ?>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i data-lucide="shield" class="w-8 h-8 text-blue-600 mr-2"></i>
                        <span class="text-xl font-bold gradient-text">Dream Team</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="#features"
                        class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">Features</a>
                    <a href="#how-it-works"
                        class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">How
                        It Works</a>
                    <a href="#testimonials"
                        class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition-colors">Reviews</a>
                    <a href="index.php?from_landing=1"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">Play
                        Now</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient lg:pt-[120px] md:pt-20 pt-16 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-white">
                    <h1 class="text-4xl md:text-6xl font-bold mb-6 leading-tight">
                        Build Your <span class="text-yellow-300">Dream Team</span>
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-blue-100">
                        The ultimate football manager experience. Create your squad, challenge rivals, and climb to the
                        top of the league.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <a href="index.php?from_landing=1"
                            class="bg-yellow-400 text-gray-900 px-8 py-4 rounded-lg font-bold text-lg hover:bg-yellow-300 transition-colors text-center">
                            Start Playing Free
                        </a>
                        <a href="#how-it-works"
                            class="border-2 border-white text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white hover:text-gray-900 transition-colors text-center">
                            Learn More
                        </a>
                    </div>
                    <div class="flex items-center gap-6 text-blue-100">
                        <div class="flex items-center gap-2">
                            <i data-lucide="users" class="w-5 h-5"></i>
                            <span>10,000+ Players</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="star" class="w-5 h-5 text-yellow-300"></i>
                            <span>4.8/5 Rating</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="zap" class="w-5 h-5"></i>
                            <span>100% Free</span>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="animate-float">
                        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md mx-auto">
                            <div class="text-center mb-6">
                                <div
                                    class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="trophy" class="w-8 h-8 text-white"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900">Manchester United FC</h3>
                                <p class="text-gray-600">Level 5 - Elite Club</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-green-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-green-700">€250M</div>
                                    <div class="text-sm text-green-600">Team Value</div>
                                </div>
                                <div class="bg-blue-50 rounded-lg p-3 text-center">
                                    <div class="text-2xl font-bold text-blue-700">11/11</div>
                                    <div class="text-sm text-blue-600">Players</div>
                                </div>
                            </div>
                            <div
                                class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white rounded-lg p-4 text-center">
                                <div class="font-bold">Challenge Available!</div>
                                <div class="text-sm opacity-90">vs Arsenal FC - €15M Prize</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Why Choose Dream Team?
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Experience the most realistic and engaging football management game with cutting-edge features
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Build Your Squad</h3>
                    <p class="text-gray-600 mb-4">Choose from thousands of real players or create custom ones. Build the
                        perfect team with strategic formations and player combinations.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Real player database
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Custom player creation
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Multiple formations
                        </li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <i data-lucide="zap" class="w-6 h-6 text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Live Match Simulation</h3>
                    <p class="text-gray-600 mb-4">Watch your team play in real-time with dynamic events, goals, and
                        tactical decisions that affect the outcome.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Real-time events
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Interactive field view
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Dynamic outcomes
                        </li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <i data-lucide="trophy" class="w-6 h-6 text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Challenge System</h3>
                    <p class="text-gray-600 mb-4">Challenge other clubs worldwide, earn rewards, and climb the global
                        rankings to become the ultimate champion.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Global competitions
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Reward system
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Club rankings
                        </li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                        <i data-lucide="trending-up" class="w-6 h-6 text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Club Progression</h3>
                    <p class="text-gray-600 mb-4">Grow your club from amateur to elite level with a comprehensive
                        progression system and unlock exclusive rewards.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            5-tier level system
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Level bonuses
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Exclusive rewards
                        </li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                        <i data-lucide="banknote" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Financial Management</h3>
                    <p class="text-gray-600 mb-4">Manage your club's budget wisely, invest in players, and make
                        strategic financial decisions to build a winning team.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Budget management
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Player investments
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Prize earnings
                        </li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <i data-lucide="smartphone" class="w-6 h-6 text-indigo-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Cross-Platform</h3>
                    <p class="text-gray-600 mb-4">Play anywhere, anytime on any device. Your progress syncs across all
                        platforms for seamless gaming experience.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Web browser
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Mobile responsive
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                            Cloud sync
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    How It Works
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Get started in minutes and begin your journey to football management glory
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-white">1</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Create Your Club</h3>
                    <p class="text-gray-600">Sign up and create your football club. Choose your club name and start with
                        a €200M budget to build your dream team.</p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-white">2</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Build Your Team</h3>
                    <p class="text-gray-600">Select players from our extensive database or create custom ones. Choose
                        formations and tactics that suit your playing style.</p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-white">3</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Challenge & Win</h3>
                    <p class="text-gray-600">Challenge other clubs, watch live match simulations, and earn rewards to
                        improve your team and climb the rankings.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    What Players Say
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Join thousands of satisfied players who love Dream Team
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-gray-50 rounded-xl p-8">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-6">"The most realistic football manager game I've ever played. The live
                        match simulation is incredible and the challenge system keeps me coming back for more!"</p>
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold">MJ</span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">Mike Johnson</div>
                            <div class="text-sm text-gray-600">Elite Club Manager</div>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-gray-50 rounded-xl p-8">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-6">"Love the strategic depth and the financial management aspect.
                        Building my team from scratch and watching them grow has been an amazing experience."</p>
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold">SR</span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">Sarah Rodriguez</div>
                            <div class="text-sm text-gray-600">Professional Level</div>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-gray-50 rounded-xl p-8">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-6">"The best part is that it's completely free! No pay-to-win mechanics,
                        just pure skill and strategy. Highly recommend to any football fan."</p>
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold">DL</span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">David Lee</div>
                            <div class="text-sm text-gray-600">Semi-Professional</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 hero-gradient">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">
                Ready to Build Your Dream Team?
            </h2>
            <p class="text-xl text-blue-100 mb-8">
                Join thousands of players worldwide and start your football management journey today. It's completely
                free!
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="index.php?from_landing=1"
                    class="bg-yellow-400 text-gray-900 px-8 py-4 rounded-lg font-bold text-lg hover:bg-yellow-300 transition-colors">
                    Start Playing Now
                </a>
                <a href="#features"
                    class="border-2 border-white text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white hover:text-gray-900 transition-colors">
                    Learn More
                </a>
            </div>
            <div class="mt-8 text-blue-100 text-sm">
                No download required • Play in your browser • 100% Free
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center mb-4">
                        <i data-lucide="shield" class="w-8 h-8 text-blue-400 mr-2"></i>
                        <span class="text-xl font-bold">Dream Team</span>
                    </div>
                    <p class="text-gray-400 mb-4 max-w-md">
                        The ultimate football manager game where you build your dream team, challenge other clubs, and
                        compete for glory.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="facebook" class="w-6 h-6"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="twitter" class="w-6 h-6"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="instagram" class="w-6 h-6"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Game</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="index.php?from_landing=1" class="hover:text-white transition-colors">Play Now</a>
                        </li>
                        <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition-colors">How It Works</a></li>
                        <li><a href="#testimonials" class="hover:text-white transition-colors">Reviews</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Support</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white transition-colors">Help Center</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Contact Us</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Terms of Service</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Dream Team. All rights reserved. Made with ❤️ for football fans
                    worldwide.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="assets/js/landing.js"></script>

    <?php
    // Add analytics tracking
    if (shouldLoadAnalytics()) {
        renderGoogleAnalytics();
        renderFacebookPixel();
    }
    ?>
</body>

</html>