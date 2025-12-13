<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'layout.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

// Require user to be logged in and have a club name
requireClubName('league');

require_once 'includes/league_functions.php';

try {
    $db = getDbConnection();

    // Get current user
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        header('Location: index.php');
        exit;
    }

    // Validate club eligibility for league
    $club_validation = validateClubForLeague($user);
    if (!$club_validation['is_valid']) {
        // Store validation errors in session for display
        $_SESSION['league_validation_errors'] = $club_validation['errors'];
        header('Location: team.php?league_validation_failed=1');
        exit;
    }

    // Initialize league if not exists
    initializeLeague($db, $user_id);

    // Get current season
    $current_season = getCurrentSeason($db);

    // Handle match simulation - simulate entire gameweek
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_match'])) {
        // Re-validate club before match simulation
        $club_validation = validateClubForLeague($user);
        if (!$club_validation['is_valid']) {
            $_SESSION['league_validation_errors'] = $club_validation['errors'];
            header('Location: team.php?league_validation_failed=1');
            exit;
        }

        $match_id = (int) $_POST['match_id'];
        $gameweek_results = simulateGameweek($db, $match_id, $user_id);

        // Store results in session for display
        $_SESSION['gameweek_results'] = $gameweek_results;

        header('Location: league.php?tab=standings&gameweek_completed=1');
        exit;
    }

    // Get league standings
    $standings = getLeagueStandings($db, $current_season);

    // Get user's match history
    $user_matches = getUserMatches($db, $user_id, $current_season);

    // Get upcoming matches for calendar
    $upcoming_matches = getUpcomingMatches($db, $user_id, $current_season);

    // Get current gameweek
    $current_gameweek = getCurrentGameweek($db, $current_season);

    // Check if user has a match in current gameweek
    $user_has_match = hasUserMatchInGameweek($db, $user_id, $current_season, $current_gameweek);

    // Get current validation status for display
    $current_validation = validateClubForLeague($user);

    $db->close();
} catch (Exception $e) {
    error_log("League page error: " . $e->getMessage());
    header('Location: welcome.php?error=league_unavailable');
    exit;
}

