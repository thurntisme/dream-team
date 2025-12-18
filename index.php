<?php
session_start();

require_once 'config/config.php';
require_once 'partials/layout.php';
require_once 'partials/meta.php';
require_once 'partials/analytics.php';

// If user is already logged in, redirect to welcome page
if (isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

// If user came from landing page, set the landing_visited cookie
if (isset($_GET['from_landing'])) {
    // Set cookie to remember they've clicked Play from landing page
    setcookie('landing_visited', '1', time() + 86400 * 7, '/'); // 7 days
}

// If user hasn't visited landing page and didn't come from landing, redirect to landing page
if (!isset($_GET['from_landing']) && !isset($_COOKIE['landing_visited'])) {
    header('Location: landing.php');
    exit;
}

// Start content capture
startContent();
?>
<div class="flex items-center justify-center min-h-[calc(100vh-200px)]">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <div class="flex items-center justify-center mb-8">
            <i data-lucide="trophy" class="w-12 h-12 text-blue-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-center mb-8">Dream Team</h1>

        <div class="mb-6 grid grid-cols-2">
            <button id="loginTab" class="py-2 border-b-2 border-blue-600 font-semibold">Login</button>
            <button id="registerTab" class="py-2 border-b-2 border-gray-200 text-gray-500">Register</button>
        </div>

        <form id="loginForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Login</button>
        </form>

        <form id="registerForm" class="space-y-4 hidden">
            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Register</button>
        </form>

        <div id="message" class="mt-4 text-center text-sm"></div>
    </div>

    <script src="assets/js/auth.js"></script>
</div>
</div>

<?php
// End content capture and render layout
endContent('Login', 'index', false);
?>