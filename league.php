<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';



require_once 'includes/league_functions.php';

try {
    $db = getDbConnection();

    // Create nation_calls table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS nation_calls (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        called_players TEXT NOT NULL,
        total_reward INTEGER NOT NULL,
        call_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )');

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

    // Get league statistics
    $top_scorers = getTopScorers($db, $current_season, 3);
    $top_assists = getTopAssists($db, $current_season, 3);
    $top_rated = getTopRatedPlayers($db, $current_season, 3);
    $most_yellow_cards = getMostYellowCards($db, $current_season, 3);
    $most_red_cards = getMostRedCards($db, $current_season, 3);
    $top_goalkeepers = getTopGoalkeepers($db, $current_season, 3);

    // Calculate nation call availability
    $matchesPlayed = $user['matches_played'] ?? 0;
    $matchesUntilNext = 8 - ($matchesPlayed % 8);
    if ($matchesUntilNext === 8)
        $matchesUntilNext = 0; // Just had a nation call

    // Check if nation call is available (every 8 matches, but not if already processed)
    $canProcessNationCall = false;
    if ($matchesPlayed > 0 && $matchesPlayed % 8 === 0) {
        // Check if nation call was already processed for this milestone
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM nation_calls WHERE user_id = :user_id AND call_date > datetime("now", "-1 day")');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $recentCalls = $result->fetchArray(SQLITE3_ASSOC);

        // Only allow if no recent nation call in the last 24 hours
        $canProcessNationCall = $recentCalls['count'] == 0;
    }

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
                    <a href="team.php"
                        class="inline-flex items-center gap-2 bg-yellow-600 text-white px-3 py-2 rounded text-sm hover:bg-yellow-700">
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <?php if ($results['user_position']): ?>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-gray-700 flex items-center gap-2">
                                    <i data-lucide="trophy" class="w-4 h-4"></i>
                                    League Position:
                                </span>
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
                            <div class="text-xs text-gray-600">
                                <?php if ($results['user_position'] <= 4): ?>
                                    <span class="text-green-600">Champions League qualification</span>
                                <?php elseif ($results['user_position'] <= 6): ?>
                                    <span class="text-blue-600">Europa League qualification</span>
                                <?php elseif ($results['user_position'] >= 18): ?>
                                    <span class="text-red-600">Relegation zone</span>
                                <?php else: ?>
                                    <span class="text-gray-600">Mid-table position</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($results['budget_earned'] > 0): ?>
                        <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-green-700 flex items-center gap-2">
                                    <i data-lucide="coins" class="w-4 h-4"></i>
                                    Budget Earned:
                                </span>
                                <span class="text-2xl font-bold text-green-600">
                                    +<?php echo formatMarketValue($results['budget_earned']); ?>
                                </span>
                            </div>

                            <!-- Budget Breakdown -->
                            <?php if (isset($results['budget_breakdown'])): ?>
                                <div class="text-xs text-green-700 space-y-1 border-t border-green-200 pt-2">
                                    <div class="font-medium mb-1">Breakdown:</div>
                                    <?php foreach ($results['budget_breakdown'] as $item): ?>
                                        <div class="flex justify-between">
                                            <span><?php echo htmlspecialchars($item['description']); ?></span>
                                            <span class="font-medium">+<?php echo formatMarketValue($item['amount']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-green-600 mt-1">
                                    Added to your club budget
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($results['fan_change_info']) && $results['fan_change_info']): ?>
                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-blue-700 flex items-center gap-2">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                    Fan Base:
                                </span>
                                <span class="text-lg font-bold text-blue-600">
                                    <?php echo number_format($results['fan_change_info']['new_fans']); ?>
                                </span>
                            </div>
                            <div class="text-xs text-blue-700">
                                <?php
                                $fan_change = $results['fan_change_info']['fan_change'];
                                if ($fan_change > 0): ?>
                                    <span class="text-green-600">+<?php echo number_format($fan_change); ?> new fans</span>
                                <?php elseif ($fan_change < 0): ?>
                                    <span class="text-red-600"><?php echo number_format($fan_change); ?> fans lost</span>
                                <?php else: ?>
                                    <span class="text-gray-600">No change in fan base</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Post-Match Player Selection Info -->
                <?php if ($results['user_match']): ?>
                    <div class="mb-4 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="gift" class="w-4 h-4 text-purple-600"></i>
                            <span class="font-medium text-purple-800">Post-Match Reward Available</span>
                        </div>
                        <p class="text-sm text-purple-700">
                            You can now choose 1 of 3 mystery boxes to reveal a random player reward!
                            <span class="font-medium">Check above for the mystery box selection.</span>
                        </p>
                    </div>
                <?php endif; ?>

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

    <!-- Post-Match Player Selection Notification -->
    <div id="postMatchNotification"
        class="mb-6 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg shadow-lg border border-purple-300 overflow-hidden"
        style="display: none;">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i data-lucide="gift" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Post-Match Reward Available!</h3>
                        <p class="text-purple-100">You can select 1 of 3 players to add to your squad</p>
                    </div>
                </div>
                <button id="openPostMatchModal"
                    class="bg-white text-purple-600 px-4 py-2 rounded-lg hover:bg-purple-50 font-medium transition-colors">
                    Choose Player
                </button>
            </div>
        </div>
    </div>

    <!-- Nation Call Notification -->
    <?php if (isset($_SESSION['nation_call_notification'])): ?>
        <?php $nationCall = $_SESSION['nation_call_notification']; ?>
        <div
            class="mb-6 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg shadow-lg border border-blue-300 overflow-hidden">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i data-lucide="flag" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold">Nation Call Success!</h3>
                            <p class="text-blue-100">
                                <?php echo count($nationCall['called_players']); ?>
                                player<?php echo count($nationCall['called_players']) > 1 ? 's' : ''; ?> called up for
                                international duty
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold">+<?php echo formatMarketValue($nationCall['total_reward']); ?></div>
                        <div class="text-blue-100 text-sm">Earnings</div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-blue-400 border-opacity-30">
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($nationCall['called_players'] as $player): ?>
                            <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1 text-sm">
                                <span class="font-medium"><?php echo htmlspecialchars($player['name']); ?></span>
                                <span class="text-blue-200">(<?php echo $player['position']; ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <a href="nation_calls.php"
                        class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 font-medium transition-colors">
                        View Details
                    </a>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['nation_call_notification']); ?>
    <?php endif; ?>

    <!-- Nation Call Processing Box -->

    <?php if ($canProcessNationCall): ?>
        <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                        <i data-lucide="flag" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900">Nation Call Available!</h3>
                        <p class="text-blue-700">
                            You've played <?php echo $matchesPlayed; ?> matches. Your best players may be called up for
                            international duty.
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="nation_calls.php"
                        class="bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 font-medium transition-colors">
                        View History
                    </a>
                    <button id="processNationCallBtn"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition-colors flex items-center gap-2">
                        <i data-lucide="play" class="w-4 h-4"></i>
                        Process Nation Call
                    </button>
                </div>
            </div>
        </div>
    <?php elseif ($matchesPlayed > 0): ?>
        <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gray-400 rounded-full flex items-center justify-center">
                        <i data-lucide="flag" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700">Next Nation Call</h3>
                        <p class="text-gray-600">
                            <?php echo $matchesUntilNext; ?> more match<?php echo $matchesUntilNext > 1 ? 'es' : ''; ?>
                            until next nation call opportunity
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-600"><?php echo $matchesUntilNext; ?></div>
                    <div class="text-sm text-gray-500">matches left</div>
                </div>
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
            <button class="tab-btn py-2 px-1 border-b-2 font-medium text-sm" data-tab="stats">
                <i data-lucide="award" class="w-4 h-4 inline mr-1"></i>
                Stats
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

    <!-- Stats Tab -->
    <div id="stats-tab" class="tab-content hidden">
        <div class="grid gap-6">
            <!-- Top Scorers -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4">
                    <div class="flex items-center gap-3">
                        <i data-lucide="target" class="w-6 h-6"></i>
                        <h3 class="text-lg font-bold">Top Scorers</h3>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (!empty($top_scorers)): ?>
                        <div class="space-y-3">
                            <?php foreach ($top_scorers as $index => $scorer): ?>
                                <?php $isUserPlayer = ($scorer['user_id'] == $user_id); ?>
                                <div
                                    class="flex items-center justify-between p-3 rounded-lg <?php echo $isUserPlayer ? 'bg-blue-50 border-2 border-blue-200' : 'bg-gray-50'; ?>">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 <?php echo $isUserPlayer ? 'bg-blue-600' : 'bg-green-600'; ?> rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium flex items-center gap-2">
                                                <?php echo htmlspecialchars($scorer['player_name']); ?>
                                                <?php if ($isUserPlayer): ?>
                                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs rounded-full">YOUR
                                                        PLAYER</span>
                                                <?php endif; ?>
                                            </div>
                                            <div
                                                class="text-sm <?php echo $isUserPlayer ? 'text-blue-700' : 'text-gray-600'; ?>">
                                                <?php echo htmlspecialchars($scorer['club_name']); ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div
                                            class="text-2xl font-bold <?php echo $isUserPlayer ? 'text-blue-600' : 'text-green-600'; ?>">
                                            <?php echo $scorer['goals']; ?></div>
                                        <div class="text-xs text-gray-500">goals</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i data-lucide="target" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                            <p class="text-gray-600">No goals scored yet this season</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Assists -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4">
                    <div class="flex items-center gap-3">
                        <i data-lucide="users" class="w-6 h-6"></i>
                        <h3 class="text-lg font-bold">Top Assists</h3>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (!empty($top_assists)): ?>
                        <div class="space-y-3">
                            <?php foreach ($top_assists as $index => $assister): ?>
                                <?php $isUserPlayer = ($assister['user_id'] == $user_id); ?>
                                <div class="flex items-center justify-between p-3 rounded-lg <?php echo $isUserPlayer ? 'bg-blue-50 border-2 border-blue-200' : 'bg-gray-50'; ?>">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 <?php echo $isUserPlayer ? 'bg-blue-600' : 'bg-purple-600'; ?> rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium flex items-center gap-2">
                                                <?php echo htmlspecialchars($assister['player_name']); ?>
                                                <?php if ($isUserPlayer): ?>
                                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs rounded-full">YOUR PLAYER</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm <?php echo $isUserPlayer ? 'text-blue-700' : 'text-gray-600'; ?>">
                                                <?php echo htmlspecialchars($assister['club_name']); ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold <?php echo $isUserPlayer ? 'text-blue-600' : 'text-purple-600'; ?>"><?php echo $assister['assists']; ?>
                                        </div>
                                        <div class="text-xs text-gray-500">assists</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i data-lucide="users" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                            <p class="text-gray-600">No assists recorded yet this season</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Rated Players -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4">
                    <div class="flex items-center gap-3">
                        <i data-lucide="star" class="w-6 h-6"></i>
                        <h3 class="text-lg font-bold">Top Rated Players</h3>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (!empty($top_rated)): ?>
                        <div class="space-y-3">
                            <?php foreach ($top_rated as $index => $player): ?>
                                <?php $isUserPlayer = ($player['user_id'] == $user_id); ?>
                                <div class="flex items-center justify-between p-3 rounded-lg <?php echo $isUserPlayer ? 'bg-blue-50 border-2 border-blue-200' : 'bg-gray-50'; ?>">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 <?php echo $isUserPlayer ? 'bg-blue-600' : 'bg-blue-600'; ?> rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium flex items-center gap-2">
                                                <?php echo htmlspecialchars($player['player_name']); ?>
                                                <?php if ($isUserPlayer): ?>
                                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs rounded-full">YOUR PLAYER</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm <?php echo $isUserPlayer ? 'text-blue-700' : 'text-gray-600'; ?>">
                                                <?php echo htmlspecialchars($player['club_name']); ?> •
                                                <?php echo htmlspecialchars($player['position']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo number_format($player['avg_rating'], 1); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $player['matches_played']; ?> matches
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i data-lucide="star" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                            <p class="text-gray-600">No player ratings available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Most Disciplined/Undisciplined -->
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Most Yellow Cards -->
                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white p-4">
                        <div class="flex items-center gap-3">
                            <i data-lucide="square" class="w-6 h-6"></i>
                            <h3 class="text-lg font-bold">Most Yellow Cards</h3>
                        </div>
                    </div>
                    <div class="p-4">
                        <?php if (!empty($most_yellow_cards)): ?>
                            <div class="space-y-3">
                                <?php foreach ($most_yellow_cards as $index => $player): ?>
                                    <?php $isUserPlayer = ($player['user_id'] == $user_id); ?>
                                    <div class="flex items-center justify-between p-2 rounded <?php echo $isUserPlayer ? 'bg-blue-50 border-2 border-blue-200' : 'bg-gray-50'; ?>">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-6 h-6 <?php echo $isUserPlayer ? 'bg-blue-600' : 'bg-yellow-600'; ?> rounded-full flex items-center justify-center text-white font-bold text-xs">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm flex items-center gap-2">
                                                    <?php echo htmlspecialchars($player['player_name']); ?>
                                                    <?php if ($isUserPlayer): ?>
                                                        <span class="px-1 py-0.5 bg-blue-600 text-white text-xs rounded">YOUR PLAYER</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs <?php echo $isUserPlayer ? 'text-blue-700' : 'text-gray-600'; ?>">
                                                    <?php echo htmlspecialchars($player['club_name']); ?></div>
                                            </div>
                                        </div>
                                        <div class="<?php echo $isUserPlayer ? 'text-blue-600' : 'text-yellow-600'; ?> font-bold"><?php echo $player['yellow_cards']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-gray-600 text-sm">No yellow cards yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Most Red Cards -->
                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-4">
                        <div class="flex items-center gap-3">
                            <i data-lucide="square" class="w-6 h-6"></i>
                            <h3 class="text-lg font-bold">Most Red Cards</h3>
                        </div>
                    </div>
                    <div class="p-4">
                        <?php if (!empty($most_red_cards)): ?>
                            <div class="space-y-3">
                                <?php foreach ($most_red_cards as $index => $player): ?>
                                    <?php $isUserPlayer = ($player['user_id'] == $user_id); ?>
                                    <div class="flex items-center justify-between p-2 rounded <?php echo $isUserPlayer ? 'bg-blue-50 border-2 border-blue-200' : 'bg-gray-50'; ?>">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-6 h-6 <?php echo $isUserPlayer ? 'bg-blue-600' : 'bg-red-600'; ?> rounded-full flex items-center justify-center text-white font-bold text-xs">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm flex items-center gap-2">
                                                    <?php echo htmlspecialchars($player['player_name']); ?>
                                                    <?php if ($isUserPlayer): ?>
                                                        <span class="px-1 py-0.5 bg-blue-600 text-white text-xs rounded">YOUR PLAYER</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs <?php echo $isUserPlayer ? 'text-blue-700' : 'text-gray-600'; ?>">
                                                    <?php echo htmlspecialchars($player['club_name']); ?></div>
                                            </div>
                                        </div>
                                        <div class="<?php echo $isUserPlayer ? 'text-blue-600' : 'text-red-600'; ?> font-bold"><?php echo $player['red_cards']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-gray-600 text-sm">No red cards yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Goalkeeper Stats -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-cyan-500 to-cyan-600 text-white p-4">
                    <div class="flex items-center gap-3">
                        <i data-lucide="shield" class="w-6 h-6"></i>
                        <h3 class="text-lg font-bold">Top Goalkeepers</h3>
                    </div>
                </div>
                <div class="p-4">
                    <?php if (!empty($top_goalkeepers)): ?>
                        <div class="space-y-3">
                            <?php foreach ($top_goalkeepers as $index => $gk): ?>
                                <?php $isUserPlayer = ($gk['user_id'] == $user_id); ?>
                                <div class="flex items-center justify-between p-3 rounded-lg <?php echo $isUserPlayer ? 'bg-blue-50 border-2 border-blue-200' : 'bg-gray-50'; ?>">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 <?php echo $isUserPlayer ? 'bg-blue-600' : 'bg-cyan-600'; ?> rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium flex items-center gap-2">
                                                <?php echo htmlspecialchars($gk['player_name']); ?>
                                                <?php if ($isUserPlayer): ?>
                                                    <span class="px-2 py-1 bg-blue-600 text-white text-xs rounded-full">YOUR PLAYER</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm <?php echo $isUserPlayer ? 'text-blue-700' : 'text-gray-600'; ?>"><?php echo htmlspecialchars($gk['club_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="flex gap-4">
                                            <div class="text-center">
                                                <div class="text-lg font-bold <?php echo $isUserPlayer ? 'text-blue-600' : 'text-cyan-600'; ?>"><?php echo $gk['clean_sheets']; ?>
                                                </div>
                                                <div class="text-xs text-gray-500">clean sheets</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-blue-600"><?php echo $gk['saves']; ?></div>
                                                <div class="text-xs text-gray-500">saves</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i data-lucide="shield" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                            <p class="text-gray-600">No goalkeeper stats available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Legend -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center gap-3 mb-2">
                <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                <h4 class="font-semibold text-blue-900">Stats Legend</h4>
            </div>
            <div class="text-sm text-blue-800">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-4 h-4 bg-blue-50 border-2 border-blue-200 rounded"></div>
                    <span>Players highlighted in blue belong to your club</span>
                </div>
                <p class="text-blue-700">Statistics are updated after each match and show league-wide performance across all teams.</p>
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
                                <?php if ($match['gameweek'] == $current_gameweek): ?>
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
                                <?php else: ?>
                                    <div class="text-sm text-gray-500">
                                        Gameweek <?php echo $match['gameweek']; ?>
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

<link rel="stylesheet" href="assets/css/league.css">

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

                // Clear gameweek completed state from URL
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('gameweek_completed') === '1') {
                    urlParams.delete('gameweek_completed');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    window.history.replaceState({}, '', newUrl);
                }

                // Clear session data and post-match rewards
                fetch('api/clear_gameweek_results_api.php', { method: 'POST' });
            }
        }

        // Initialize active tab
        showTab(activeTab);

        // Tab click handlers
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                showTab(btn.dataset.tab);

                // Clear gameweek completed state and post-match rewards when switching tabs
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('gameweek_completed') === '1') {
                    urlParams.delete('gameweek_completed');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    window.history.replaceState({}, '', newUrl);

                    // Clear post-match rewards
                    fetch('api/clear_gameweek_results_api.php', { method: 'POST' })
                        .catch(error => console.log('Error clearing post-match rewards:', error));
                }
            });
        });

        // Close gameweek results handler
        document.getElementById('closeGameweekResults')?.addEventListener('click', function () {
            hideGameweekResults();
        });

        // Clear post-match rewards when user navigates away from gameweek completed state
        function clearPostMatchRewardsIfIgnored() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('gameweek_completed') !== '1') {
                // User navigated away from gameweek completed state, clear rewards
                fetch('api/clear_gameweek_results_api.php', { method: 'POST' })
                    .catch(error => console.log('Error clearing post-match rewards:', error));
            }
        }

        // Listen for URL changes (back/forward navigation)
        window.addEventListener('popstate', clearPostMatchRewardsIfIgnored);

        // Listen for page unload (user navigating to different page)
        window.addEventListener('beforeunload', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('gameweek_completed') === '1') {
                // User is leaving the gameweek completed page, clear rewards
                navigator.sendBeacon('api/clear_gameweek_results_api.php');
            }
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

        // Process nation call
        document.getElementById('processNationCallBtn')?.addEventListener('click', function () {
            Swal.fire({
                icon: 'question',
                title: 'Process Nation Call?',
                text: 'This will evaluate your best players for international duty and award budget rewards.',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Process Nation Call',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Processing Nation Call...',
                        text: 'Evaluating players for international duty',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Process nation call
                    fetch('api/process_nation_call_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success with details
                                let playersHtml = '';
                                if (data.called_players && data.called_players.length > 0) {
                                    playersHtml = '<div class="mt-4 p-3 bg-blue-50 rounded-lg"><h4 class="font-semibold text-blue-900 mb-2">Called Players:</h4>';
                                    data.called_players.forEach(player => {
                                        playersHtml += `<div class="flex justify-between items-center py-1">
                                        <span class="font-medium">${player.name} (${player.position})</span>
                                        <span class="text-green-600">+€${(player.reward || 50000).toLocaleString()}</span>
                                    </div>`;
                                    });
                                    playersHtml += '</div>';
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Nation Call Processed!',
                                    html: `
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600 mb-2">
                                            +€${data.total_reward.toLocaleString()}
                                        </div>
                                        <p class="text-gray-600 mb-2">
                                            ${data.called_players.length} player${data.called_players.length > 1 ? 's' : ''} called up for international duty
                                        </p>
                                        ${playersHtml}
                                    </div>
                                `,
                                    confirmButtonColor: '#3b82f6',
                                    confirmButtonText: 'Great!'
                                }).then(() => {
                                    // Reload page to update the display
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'No Nation Call',
                                    text: data.message || 'No players were selected for international duty at this time.',
                                    confirmButtonColor: '#3b82f6'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to process nation call. Please try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        });
                }
            });
        });
    });

    // Check for post-match player selection on page load only if gameweek was completed
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('gameweek_completed') === '1') {
        checkPostMatchPlayers();
    }

    function checkPostMatchPlayers() {
        fetch('api/get_post_match_players_api.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.players) {
                    showPostMatchNotification(data.players, data.time_remaining);
                }
            })
            .catch(error => {
                console.log('No post-match players available');
            });
    }

    function showPostMatchNotification(players, timeRemaining) {
        // Only show notification if gameweek was completed
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('gameweek_completed') !== '1') {
            return;
        }

        const notification = document.getElementById('postMatchNotification');
        if (notification) {
            notification.style.display = 'block';

            // Store data for modal
            notification.playersData = players;
            notification.timeRemaining = timeRemaining;

            // Add click handler for opening modal
            document.getElementById('openPostMatchModal').addEventListener('click', () => {
                showPostMatchPlayerModal(players, timeRemaining);
                notification.style.display = 'none';
            });
        }
    }

    function showPostMatchPlayerModal(players, timeRemaining) {
        const modalHtml = `
            <div id="postMatchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <i data-lucide="gift" class="w-6 h-6"></i>
                                <div>
                                    <h3 class="text-xl font-bold">Post-Match Reward</h3>
                                    <p class="text-green-100">Choose 1 of 3 mystery boxes to reveal your reward</p>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6" id="player-option-box">
                            ${players.map((player, index) => `
                                <div class="player-option mystery-box border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 transition-all duration-300 transform hover:scale-105" data-index="${index}">
                                    <!-- Mystery Box State -->
                                    <div class="mystery-content text-center">
                                        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-3 mystery-icon relative">
                                            <i data-lucide="gift" class="w-8 h-8 text-white"></i>
                                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-yellow-400 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold text-yellow-800">?</span>
                                            </div>
                                        </div>
                                        <h4 class="font-bold text-lg mb-1 text-gray-700">Mystery Player</h4>
                                        <div class="text-sm text-gray-500 mb-2">Click to reveal</div>
                                        <div class="text-xs text-purple-600 font-medium">Option ${index + 1}</div>
                                    </div>
                                    
                                    <!-- Revealed Player State (hidden initially) -->
                                    <div class="revealed-content text-center hidden">
                                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 player-rating">
                                            <span class="text-white font-bold text-lg">${player.rating}</span>
                                        </div>
                                        <h4 class="font-bold text-lg mb-1 player-name">${player.name}</h4>
                                        <div class="flex items-center justify-center gap-2 mb-2">
                                            <span class="px-2 py-1 bg-gray-100 rounded text-sm font-medium player-position">${player.position}</span>
                                            <span class="px-2 py-1 bg-${player.category === 'young' ? 'green' : 'gray'}-100 text-${player.category === 'young' ? 'green' : 'gray'}-800 rounded text-sm font-medium capitalize player-category">${player.category}</span>
                                        </div>
                                        <div class="text-sm text-gray-600 mb-2 player-age">Age: ${player.age}</div>
                                        <div class="text-sm font-medium text-blue-600 player-value">${formatMarketValue(player.value)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="flex justify-between items-center">

                            <div class="flex gap-3">
                                <button id="skipSelection" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                    Skip for now
                                </button>
                                <button id="confirmSelection" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                                    Select a Mystery Box
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Add event listeners
        let selectedIndex = null;
        let isRevealing = false;

        document.querySelectorAll('.player-option').forEach((option, index) => {
            option.addEventListener('click', () => {
                if (isRevealing) return; // Prevent multiple clicks during animation

                selectedIndex = index;
                isRevealing = true;

                // Disable all options during reveal
                document.querySelectorAll('.player-option').forEach(opt => {
                    opt.style.pointerEvents = 'none';
                });

                // Update button text
                const confirmBtn = document.getElementById('confirmSelection');
                confirmBtn.textContent = 'Revealing...';
                confirmBtn.disabled = true;

                // Start reveal animation sequence
                revealPlayerSequence(players, selectedIndex);
            });
        });

        document.getElementById('confirmSelection').addEventListener('click', () => {
            const confirmBtn = document.getElementById('confirmSelection');

            // If button text is "Close", just close the modal
            if (confirmBtn.textContent === 'Close') {
                closePostMatchModal();
                return;
            }

            // Otherwise, this shouldn't happen since we auto-save now
            if (selectedIndex !== null && !isRevealing) {
                closePostMatchModal();
            }
        });

        document.getElementById('skipSelection').addEventListener('click', () => {
            if (isRevealing) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reveal in Progress',
                    text: 'Please wait for the reveal to complete before skipping.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            closePostMatchModal();
        });

        // Close modal when clicking outside
        document.getElementById('postMatchModal').addEventListener('click', (e) => {
            if (e.target.id === 'postMatchModal') {
                closePostMatchModal();
            }
        });

        // Update countdown timer
        const timer = setInterval(() => {
            timeRemaining--;
            if (timeRemaining <= 0) {
                clearInterval(timer);
                closePostMatchModal();
                Swal.fire({
                    icon: 'warning',
                    title: 'Selection Expired',
                    text: 'The post-match player selection has expired.',
                    confirmButtonColor: '#3b82f6'
                });
            }
        }, 1000);

        // Store timer reference for cleanup
        document.getElementById('postMatchModal').timer = timer;

        lucide.createIcons();
    }

    function revealPlayerSequence(players, selectedIndex) {
        const options = document.querySelectorAll('.player-option');

        // First, add shake animation to selected box
        const selectedOption = options[selectedIndex];
        selectedOption.classList.add('animate-pulse');

        // Create reveal sequence with delays
        const revealOrder = [selectedIndex]; // Selected first
        const otherIndices = [0, 1, 2].filter(i => i !== selectedIndex);
        revealOrder.push(...otherIndices);

        let revealCount = 0;

        revealOrder.forEach((index, sequenceIndex) => {
            setTimeout(() => {
                const option = options[index];
                const mysteryContent = option.querySelector('.mystery-content');
                const revealedContent = option.querySelector('.revealed-content');

                // Add dramatic reveal animation
                option.classList.add('transform', 'scale-110');
                option.style.background = 'linear-gradient(135deg, #fbbf24, #f59e0b)';
                option.style.borderColor = '#f59e0b';
                option.style.boxShadow = '0 0 30px rgba(251, 191, 36, 0.6)';

                setTimeout(() => {
                    // Flip animation
                    mysteryContent.style.transform = 'rotateY(90deg)';
                    mysteryContent.style.opacity = '0';

                    setTimeout(() => {
                        mysteryContent.classList.add('hidden');
                        revealedContent.classList.remove('hidden');
                        revealedContent.style.transform = 'rotateY(-90deg)';
                        revealedContent.style.opacity = '0';

                        setTimeout(() => {
                            revealedContent.style.transform = 'rotateY(0deg)';
                            revealedContent.style.opacity = '1';
                            revealedContent.style.transition = 'all 0.5s ease';

                            // Reset option styling
                            setTimeout(() => {
                                option.classList.remove('transform', 'scale-110');
                                option.style.background = '';

                                if (index === selectedIndex) {
                                    option.style.borderColor = '#3b82f6';
                                    option.style.backgroundColor = '#dbeafe';
                                    option.classList.add('selected-player');
                                } else {
                                    option.style.borderColor = '#d1d5db';
                                    option.style.backgroundColor = '#f9fafb';
                                    option.style.opacity = '0.7';
                                }

                                revealCount++;

                                // When all are revealed, automatically save the selected player
                                if (revealCount === 3) {
                                    setTimeout(() => {
                                        // Automatically save the selected player
                                        saveSelectedPlayer(selectedIndex, players[selectedIndex]);
                                    }, 500);
                                }
                            }, 300);
                        }, 100);
                    }, 200);
                }, 300);
            }, sequenceIndex * 800); // 800ms delay between each reveal
        });
    }

    function saveSelectedPlayer(selectedIndex, selectedPlayer) {
        // Show saving message
        const confirmBtn = document.getElementById('confirmSelection');
        confirmBtn.textContent = 'Saving Player...';
        confirmBtn.disabled = true;

        // Show selection message
        const messageDiv = document.createElement('div');
        messageDiv.className = 'mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-center animate-fade-in';
        messageDiv.innerHTML = `
            <div class="flex items-center justify-center gap-2 mb-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i>
                <span class="font-medium text-blue-800">Adding ${selectedPlayer.name} to your club...</span>
            </div>
            <div class="flex items-center justify-center gap-2">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                <span class="text-sm text-blue-700">Please wait</span>
            </div>
        `;

        const container = document.querySelector('#postMatchModal .grid');
        container.parentNode.insertBefore(messageDiv, container.nextSibling);
        lucide.createIcons();

        // Make API call to save player
        fetch('api/select_post_match_player_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                selected_index: selectedIndex
            })
        })
            .then(response => response.json())
            .then(data => {
                // Remove loading message
                messageDiv.remove();

                if (data.success) {
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-center animate-fade-in';
                    successDiv.innerHTML = `
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <span class="font-medium text-green-800">${selectedPlayer.name} added to your club!</span>
                    </div>
                    <p class="text-sm text-green-700">The player has been added to your inventory</p>
                `;

                    const container = document.querySelector('#postMatchModal .grid');
                    container.parentNode.insertBefore(successDiv, container.nextSibling);

                    // Update button to close modal
                    confirmBtn.textContent = 'Close';
                    confirmBtn.disabled = false;
                    confirmBtn.classList.remove('animate-pulse');
                    confirmBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    confirmBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');

                    // Store success info for notification after modal closes
                    confirmBtn.dataset.playerAdded = 'true';
                    confirmBtn.dataset.playerName = selectedPlayer.name;

                    lucide.createIcons();
                } else {
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-center animate-fade-in';
                    errorDiv.innerHTML = `
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                        <span class="font-medium text-red-800">Failed to add player</span>
                    </div>
                    <p class="text-sm text-red-700">${data.message || 'An error occurred'}</p>
                `;

                    const container = document.querySelector('#postMatchModal .grid');
                    container.parentNode.insertBefore(errorDiv, container.nextSibling);

                    // Update button to close modal
                    confirmBtn.textContent = 'Close';
                    confirmBtn.disabled = false;

                    lucide.createIcons();
                }
            })
            .catch(error => {
                // Remove loading message
                messageDiv.remove();

                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-center animate-fade-in';
                errorDiv.innerHTML = `
                <div class="flex items-center justify-center gap-2 mb-2">
                    <i data-lucide="wifi-off" class="w-5 h-5 text-red-600"></i>
                    <span class="font-medium text-red-800">Connection Error</span>
                </div>
                <p class="text-sm text-red-700">Please check your connection and try again</p>
            `;

                const container = document.querySelector('#postMatchModal .grid');
                container.parentNode.insertBefore(errorDiv, container.nextSibling);

                // Update button to close modal
                confirmBtn.textContent = 'Close';
                confirmBtn.disabled = false;

                lucide.createIcons();
            });
    }



    function closePostMatchModal() {
        const modal = document.getElementById('postMatchModal');
        if (modal) {
            // Check if we need to show success notification
            const confirmBtn = document.getElementById('confirmSelection');
            const playerAdded = confirmBtn && confirmBtn.dataset.playerAdded === 'true';
            const playerName = confirmBtn && confirmBtn.dataset.playerName;

            if (modal.timer) {
                clearInterval(modal.timer);
            }
            modal.remove();

            // Show success notification after modal is closed
            if (playerAdded && playerName) {
                setTimeout(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Player Added!',
                        html: `<strong>${playerName}</strong> has been added to your club!<br><small>Check your transfer page to manage your players.</small>`,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Great!',
                        timer: 4000,
                        timerProgressBar: true
                    });
                }, 300);
            }
        }
    }

    function formatTimeRemaining(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        } else {
            return `${secs}s`;
        }
    }

    // Helper function for market value formatting (if not already available)
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + (value / 1000).toFixed(0) + ' K ';
        } else {
            return '€' + value;
        }
    }

    lucide.createIcons();
</script>

<?php
endContent('League - Dream Team', 'league', true, false, true);
?>