startContent();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="trophy" class="w-8 h-8 text-yellow-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Premier League</h1>
                <p class="text-gray-600">Season <?php echo $current_season; ?> • Gameweek
                    <?php echo $current_gameweek; ?>
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            <!-- Club Validation Status -->
            <?php if (!$current_validation['is_valid']): ?>
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded-lg flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    <span class="font-medium">Club Not Eligible</span>
                </div>
            <?php elseif ($user_has_match): ?>
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded-lg flex items-center gap-2">
                    <i data-lucide="play" class="w-4 h-4"></i>
                    <span class="font-medium">You have a match this gameweek!</span>
                </div>
            <?php else: ?>
                <button id="simulateAllBtn"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i data-lucide="fast-forward" class="w-4 h-4"></i>
                    Simulate Gameweek
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Club Validation Warning -->
    <?php if (!$current_validation['is_valid']): ?>
        <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600 mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="font-semibold text-yellow-800 mb-2">League Participation Requirements</h3>
                    <p class="text-yellow-700 text-sm mb-3">
                        Your club doesn't meet the minimum requirements to participate in league matches. 
                        Please address these issues:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-yellow-700 text-sm mb-3">
                        <?php foreach ($current_validation['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="team.php" class="inline-flex items-center gap-2 bg-yellow-600 text-white px-3 py-2 rounded text-sm hover:bg-yellow-700">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        Fix Team Setup
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Gameweek Results -->
    <?php if (isset($_GET['gameweek_completed']) && isset($_SESSION['gameweek_results'])): ?>
        <?php
        $results = $_SESSION['gameweek_results'];
        // Don't clear session data yet - we'll clear it when user closes or changes tabs
        ?>
        <div id="gameweekResults" class="mb-6 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i data-lucide="trophy" class="w-6 h-6"></i>
                        <div>
                            <h3 class="text-lg font-bold">Gameweek <?php echo $results['gameweek']; ?> Results</h3>
                            <p class="text-green-100">All matches completed</p>
                        </div>
                    </div>
                    <button id="closeGameweekResults" class="text-white hover:text-green-200 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="p-4">
                <!-- User's Match Result (if they had one) -->
                <?php if ($results['user_match']): ?>
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                            <i data-lucide="user" class="w-4 h-4"></i>
                            Your Match Result
                        </h4>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <span
                                    class="font-medium <?php echo $results['user_match']['is_home'] ? 'text-blue-600' : ''; ?>">
                                    <?php echo htmlspecialchars($results['user_match']['home_team']); ?>
                                </span>
                                <div class="bg-white px-3 py-1 rounded border font-bold text-lg">
                                    <?php echo $results['user_match']['home_score']; ?> -
                                    <?php echo $results['user_match']['away_score']; ?>
                                </div>
                                <span
                                    class="font-medium <?php echo !$results['user_match']['is_home'] ? 'text-blue-600' : ''; ?>">
                                    <?php echo htmlspecialchars($results['user_match']['away_team']); ?>
                                </span>
                            </div>
                            <div class="text-right">
                                <?php
                                $user_score = $results['user_match']['is_home'] ? $results['user_match']['home_score'] : $results['user_match']['away_score'];
                                $opponent_score = $results['user_match']['is_home'] ? $results['user_match']['away_score'] : $results['user_match']['home_score'];

                                if ($user_score > $opponent_score) {
                                    echo '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">WIN</span>';
                                } elseif ($user_score == $opponent_score) {
                                    echo '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">DRAW</span>';
                                } else {
                                    echo '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">LOSS</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- League Position & Budget Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <?php if ($results['user_position']): ?>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-700">League Position:</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl font-bold <?php
                                    if ($results['user_position'] <= 4)
                                        echo 'text-green-600'; // Champions League
                                    elseif ($results['user_position'] <= 6)
                                        echo 'text-blue-600'; // Europa League  
                                    elseif ($results['user_position'] >= 18)
                                        echo 'text-red-600'; // Relegation
                                    else
                                        echo 'text-gray-900';
                                    ?>"><?php echo $results['user_position']; ?></span>
                                    <span class="text-gray-500">/ 20</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($results['budget_earned'] > 0): ?>
                        <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-green-700 flex items-center gap-2">
                                    <i data-lucide="coins" class="w-4 h-4"></i>
                                    Budget Earned:
                                </span>
                                <span class="text-2xl font-bold text-green-600">
                                    +<?php echo formatMarketValue($results['budget_earned']); ?>
                                </span>
                            </div>
                            <div class="text-xs text-green-600 mt-1">
                                Added to your club budget
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- All Match Results -->
                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-900 mb-3">All Gameweek Results:</h4>
                    <div class="grid gap-2 max-h-48 overflow-y-auto">
                        <?php foreach ($results['all_results'] as $match): ?>
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded text-sm">
                                <div class="flex items-center gap-3 flex-1">
                                    <span class="w-32 text-right"><?php echo htmlspecialchars($match['home_team']); ?></span>
                                    <div class="bg-white px-2 py-1 rounded border font-medium min-w-[50px] text-center">
                                        <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                    </div>
                                    <span class="w-32"><?php echo htmlspecialchars($match['away_team']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (isset($_GET['simulated'])): ?>
        <!-- Fallback for old simulation method -->
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                <span class="font-semibold text-green-800">
                    Gameweek completed! Simulated <?php echo (int) $_GET['simulated']; ?> matches.
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button class="tab-btn py-2 px-1 border-b-2 font-medium text-sm active" data-tab="standings">
                <i data-lucide="bar-chart-3" class="w-4 h-4 inline mr-1"></i>
                Standings
            </button>
            <button class="tab-btn py-2 px-1 border-b-2 font-medium text-sm" data-tab="calendar">
                <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                Calendar
            </button>
            <button class="tab-btn py-2 px-1 border-b-2 font-medium text-sm" data-tab="history">
                <i data-lucide="history" class="w-4 h-4 inline mr-1"></i>
                Match History
            </button>
        </nav>
    </div>

    <!-- Standings Tab -->
    <div id="standings-tab" class="tab-content">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Pos</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Club</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                MP</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                W</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                D</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                L</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                GF</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                GA</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                GD</th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Pts</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($standings as $index => $team): ?>
                            <tr class="<?php echo $team['is_user'] ? 'bg-blue-50' : ''; ?> hover:bg-gray-50">
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

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap gap-4 text-sm">
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 bg-green-600 rounded"></div>
                <span>Champions League</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 bg-blue-600 rounded"></div>
                <span>Europa League</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 bg-red-600 rounded"></div>
                <span>Relegation</span>
            </div>
        </div>
    </div>

    <!-- Calendar Tab -->
    <div id="calendar-tab" class="tab-content hidden">
        <div class="grid gap-4">
            <?php if (!empty($upcoming_matches)): ?>
                <?php
                $current_gw = null;
                foreach ($upcoming_matches as $match):
                    if ($current_gw !== $match['gameweek']):
                        $current_gw = $match['gameweek'];
                        ?>
                        <div class="bg-white rounded-lg shadow p-4">
                            <h3 class="font-semibold text-lg mb-3">Gameweek <?php echo $match['gameweek']; ?></h3>
                        <?php endif; ?>

                        <div class="flex items-center justify-between p-3 border rounded-lg mb-2 last:mb-0">
                            <div class="flex items-center gap-4">
                                <div class="text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($match['match_date'])); ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-medium <?php echo $match['home_team_id'] == $user_id ? 'text-blue-600' : ''; ?>">
                                        <?php echo htmlspecialchars($match['home_team']); ?>
                                    </span>
                                    <span class="text-gray-400">vs</span>
                                    <span
                                        class="font-medium <?php echo $match['away_team_id'] == $user_id ? 'text-blue-600' : ''; ?>">
                                        <?php echo htmlspecialchars($match['away_team']); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($match['status'] === 'scheduled' && ($match['home_team_id'] == $user_id || $match['away_team_id'] == $user_id)): ?>
                                <?php if ($current_validation['is_valid']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                        <button type="submit" name="simulate_match"
                                            class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                                            title="This will simulate all matches in gameweek <?php echo $match['gameweek']; ?>">
                                            Play Gameweek <?php echo $match['gameweek']; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="flex items-center gap-2">
                                        <button disabled class="bg-gray-400 text-white px-3 py-1 rounded text-sm cursor-not-allowed"
                                            title="Club not eligible - check team requirements">
                                            Club Not Eligible
                                        </button>
                                        <a href="team.php" class="text-blue-600 hover:text-blue-800 text-sm">
                                            Fix Issues
                                        </a>
                                    </div>
                                <?php endif; ?>
                                           <?php elseif ($match['status'] === 'completed'): ?>
                                <div class="text-sm font-medium">
                                    <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Check if this is the last match of the gameweek
                        $next_key = array_search($match, $upcoming_matches) + 1;
                        if (!isset($upcoming_matches[$next_key]) || $upcoming_matches[$next_key]['gameweek'] !== $current_gw):
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <i data-lucide="calendar-x" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Upcoming Matches</h3>
                    <p class="text-gray-600">The season has ended or no matches are scheduled.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Match History Tab -->
    <div id="history-tab" class="tab-content hidden">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (!empty($user_matches)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    GW</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Opponent</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    H/A</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Result</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Score</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($user_matches as $match): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <?php echo date('M j, Y', strtotime($match['match_date'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?php echo $match['gameweek']; ?></td>
                                    <td class="px-4 py-3 text-sm font-medium">
                                        <?php echo htmlspecialchars($match['opponent']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span
                                            class="<?php echo $match['venue'] === 'H' ? 'text-blue-600' : 'text-gray-600'; ?>">
                                            <?php echo $match['venue']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php
                                        echo $match['result'] === 'W' ? 'bg-green-100 text-green-800' :
                                            ($match['result'] === 'D' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>">
                                            <?php echo $match['result']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm font-medium">
                                        <?php echo $match['user_score']; ?> - <?php echo $match['opponent_score']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-8 text-center">
                    <i data-lucide="history" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Match History</h3>
                    <p class="text-gray-600">You haven't played any matches yet this season.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .tab-btn.active {
        border-color: #3b82f6 !important;
        color: #2563eb !important;
    }

    .tab-btn {
        border-color: transparent;
        color: #6b7280;
    }

    .tab-btn:hover {
        color: #2563eb;
    }

    .match-result-card {
        transition: all 0.2s ease;
    }

    .match-result-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .score-display {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: 2px solid #cbd5e1;
    }

    .user-match-highlight {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px solid #3b82f6;
    }
</style>

<script>
    // Tab functionality
    document.addEventListener('DOMContentLoaded', function () {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        // Get active tab from URL or default to standings
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'standings';

        function showTab(tabName) {
            // Hide gameweek results when changing tabs (except to standings)
            const gameweekResults = document.getElementById('gameweekResults');
            if (gameweekResults && tabName !== 'standings') {
                hideGameweekResults();
            }

            // Update buttons
            tabBtns.forEach(btn => {
                if (btn.dataset.tab === tabName) {
                    btn.classList.add('active', 'border-blue-500', 'text-blue-600');
                    btn.classList.remove('border-transparent', 'text-gray-500');
                } else {
                    btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    btn.classList.add('border-transparent', 'text-gray-500');
                }
            });

            // Update content
            tabContents.forEach(content => {
                if (content.id === tabName + '-tab') {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });

            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }

        function hideGameweekResults() {
            const gameweekResults = document.getElementById('gameweekResults');
            if (gameweekResults) {
                gameweekResults.style.display = 'none';
                // Clear session data
                fetch('clear_gameweek_results.php', { method: 'POST' });
            }
        }

        // Initialize active tab
        showTab(activeTab);

        // Tab click handlers
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                showTab(btn.dataset.tab);
            });
        });

        // Close gameweek results handler
        document.getElementById('closeGameweekResults')?.addEventListener('click', function () {
            hideGameweekResults();
        });

        // Simulate gameweek
        document.getElementById('simulateAllBtn')?.addEventListener('click', function () {
            Swal.fire({
                icon: 'question',
                title: 'Simulate Gameweek?',
                text: 'This will simulate all matches in the current gameweek.',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6 ',
                cancelButtonColor: '#6b7280 ',
                confirmButtonText: 'Simulate Gameweek',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'simulate_league.php';
                }
            });
        });
    });

    lucide.createIcons();
</script>

<?php
endContent('League - Dream Team', 'league');
?>