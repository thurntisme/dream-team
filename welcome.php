<?php
session_start();

require_once 'config.php';
require_once 'constants.php';
require_once 'layout.php';
require_once 'ads.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

// Validate session but allow access to welcome page
validateSession('welcome');

try {
    $db = getDbConnection();

    // Get user's club info
    $stmt = $db->prepare('SELECT club_name, formation, team FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_club = $result->fetchArray(SQLITE3_ASSOC);
    $has_club = !empty($user_club['club_name']);

    // Get other clubs (exclude current user) - ordered by team value
    $stmt = $db->prepare('SELECT club_name, name, team FROM users WHERE club_name IS NOT NULL AND club_name != "" AND id != :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $other_clubs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Calculate team value for sorting
        $team = json_decode($row['team'] ?? '[]', true);
        $teamValue = 0;
        if (is_array($team)) {
            foreach ($team as $player) {
                if ($player && isset($player['value'])) {
                    $teamValue += $player['value'];
                }
            }
        }
        $row['team_value'] = $teamValue;
        $other_clubs[] = $row;
    }

    // Sort by team value (highest first) and limit to 10
    usort($other_clubs, fn($a, $b) => $b['team_value'] - $a['team_value']);

    // Calculate user's ranking among all clubs (if user has a club)
    $user_ranking = null;
    if ($has_club) {
        $user_team = json_decode($user_club['team'] ?? '[]', true);
        $user_team_value = 0;
        if (is_array($user_team)) {
            foreach ($user_team as $player) {
                if ($player && isset($player['value'])) {
                    $user_team_value += $player['value'];
                }
            }
        }

        // Count how many clubs have higher team value
        $higher_clubs = 0;
        foreach ($other_clubs as $club) {
            if ($club['team_value'] > $user_team_value) {
                $higher_clubs++;
            }
        }
        $user_ranking = $higher_clubs + 1; // +1 because ranking starts from 1
    }

    $other_clubs = array_slice($other_clubs, 0, 10);

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}



// Start content capture
startContent();
?>

<!-- Ads for free users -->
<?php if (shouldShowAds($_SESSION['user_id'])): ?>
    <?php renderBannerAd('header', $_SESSION['user_id']); ?>
<?php endif; ?>

<!-- Plan comparison for free users -->
<?php if (shouldShowAds($_SESSION['user_id'])): ?>
    <?php renderPlanComparison($_SESSION['user_id']); ?>
<?php endif; ?>

<div class="container mx-auto p-4 max-w-4xl flex items-center justify-center min-h-[calc(100vh-200px)]">
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
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <div class="text-xl font-bold"><?php echo htmlspecialchars($user_club['club_name']); ?>
                                </div>
                                <?php if ($user_ranking && $user_team_value > 0): ?>
                                    <?php if ($user_ranking === 1): ?>
                                        <span
                                            class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-1 rounded-full">
                                            <i data-lucide="crown" class="w-3 h-3"></i>
                                            #1
                                        </span>
                                    <?php elseif ($user_ranking === 2): ?>
                                        <span
                                            class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-xs font-semibold px-2 py-1 rounded-full">
                                            <i data-lucide="medal" class="w-3 h-3"></i>
                                            #2
                                        </span>
                                    <?php elseif ($user_ranking === 3): ?>
                                        <span
                                            class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-1 rounded-full">
                                            <i data-lucide="award" class="w-3 h-3"></i>
                                            #3
                                        </span>
                                    <?php elseif ($user_ranking <= 10): ?>
                                        <span
                                            class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">
                                            <i data-lucide="hash" class="w-3 h-3"></i>
                                            #<?php echo $user_ranking; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm text-gray-600">Formation:
                                <?php echo htmlspecialchars($user_club['formation'] ?? 'Not set'); ?>
                            </div>
                        </div>
                    </div>

                    <?php
                    $team = json_decode($user_club['team'] ?? '[]', true);
                    $player_count = is_array($team) ? count(array_filter($team, fn($p) => $p !== null)) : 0;

                    // Calculate user's team value
                    $user_team_value = 0;
                    if (is_array($team)) {
                        foreach ($team as $player) {
                            if ($player && isset($player['value'])) {
                                $user_team_value += $player['value'];
                            }
                        }
                    }
                    ?>
                    <div class="space-y-2 mb-4">
                        <div class="text-sm text-gray-600">
                            <i data-lucide="users" class="w-4 h-4 inline"></i>
                            <?php echo $player_count; ?> / 11 players selected
                        </div>
                        <?php if ($user_team_value > 0): ?>
                            <div class="text-sm text-green-600 font-semibold">
                                <i data-lucide="trending-up" class="w-4 h-4 inline"></i>
                                Team Value: <?php echo formatMarketValue($user_team_value); ?>
                            </div>
                        <?php endif; ?>
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

        <!-- Top Clubs List -->
        <div class="p-8 bg-white rounded-lg shadow">
            <div class="flex items-center gap-2 mb-6">
                <i data-lucide="users" class="w-6 h-6 text-gray-600"></i>
                <h2 class="text-xl font-bold">Top Clubs</h2>
            </div>

            <?php if (count($other_clubs) > 0): ?>
                <div class="space-y-3 max-h-80 overflow-y-auto mb-4">
                    <?php foreach (array_slice($other_clubs, 0, 5) as $index => $club): ?>
                        <div class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i data-lucide="shield" class="w-5 h-5 text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold"><?php echo htmlspecialchars($club['club_name']); ?></div>
                                    <?php if ($club['team_value'] > 0): ?>
                                        <?php if ($index === 0): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs font-semibold px-1.5 py-0.5 rounded-full">
                                                <i data-lucide="crown" class="w-2.5 h-2.5"></i>
                                                #1
                                            </span>
                                        <?php elseif ($index === 1): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-xs font-semibold px-1.5 py-0.5 rounded-full">
                                                <i data-lucide="medal" class="w-2.5 h-2.5"></i>
                                                #2
                                            </span>
                                        <?php elseif ($index === 2): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-semibold px-1.5 py-0.5 rounded-full">
                                                <i data-lucide="award" class="w-2.5 h-2.5"></i>
                                                #3
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-500">by <?php echo htmlspecialchars($club['name']); ?></div>
                                <?php if ($club['team_value'] > 0): ?>
                                    <div class="text-xs text-green-600 font-semibold">
                                        <i data-lucide="trending-up" class="w-3 h-3 inline mr-1"></i>
                                        <?php echo formatMarketValue($club['team_value']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($other_clubs) > 5): ?>
                        <div class="text-center text-sm text-gray-500 py-2">
                            and <?php echo count($other_clubs) - 5; ?> more clubs...
                        </div>
                    <?php endif; ?>
                </div>
                <a href="clubs.php"
                    class="block w-full bg-gray-100 text-gray-700 py-2 rounded-lg hover:bg-gray-200 text-center transition-colors">
                    <i data-lucide="eye" class="w-4 h-4 inline mr-1"></i>
                    View All Clubs
                </a>
            <?php else: ?>
                <div class="text-center text-gray-500 py-8">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 text-gray-400"></i>
                    <p>No clubs yet. Be the first!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    $('#clubForm').submit(function (e) {
        e.preventDefault();
        $.post('save_club.php', $(this).serialize(), function (response) {
            if (response.redirect) {
                window.location.href = response.redirect;
            } else if (response.success) {
                window.location.href = 'team.php';
            }
        }, 'json');
    });
</script>

<!-- Floating ad for free users -->
<?php if (shouldShowAds($_SESSION['user_id'])): ?>
    <?php renderFloatingAd($_SESSION['user_id']); ?>
<?php endif; ?>

<?php
// End content capture and render layout
endContent('Welcome', 'welcome');
?>