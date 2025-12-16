// Authentication page JavaScript
document.addEventListener('DOMContentLoaded', function() {
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
});