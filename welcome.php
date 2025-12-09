<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['club_name']) && $_SESSION['club_name']) {
    header('Location: team.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dream Team - Welcome</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <div class="flex items-center justify-center mb-8">
            <i data-lucide="shield" class="w-16 h-16 text-blue-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-center mb-2">Welcome,
            <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
        <p class="text-center text-gray-600 mb-8">Create your dream team</p>

        <form id="clubForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Club Name</label>
                <input type="text" name="club_name" required
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter your club name">
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Continue</button>
        </form>
    </div>

    <script>
        lucide.createIcons();

        $('#clubForm').submit(function (e) {
            e.preventDefault();
            $.post('save_club.php', $(this).serialize(), function (response) {
                if (response.success) {
                    window.location.href = 'team.php';
                }
            }, 'json');
        });
    </script>
</body>

</html>