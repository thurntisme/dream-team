<?php
session_start();

require_once 'config.php';
require_once 'constants.php';
require_once 'layout.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['club_name'])) {
    header('Location: index.php');
    exit;
}

// Get opponent ID from URL
$opponent_id = $_GET['opponent'] ?? null;
if (!$opponent_id) {
    header('Location: clubs.php');
    exit;
}

try {
    $db = getDbConnection();

    // Get user's team data
    $stmt = $db->prepare('SELECT name, club_name, formation, team, budget FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Get opponent's team data
    $stmt = $db->prepare('SELECT name, club_name, formation, team, budget FROM users WHERE id = :opponent_id AND id != :user_id');
    $stmt->bindValue(':opponent_id', $opponent_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $opponent_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data || !$opponent_data) {
        header('Location: clubs.php');
        exit;
    }

    $db->close();

    // Calculate team values
    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $opponent_team = json_decode($opponent_data['team'] ?? '[]', true);

    $user_team_value = calculateTeamValue($user_team);
    $opponent_team_value = calculateTeamValue($opponent_team);

    // Simulate the match
    $match_result = simulateMatch($user_team, $opponent_team, $user_team_value, $opponent_team_value);

} catch (Exception $e) {
    header('Location: clubs.php');
    exit;
}

// Helper function to calculate team value
function calculateTeamValue($team)
{
    if (!is_array($team))
        return 0;

    $totalValue = 0;
    foreach ($team as $player) {
        if ($player && isset($player['value'])) {
            $totalValue += $player['value'];
        }
    }
    return $totalValue;
}

// Get random player names for events
function getRandomPlayer($team, $fallback = 'Player')
{
    if (!is_array($team))
        return $fallback;

    $players = array_filter($team, fn($p) => $p !== null && isset($p['name']));
    if (empty($players))
        return $fallback;

    return $players[array_rand($players)]['name'];
}

// Simulate match between two teams
function simulateMatch($user_team, $opponent_team, $user_team_value, $opponent_team_value)
{
    // Calculate team strengths based on multiple factors
    $user_strength = calculateTeamStrength($user_team, $user_team_value);
    $opponent_strength = calculateTeamStrength($opponent_team, $opponent_team_value);

    // Add some randomness to make matches more interesting
    $user_performance = $user_strength * (0.7 + (mt_rand(0, 60) / 100)); // 70-130% of base strength
    $opponent_performance = $opponent_strength * (0.7 + (mt_rand(0, 60) / 100));

    // Calculate goal probabilities based on performance difference
    $total_performance = $user_performance + $opponent_performance;
    $user_goal_probability = $user_performance / $total_performance;

    // Simulate goals (0-5 goals per team, weighted by strength)
    $total_goals = mt_rand(0, 6); // Total goals in match
    $user_goals = 0;
    $opponent_goals = 0;

    for ($i = 0; $i < $total_goals; $i++) {
        if (mt_rand(1, 100) / 100 <= $user_goal_probability) {
            $user_goals++;
        } else {
            $opponent_goals++;
        }
    }

    // Determine result
    if ($user_goals > $opponent_goals) {
        $result = 'win';
    } elseif ($user_goals < $opponent_goals) {
        $result = 'loss';
    } else {
        $result = 'draw';
    }

    // Generate match events
    $events = generateMatchEvents($user_team, $opponent_team, $user_goals, $opponent_goals);

    return [
        'userGoals' => $user_goals,
        'opponentGoals' => $opponent_goals,
        'result' => $result,
        'userStrength' => round($user_strength, 1),
        'opponentStrength' => round($opponent_strength, 1),
        'events' => $events
    ];
}

// Calculate team strength based on players and formation
function calculateTeamStrength($team, $team_value)
{
    if (!is_array($team))
        return 50;

    $player_count = count(array_filter($team, fn($p) => $p !== null));

    // Base strength from team value (normalized to 0-100 scale)
    $value_strength = min(100, ($team_value / 1000000000) * 100); // €1B = 100 strength

    // Player count bonus/penalty
    $count_modifier = 1.0;
    if ($player_count < 11) {
        $count_modifier = 0.7 + ($player_count / 11) * 0.3; // Penalty for incomplete team
    }

    // Calculate average player rating
    $total_rating = 0;
    $rated_players = 0;
    foreach ($team as $player) {
        if ($player && isset($player['rating']) && $player['rating'] > 0) {
            $total_rating += $player['rating'];
            $rated_players++;
        }
    }

    $avg_rating = $rated_players > 0 ? $total_rating / $rated_players : 75;
    $rating_strength = ($avg_rating - 50) * 2; // Convert 50-100 rating to 0-100 strength

    // Combine factors
    $final_strength = (($value_strength * 0.6) + ($rating_strength * 0.4)) * $count_modifier;

    return max(10, min(100, $final_strength)); // Clamp between 10-100
}

// Generate match events for display
function generateMatchEvents($user_team, $opponent_team, $user_goals, $opponent_goals)
{
    $events = [];
    $total_goals = $user_goals + $opponent_goals;

    // Generate goal events
    for ($i = 0; $i < $user_goals; $i++) {
        $scorer = getRandomPlayer($user_team, 'Player');
        $minute = mt_rand(1, 90);
        $events[] = [
            'minute' => $minute,
            'type' => 'goal',
            'team' => 'user',
            'player' => $scorer,
            'description' => "GOAL! {$scorer} finds the net!"
        ];
    }

    for ($i = 0; $i < $opponent_goals; $i++) {
        $scorer = getRandomPlayer($opponent_team, 'Player');
        $minute = mt_rand(1, 90);
        $events[] = [
            'minute' => $minute,
            'type' => 'goal',
            'team' => 'opponent',
            'player' => $scorer,
            'description' => "Goal for the opposition. {$scorer} scores."
        ];
    }

    // Add some random events
    if (mt_rand(1, 3) === 1) { // 33% chance
        $player = getRandomPlayer(mt_rand(0, 1) ? $user_team : $opponent_team, 'Player');
        $events[] = [
            'minute' => mt_rand(1, 90),
            'type' => 'yellow_card',
            'player' => $player,
            'description' => "Yellow card for {$player}"
        ];
    }

    if (mt_rand(1, 10) === 1) { // 10% chance
        $player = getRandomPlayer(mt_rand(0, 1) ? $user_team : $opponent_team, 'Player');
        $events[] = [
            'minute' => mt_rand(1, 90),
            'type' => 'red_card',
            'player' => $player,
            'description' => "Red card! {$player} is sent off!"
        ];
    }

    // Sort events by minute
    usort($events, fn($a, $b) => $a['minute'] - $b['minute']);

    return $events;
}

// Start content capture
startContent();
?>

<div class="p-4">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Live Match Simulation</h1>
            <p class="text-gray-600">Friendly Match - Interactive Experience</p>

            <!-- Match Timer -->
            <div class="mt-4 flex justify-center items-center gap-4">
                <div id="matchTimer"
                    class="inline-flex items-center gap-2 bg-red-600 text-white px-6 py-3 rounded-full text-lg font-bold">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                    <span id="timerDisplay">0'</span>
                </div>
                <button id="startMatch"
                    class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                    <i data-lucide="play" class="w-5 h-5"></i>
                    Start Match
                </button>
                <button id="pauseMatch"
                    class="hidden inline-flex items-center gap-2 bg-yellow-600 text-white px-6 py-3 rounded-lg hover:bg-yellow-700 transition-colors">
                    <i data-lucide="pause" class="w-5 h-5"></i>
                    Pause
                </button>
            </div>
        </div>

        <!-- Live Score Display -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-8 mb-6 relative overflow-hidden">
            <!-- Background animation -->
            <div class="absolute inset-0 opacity-5">
                <div class="absolute top-4 left-4 animate-pulse">
                    <i data-lucide="zap" class="w-32 h-32 text-gray-900"></i>
                </div>
            </div>

            <div class="relative flex justify-between items-center">
                <!-- User Team -->
                <div class="text-center flex-1">
                    <div
                        class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                        <i data-lucide="shield" class="w-8 h-8 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($user_data['club_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user_data['name']); ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo formatMarketValue($user_team_value); ?></p>
                </div>

                <!-- Live Score -->
                <div class="text-center mx-8">
                    <div class="text-6xl font-bold text-gray-900 mb-2">
                        <span id="userScore">0</span> - <span id="opponentScore">0</span>
                    </div>
                    <div id="matchStatus"
                        class="text-lg font-semibold text-gray-600 flex items-center justify-center gap-2">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                        Match Ready
                    </div>
                </div>

                <!-- Opponent Team -->
                <div class="text-center flex-1">
                    <div
                        class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                        <i data-lucide="shield" class="w-8 h-8 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <?php echo htmlspecialchars($opponent_data['club_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($opponent_data['name']); ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo formatMarketValue($opponent_team_value); ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12">
            <!-- Football Field with Players -->
            <div class="bg-white rounded-lg p-6 mb-6 col-span-7">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map" class="w-5 h-5"></i>
                    Live Field View
                </h3>
                <div class="bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow-lg relative"
                    style="min-height: 600px;">
                    <!-- Field Lines -->
                    <div class="absolute inset-8 border-2 border-white border-opacity-40 rounded overflow-hidden">
                        <!-- Center Line -->
                        <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-white opacity-40"></div>
                        <!-- Center Circle -->
                        <div
                            class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-24 h-24 border-2 border-white border-opacity-40 rounded-full">
                        </div>
                        <div
                            class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-2 h-2 bg-white opacity-40 rounded-full">
                        </div>

                        <!-- Top Penalty Area -->
                        <div
                            class="absolute top-0 left-1/2 transform -translate-x-1/2 w-48 h-20 border-2 border-t-0 border-white border-opacity-40">
                        </div>
                        <div
                            class="absolute top-0 left-1/2 transform -translate-x-1/2 w-24 h-10 border-2 border-t-0 border-white border-opacity-40">
                        </div>

                        <!-- Bottom Penalty Area -->
                        <div
                            class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-48 h-20 border-2 border-b-0 border-white border-opacity-40">
                        </div>
                        <div
                            class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-24 h-10 border-2 border-b-0 border-white border-opacity-40">
                        </div>

                        <!-- Corner Arcs -->
                        <div
                            class="absolute top-0 left-0 w-8 h-8 border-2 border-t-0 border-l-0 border-white border-opacity-40 rounded-br-full">
                        </div>
                        <div
                            class="absolute top-0 right-0 w-8 h-8 border-2 border-t-0 border-r-0 border-white border-opacity-40 rounded-bl-full">
                        </div>
                        <div
                            class="absolute bottom-0 left-0 w-8 h-8 border-2 border-b-0 border-l-0 border-white border-opacity-40 rounded-tr-full">
                        </div>
                        <div
                            class="absolute bottom-0 right-0 w-8 h-8 border-2 border-b-0 border-r-0 border-white border-opacity-40 rounded-tl-full">
                        </div>
                    </div>

                    <!-- Players will be rendered here -->
                    <div id="fieldPlayers" class="relative h-full"></div>
                </div>
            </div>

            <!-- Live Match Events -->
            <div class="bg-white rounded-lg p-6 mb-6 col-span-5">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="radio" class="w-5 h-5"></i>
                    Live Commentary
                </h3>
                <div id="liveEvents" class="space-y-3 max-h-80 overflow-y-auto">
                    <div class="text-center text-gray-500 py-8">
                        <i data-lucide="mic" class="w-8 h-8 mx-auto mb-2"></i>
                        <p>Match commentary will appear here...</p>
                        <p class="text-sm">Click "Start Match" to begin!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Match Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Your Team</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Formation:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($user_data['formation']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Team Value:</span>
                        <span
                            class="font-medium text-green-600"><?php echo formatMarketValue($user_team_value); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Players:</span>
                        <span
                            class="font-medium"><?php echo count(array_filter($user_team, fn($p) => $p !== null)); ?>/11</span>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Opponent</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Formation:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($opponent_data['formation']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Team Value:</span>
                        <span
                            class="font-medium text-green-600"><?php echo formatMarketValue($opponent_team_value); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Players:</span>
                        <span
                            class="font-medium"><?php echo count(array_filter($opponent_team, fn($p) => $p !== null)); ?>/11</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Match Statistics -->
        <div class="bg-white rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="activity" class="w-5 h-5"></i>
                Live Statistics
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div id="liveUserGoals" class="text-2xl font-bold text-blue-600 mb-1">0</div>
                    <div class="text-sm text-gray-600">Goals</div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($user_data['club_name']); ?>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div id="liveOpponentGoals" class="text-2xl font-bold text-red-600 mb-1">0</div>
                    <div class="text-sm text-gray-600">Goals</div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($opponent_data['club_name']); ?>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div id="liveEventsCount" class="text-2xl font-bold text-gray-600 mb-1">0</div>
                    <div class="text-sm text-gray-600">Events</div>
                    <div class="text-xs text-gray-500 mt-1">Total</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div id="matchProgress" class="text-2xl font-bold text-purple-600 mb-1">0%</div>
                    <div class="text-sm text-gray-600">Progress</div>
                    <div class="text-xs text-gray-500 mt-1">Match Time</div>
                </div>
            </div>
        </div>

        <!-- Team Performance -->
        <div class="bg-white rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="trending-up" class="w-5 h-5"></i>
                Team Performance Analysis
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="text-center">
                    <h4 class="font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($user_data['club_name']); ?>
                    </h4>
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-blue-600 h-4 rounded-full transition-all duration-1000"
                            style="width: <?php echo $match_result['userStrength']; ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600">Strength: <?php echo $match_result['userStrength']; ?>%</p>
                </div>
                <div class="text-center">
                    <h4 class="font-medium text-gray-900 mb-2">
                        <?php echo htmlspecialchars($opponent_data['club_name']); ?>
                    </h4>
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-red-600 h-4 rounded-full transition-all duration-1000"
                            style="width: <?php echo $match_result['opponentStrength']; ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600">Strength: <?php echo $match_result['opponentStrength']; ?>%</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-center gap-4">
            <a href="clubs.php"
                class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Clubs
            </a>
            <a href="team.php"
                class="inline-flex items-center gap-2 bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                <i data-lucide="users" class="w-4 h-4"></i>
                My Team
            </a>
            <a href="match-simulator.php?opponent=<?php echo $opponent_id; ?>"
                class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                Play Again
            </a>
        </div>
    </div>
</div>

<script>
    // Match data from PHP
    const matchData = {
        userTeam: <?php echo json_encode($user_team); ?>,
        opponentTeam: <?php echo json_encode($opponent_team); ?>,
        userFormation: '<?php echo $user_data['formation']; ?>',
        opponentFormation: '<?php echo $opponent_data['formation']; ?>',
        matchResult: <?php echo json_encode($match_result); ?>,
        formations: <?php echo json_encode(FORMATIONS); ?>
    };

    let matchTimer = 0;
    let matchInterval = null;
    let isMatchRunning = false;
    let currentEventIndex = 0;

    // Initialize icons
    lucide.createIcons();

    // Render field players
    function renderFieldPlayers() {
        const $field = $('#fieldPlayers');
        $field.empty();

        // Render user team (bottom half)
        renderTeamOnField(matchData.userTeam, matchData.userFormation, 'user', $field);

        // Render opponent team (top half, flipped)
        renderTeamOnField(matchData.opponentTeam, matchData.opponentFormation, 'opponent', $field);
    }

    function renderTeamOnField(team, formation, teamType, $field) {
        const formationData = matchData.formations[formation];
        const positions = formationData.positions;
        const roles = formationData.roles;

        let playerIdx = 0;
        const isOpponent = teamType === 'opponent';

        positions.forEach((line, lineIdx) => {
            line.forEach(xPos => {
                const player = team[playerIdx];
                let yPos;

                if (isOpponent) {
                    // Flip opponent team to top half
                    yPos = ((lineIdx + 1) * (50 / (positions.length + 1)));
                } else {
                    // User team in bottom half
                    yPos = 50 + ((lineIdx + 1) * (50 / (positions.length + 1)));
                }

                const requiredPosition = roles[playerIdx] || 'GK';
                const colors = getPositionColors(requiredPosition);
                const teamColor = isOpponent ? 'bg-red-500' : 'bg-blue-500';

                if (player) {
                    $field.append(`
                        <div class="absolute transition-all duration-200" 
                             style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);">
                            <div class="w-12 h-12 ${teamColor} rounded-full flex flex-col items-center justify-center shadow-lg border-2 border-white">
                                <i data-lucide="user" class="w-4 h-4 text-white"></i>
                                <span class="text-[8px] font-bold text-white">${requiredPosition}</span>
                            </div>
                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 whitespace-nowrap">
                                <div class="text-white text-[10px] font-bold bg-black bg-opacity-70 px-1.5 py-0.5 rounded">
                                    ${player.name}
                                </div>
                            </div>
                        </div>
                    `);
                }
                playerIdx++;
            });
        });
    }

    function getPositionColors(position) {
        const colorMap = {
            'GK': { bg: 'bg-amber-400', border: 'border-amber-500' },
            'CB': { bg: 'bg-emerald-400', border: 'border-emerald-500' },
            'LB': { bg: 'bg-emerald-400', border: 'border-emerald-500' },
            'RB': { bg: 'bg-emerald-400', border: 'border-emerald-500' },
            'LWB': { bg: 'bg-emerald-400', border: 'border-emerald-500' },
            'RWB': { bg: 'bg-emerald-400', border: 'border-emerald-500' },
            'CDM': { bg: 'bg-blue-400', border: 'border-blue-500' },
            'CM': { bg: 'bg-blue-400', border: 'border-blue-500' },
            'CAM': { bg: 'bg-blue-400', border: 'border-blue-500' },
            'LM': { bg: 'bg-blue-400', border: 'border-blue-500' },
            'RM': { bg: 'bg-blue-400', border: 'border-blue-500' },
            'LW': { bg: 'bg-red-400', border: 'border-red-500' },
            'RW': { bg: 'bg-red-400', border: 'border-red-500' },
            'ST': { bg: 'bg-red-400', border: 'border-red-500' },
            'CF': { bg: 'bg-red-400', border: 'border-red-500' }
        };
        return colorMap[position] || colorMap['GK'];
    }

    // Start match simulation
    function startMatch() {
        if (isMatchRunning) return;

        isMatchRunning = true;
        matchTimer = 0;
        currentEventIndex = 0;

        $('#startMatch').addClass('hidden');
        $('#pauseMatch').removeClass('hidden');
        $('#matchStatus').html('<i data-lucide="play" class="w-5 h-5"></i> Match In Progress');

        // Reset scores and statistics
        $('#userScore').text('0');
        $('#opponentScore').text('0');
        $('#liveUserGoals').text('0');
        $('#liveOpponentGoals').text('0');
        $('#liveEventsCount').text('0');
        $('#matchProgress').text('0%');

        // Clear events
        $('#liveEvents').html('<div class="text-center text-gray-500 py-4">Match starting...</div>');

        // Start timer
        matchInterval = setInterval(() => {
            matchTimer++;
            $('#timerDisplay').text(matchTimer + "'");

            // Update match progress
            const progress = Math.round((matchTimer / 90) * 100);
            $('#matchProgress').text(progress + '%');

            // Check for events at this minute
            checkForEvents(matchTimer);

            // End match at 90 minutes
            if (matchTimer >= 90) {
                endMatch();
            }
        }, 100); // Fast simulation - 100ms per minute

        lucide.createIcons();
    }

    function checkForEvents(minute) {
        const events = matchData.matchResult.events;

        events.forEach((event, index) => {
            if (event.minute === minute && index >= currentEventIndex) {
                displayEvent(event);

                if (event.type === 'goal') {
                    if (event.team === 'user') {
                        const currentScore = parseInt($('#userScore').text());
                        $('#userScore').text(currentScore + 1);
                        $('#liveUserGoals').text(currentScore + 1);
                        // Celebration animation for user goal
                        $('#userScore').addClass('animate-bounce text-green-600');
                        setTimeout(() => $('#userScore').removeClass('animate-bounce text-green-600'), 2000);
                    } else {
                        const currentScore = parseInt($('#opponentScore').text());
                        $('#opponentScore').text(currentScore + 1);
                        $('#liveOpponentGoals').text(currentScore + 1);
                        // Animation for opponent goal
                        $('#opponentScore').addClass('animate-pulse text-red-600');
                        setTimeout(() => $('#opponentScore').removeClass('animate-pulse text-red-600'), 2000);
                    }
                }

                // Update live events counter
                const eventCount = parseInt($('#liveEventsCount').text()) + 1;
                $('#liveEventsCount').text(eventCount);

                currentEventIndex = index + 1;
            }
        });
    }

    function displayEvent(event) {
        const eventHtml = `
            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg animate-pulse">
                <div class="w-12 h-8 bg-gray-200 rounded flex items-center justify-center text-sm font-bold text-gray-700">
                    ${event.minute}'
                </div>
                <div class="flex-1">
                    ${getEventIcon(event.type)}
                    <span class="font-medium">${event.description}</span>
                </div>
            </div>
        `;

        $('#liveEvents').prepend(eventHtml);
        lucide.createIcons();
    }

    function getEventIcon(type) {
        switch (type) {
            case 'goal':
                return '<i data-lucide="target" class="w-4 h-4 text-green-600 inline mr-2"></i>';
            case 'yellow_card':
                return '<div class="w-4 h-4 bg-yellow-400 rounded-sm inline-block mr-2"></div>';
            case 'red_card':
                return '<div class="w-4 h-4 bg-red-500 rounded-sm inline-block mr-2"></div>';
            default:
                return '<i data-lucide="info" class="w-4 h-4 text-blue-600 inline mr-2"></i>';
        }
    }

    function pauseMatch() {
        if (!isMatchRunning) return;

        clearInterval(matchInterval);
        isMatchRunning = false;

        $('#startMatch').removeClass('hidden');
        $('#pauseMatch').addClass('hidden');
        $('#matchStatus').html('<i data-lucide="pause" class="w-5 h-5"></i> Match Paused');

        lucide.createIcons();
    }

    function endMatch() {
        clearInterval(matchInterval);
        isMatchRunning = false;

        $('#startMatch').addClass('hidden');
        $('#pauseMatch').addClass('hidden');

        const userGoals = parseInt($('#userScore').text());
        const opponentGoals = parseInt($('#opponentScore').text());

        let resultText = '';
        let resultClass = '';

        if (userGoals > opponentGoals) {
            resultText = '<i data-lucide="trophy" class="w-5 h-5"></i> VICTORY!';
            resultClass = 'text-green-600';
        } else if (userGoals < opponentGoals) {
            resultText = '<i data-lucide="frown" class="w-5 h-5"></i> DEFEAT!';
            resultClass = 'text-red-600';
        } else {
            resultText = '<i data-lucide="equal" class="w-5 h-5"></i> DRAW!';
            resultClass = 'text-yellow-600';
        }

        $('#matchStatus').html(resultText).removeClass('text-gray-600').addClass(resultClass);
        $('#timerDisplay').text("90' FT");

        // Add final whistle event
        displayEvent({
            minute: 90,
            type: 'whistle',
            description: 'Full Time! Match finished.'
        });

        lucide.createIcons();
    }

    // Event listeners
    $('#startMatch').click(startMatch);
    $('#pauseMatch').click(pauseMatch);

    // Initialize field
    $(document).ready(() => {
        renderFieldPlayers();
        lucide.createIcons();
    });
</script>

<?php
// End content capture and render layout
endContent('Live Match Simulation', 'match');
?>