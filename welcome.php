<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get user's club info
$db = new SQLite3('dreamteam.db');
$stmt = $db->prepare('SELECT club_name, formation, team FROM users WHERE id = :id');
$stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$user_club = $result->fetchArray(SQLITE3_ASSOC);
$has_club = !empty($user_club['club_name']);

// Get other clubs
$stmt = $db->prepare('SELECT club_name, name FROM users WHERE club_name IS NOT NULL AND club_name != "" ORDER BY id DESC LIMIT 10');
$result = $stmt->execute();
$other_clubs = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $other_clubs[] = $row;
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

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Club Creation Form or User Club Info -->
        <div class="p-8 bg-white rounded-lg shadow">
            <div class="flex items-center justify-center mb-8">
                <i data-lucide="shield" class="w-16 h-16 text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-center mb-2">Welcome,
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
            </h1>

            <?php if ($has_club): ?>
                <!-- User has a club -->
                <p class="text-center text-gray-600 mb-8">Your club is ready</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                            <i data-lucide="shield" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <div class="text-xl font-bold"><?php echo htmlspecialchars($user_club['club_name']); ?></div>
                            <div class="text-sm text-gray-600">Formation:
                                <?php echo htmlspecialchars($user_club['formation'] ?? 'Not set'); ?></div>
                        </div>
                    </div>

                    <?php
                    $team = json_decode($user_club['team'] ?? '[]', true);
                    $player_count = is_array($team) ? count(array_filter($team, function ($p) {
                        return $p !== null; })) : 0;
                    ?>
                    <div class="text-sm text-gray-600 mb-4">
                        <i data-lucide="users" class="w-4 h-4 inline"></i>
                        <?php echo $player_count; ?> / 11 players selected
                    </div>
                </div>

                <a href="team.php"
                    class="block w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 text-center">
                    Manage Your Club
                </a>
            <?php else: ?>
                <!-- User doesn't have a club -->
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
            <?php endif; ?>
        </div>

        <!-- Other Clubs List -->
        <div class="p-8 bg-white rounded-lg shadow">
            <div class="flex items-center gap-2 mb-6">
                <i data-lucide="users" class="w-6 h-6 text-gray-600"></i>
                <h2 class="text-xl font-bold">Other Clubs</h2>
            </div>

            <?php if (count($other_clubs) > 0): ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($other_clubs as $club): ?>
                        <div class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i data-lucide="shield" class="w-5 h-5 text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold"><?php echo htmlspecialchars($club['club_name']); ?></div>
                                <div class="text-sm text-gray-500">by <?php echo htmlspecialchars($club['name']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500 py-8">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 text-gray-400"></i>
                    <p>No clubs yet. Be the first!</p>
                </div>
            <?php endif; ?>
        </div>
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