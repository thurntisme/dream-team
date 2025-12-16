<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';
require_once 'includes/league_functions.php';
require_once 'includes/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if season summary exists in session
if (!isset($_SESSION['season_end_summary'])) {
    header('Location: league.php');
    exit;
}

$season_summary = $_SESSION['season_end_summary'];
$user_id = $_SESSION['user_id'];

startContent();
?>
<div class="container mx-auto py-6">
    <div class="container mx-auto px-4 py-6 max-w-6xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full mb-4">
                <i data-lucide="trophy" class="w-10 h-10 text-white"></i>
            </div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Season <?php echo $season_summary['season']; ?> Results
            </h1>
            <p class="text-xl text-gray-600">Final standings and rewards</p>
        </div>

        <!-- User's Final Position -->
        <div class="mb-8 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <span class="text-2xl font-bold"><?php echo $season_summary['user_position']; ?></span>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold">
                                <?php echo htmlspecialchars($season_summary['user_team_data']['name']); ?>
                            </h2>
                            <p class="text-blue-100">
                                <?php echo $season_summary['user_division'] == 1 ? 'Premier League' : 'Championship'; ?>
                                -
                                <?php echo ordinal($season_summary['user_position']); ?> Place
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold">
                            +<?php echo formatMarketValue($season_summary['total_reward']); ?>
                        </div>
                        <div class="text-blue-100">Total Rewards</div>
                    </div>
                </div>

                <!-- Team Stats -->
                <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white bg-opacity-10 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold">
                            <?php echo $season_summary['user_team_data']['matches_played']; ?>
                        </div>
                        <div class="text-sm text-blue-100">Matches</div>
                    </div>
                    <div class="bg-white bg-opacity-10 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-green-300">
                            <?php echo $season_summary['user_team_data']['wins']; ?>
                        </div>
                        <div class="text-sm text-blue-100">Wins</div>
                    </div>
                    <div class="bg-white bg-opacity-10 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold"><?php echo $season_summary['user_team_data']['goals_for']; ?>
                        </div>
                        <div class="text-sm text-blue-100">Goals For</div>
                    </div>
                    <div class="bg-white bg-opacity-10 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-yellow-300">
                            <?php echo $season_summary['user_team_data']['points']; ?>
                        </div>
                        <div class="text-sm text-blue-100">Points</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rewards Breakdown -->
    <div class="mb-8 bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <i data-lucide="coins" class="w-6 h-6"></i>
                Season Rewards Breakdown
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php foreach ($season_summary['user_rewards'] as $reward): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">
                                <i data-lucide="<?php echo $reward['type'] == 'prize' ? 'award' : 'shield'; ?>"
                                    class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <div class="font-medium"><?php echo htmlspecialchars($reward['description']); ?></div>
                                <div class="text-sm text-gray-600 capitalize"><?php echo $reward['type']; ?></div>
                            </div>
                        </div>
                        <div class="text-xl font-bold text-green-600">+<?php echo formatMarketValue($reward['amount']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold">Total Rewards:</span>
                    <span
                        class="text-2xl font-bold text-green-600">+<?php echo formatMarketValue($season_summary['total_reward']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Season Statistics -->
    <div class="mb-8 bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <i data-lucide="bar-chart-3" class="w-6 h-6"></i>
                Season <?php echo $season_summary['season']; ?> Statistics
            </h3>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Top Scorer -->
                <?php if ($season_summary['season_stats']['top_scorer']): ?>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="target" class="w-8 h-8 text-white"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900">Top Scorer</h4>
                        <p class="font-medium">
                            <?php echo htmlspecialchars($season_summary['season_stats']['top_scorer']['player_name']); ?>
                        </p>
                        <p class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($season_summary['season_stats']['top_scorer']['club_name']); ?>
                        </p>
                        <p class="text-lg font-bold text-green-600">
                            <?php echo $season_summary['season_stats']['top_scorer']['goals']; ?> goals
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Top Assists -->
                <?php if ($season_summary['season_stats']['top_assists']): ?>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-purple-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="users" class="w-8 h-8 text-white"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900">Most Assists</h4>
                        <p class="font-medium">
                            <?php echo htmlspecialchars($season_summary['season_stats']['top_assists']['player_name']); ?>
                        </p>
                        <p class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($season_summary['season_stats']['top_assists']['club_name']); ?>
                        </p>
                        <p class="text-lg font-bold text-purple-600">
                            <?php echo $season_summary['season_stats']['top_assists']['assists']; ?> assists
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Best Player -->
                <?php if ($season_summary['season_stats']['best_player']): ?>
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="star" class="w-8 h-8 text-white"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900">Best Player</h4>
                        <p class="font-medium">
                            <?php echo htmlspecialchars($season_summary['season_stats']['best_player']['player_name']); ?>
                        </p>
                        <p class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($season_summary['season_stats']['best_player']['club_name']); ?>
                        </p>
                        <p class="text-lg font-bold text-blue-600">
                            <?php echo number_format($season_summary['season_stats']['best_player']['avg_rating'], 1); ?>
                            rating
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Final League Table -->
    <div class="mb-8 bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-gray-700 to-gray-800 text-white p-4">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <i data-lucide="list" class="w-6 h-6"></i>
                Final Premier League Table
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pos</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Club</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">MP</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">W</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">D</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">L</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">GF</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">GA</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">GD</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pts</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($season_summary['premier_standings'] as $index => $team): ?>
                        <tr class="<?php echo $team['is_user'] ? 'bg-blue-50 border-l-4 border-blue-500' : ''; ?>">
                            <td class="px-4 py-3 text-sm font-medium">
                                <span class="<?php
                                if ($index < 4)
                                    echo 'text-green-600'; // Champions League
                                elseif ($index < 6)
                                    echo 'text-blue-600'; // Europa League
                                elseif ($index >= 17)
                                    echo 'text-red-600'; // Relegation
                                else
                                    echo 'text-gray-900';
                                ?>"><?php echo $index + 1; ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <?php if ($team['is_user']): ?>
                                        <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center">
                                            <i data-lucide="user" class="w-3 h-3 text-white"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-6 h-6 bg-gray-400 rounded-full"></div>
                                    <?php endif; ?>
                                    <span class="font-medium <?php echo $team['is_user'] ? 'text-blue-600' : ''; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm"><?php echo $team['matches_played']; ?></td>
                            <td class="px-4 py-3 text-center text-sm"><?php echo $team['wins']; ?></td>
                            <td class="px-4 py-3 text-center text-sm"><?php echo $team['draws']; ?></td>
                            <td class="px-4 py-3 text-center text-sm"><?php echo $team['losses']; ?></td>
                            <td class="px-4 py-3 text-center text-sm"><?php echo $team['goals_for']; ?></td>
                            <td class="px-4 py-3 text-center text-sm"><?php echo $team['goals_against']; ?></td>
                            <td
                                class="px-4 py-3 text-center text-sm <?php echo $team['goal_difference'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $team['goal_difference'] >= 0 ? '+' : ''; ?>
                                <?php echo $team['goal_difference']; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-sm font-bold"><?php echo $team['points']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Relegation/Promotion Summary -->
    <div class="mb-8 grid md:grid-cols-2 gap-6">
        <!-- Relegated Teams -->
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-4">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i data-lucide="arrow-down" class="w-5 h-5"></i>
                    Relegated to Championship
                </h3>
            </div>
            <div class="p-4">
                <div class="space-y-3">
                    <?php foreach ($season_summary['relegated_teams'] as $index => $team): ?>
                        <div class="flex items-center gap-3 p-3 bg-red-50 rounded-lg">
                            <div
                                class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                <?php echo 18 + $index; ?>
                            </div>
                            <span class="font-medium <?php echo $team['is_user'] ? 'text-red-700' : ''; ?>">
                                <?php echo htmlspecialchars($team['name']); ?>
                                <?php if ($team['is_user']): ?>
                                    <span class="text-xs bg-red-600 text-white px-2 py-1 rounded ml-2">YOUR CLUB</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Promoted Teams -->
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i data-lucide="arrow-up" class="w-5 h-5"></i>
                    Promoted to Premier League
                </h3>
            </div>
            <div class="p-4">
                <div class="space-y-3">
                    <?php foreach ($season_summary['promoted_teams'] as $index => $team): ?>
                        <div class="flex items-center gap-3 p-3 bg-green-50 rounded-lg">
                            <div
                                class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                <?php echo $index + 1; ?>
                            </div>
                            <span class="font-medium"><?php echo htmlspecialchars($team['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Continue Button -->
    <div class="text-center">
        <a href="league.php?tab=standings"
            class="inline-flex items-center gap-2 bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 font-bold text-lg transition-colors">
            <i data-lucide="arrow-right" class="w-5 h-5"></i>
            Continue to Season
            <?php echo $season_summary['season'] + 1; ?>
        </a>
    </div>
</div>

</div>

<script>
    lucide.createIcons();

    // Clear session data when user leaves the page
    window.addEventListener('beforeunload', function () {
        fetch('api/clear_season_summary.php', { method: 'POST' });
    });
</script>

<?php
endContent('Season Results', 'season_results');
?>""