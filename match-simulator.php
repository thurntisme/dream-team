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

<div class="container mx-auto p-4 max-w-4xl">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Match Result</h1>
            <p class="text-gray-600">Friendly Match Simulation</p>
            <div class="mt-4 inline-flex items-center gap-2 bg-blue-100 text-blue-800 px-4 py-2 rounded-full text-sm">
                <i data-lucide="clock" class="w-4 h-4"></i>
                Full Time: 90 minutes
            </div>
        </div>

        <!-- Match Result Display -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-8 mb-6">
            <div class="flex justify-between items-center">
                <!-- User Team -->
                <div class="text-center flex-1">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="shield" class="w-8 h-8 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($user_data['club_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user_data['name']); ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo formatMarketValue($user_team_value); ?></p>
                </div>

                <!-- Score -->
                <div class="text-center mx-8">
                    <div class="text-6xl font-bold text-gray-900 mb-2">
                        <?php echo $match_result['userGoals']; ?> - <?php echo $match_result['opponentGoals']; ?>
                    </div>
                    <div class="text-lg font-semibold <?php
                    echo $match_result['result'] === 'win' ? 'text-green-600' :
                        ($match_result['result'] === 'draw' ? 'text-yellow-600' : 'text-red-600');
                    ?> flex items-center justify-center gap-2">
                        <?php if ($match_result['result'] === 'win'): ?>
                            <i data-lucide="trophy" class="w-5 h-5"></i>
                            VICTORY!
                        <?php elseif ($match_result['result'] === 'draw'): ?>
                            <i data-lucide="equal" class="w-5 h-5"></i>
                            DRAW!
                        <?php else: ?>
                            <i data-lucide="frown" class="w-5 h-5"></i>
                            DEFEAT!
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Opponent Team -->
                <div class="text-center flex-1">
                    <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
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

        <!-- Match Events -->
        <?php if (!empty($match_result['events'])): ?>
            <div class="bg-white rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                    Match Events
                </h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php foreach ($match_result['events'] as $event): ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div
                                class="w-12 h-8 bg-gray-200 rounded flex items-center justify-center text-sm font-bold text-gray-700">
                                <?php echo $event['minute']; ?>'
                            </div>
                            <div class="flex-1">
                                <?php if ($event['type'] === 'goal'): ?>
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="target" class="w-4 h-4 text-green-600"></i>
                                        <span class="font-medium"><?php echo htmlspecialchars($event['description']); ?></span>
                                    </div>
                                <?php elseif ($event['type'] === 'yellow_card'): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-yellow-400 rounded-sm"></div>
                                        <span class="font-medium"><?php echo htmlspecialchars($event['description']); ?></span>
                                    </div>
                                <?php elseif ($event['type'] === 'red_card'): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 bg-red-500 rounded-sm"></div>
                                        <span class="font-medium"><?php echo htmlspecialchars($event['description']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Match Summary -->
        <div class="bg-white rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                Match Summary
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-blue-600 mb-1"><?php echo $match_result['userGoals']; ?></div>
                    <div class="text-sm text-gray-600">Goals Scored</div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($user_data['club_name']); ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-gray-600 mb-1"><?php echo count($match_result['events']); ?></div>
                    <div class="text-sm text-gray-600">Total Events</div>
                    <div class="text-xs text-gray-500 mt-1">Cards & Goals</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-red-600 mb-1"><?php echo $match_result['opponentGoals']; ?></div>
                    <div class="text-sm text-gray-600">Goals Scored</div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($opponent_data['club_name']); ?></div>
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
    lucide.createIcons();
</script>

<?php
// End content capture and render layout
endContent('Match Result', 'match');
?>