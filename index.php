<?php
session_start();

require_once 'config.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dream Team - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
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

    <script>
        lucide.createIcons();

        $('#loginTab').click(function () {
            $(this).addClass('border-blue-600 font-semibold text-black').removeClass('border-gray-200 text-gray-500');
            $('#registerTab').addClass('border-gray-200 text-gray-500').removeClass('border-blue-600 font-semibold text-black');
            $('#loginForm').removeClass('hidden');
            $('#registerForm').addClass('hidden');
        });

        $('#registerTab').click(function () {
            $(this).addClass('border-blue-600 font-semibold text-black').removeClass('border-gray-200 text-gray-500');
            $('#loginTab').addClass('border-gray-200 text-gray-500').removeClass('border-blue-600 font-semibold text-black');
            $('#registerForm').removeClass('hidden');
            $('#loginForm').addClass('hidden');
        });

        $('#loginForm').submit(function (e) {
            e.preventDefault();
            $.post('auth.php', $(this).serialize() + '&action=login', function (response) {
                if (response.redirect) {
                    window.location.href = response.redirect;
                } else if (response.success) {
                    window.location.href = 'welcome.php';
                } else {
                    $('#message').html('<span class="text-red-600">' + response.message + '</span>');
                }
            }, 'json');
        });

        $('#registerForm').submit(function (e) {
            e.preventDefault();
            $.post('auth.php', $(this).serialize() + '&action=register', function (response) {
                if (response.redirect) {
                    window.location.href = response.redirect;
                } else if (response.success) {
                    window.location.href = 'welcome.php';
                } else {
                    $('#message').html('<span class="text-red-600">' + response.message + '</span>');
                }
            }, 'json');
        });
    </script>
</body>

</html>