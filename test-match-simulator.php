<?php
/**
 * Test Match Simulator - Clone of match-simulator.php with fake players
 * Standalone match simulator with fake teams for testing purposes
 */

require_once 'config/constants.php';

// Generate fake player data
function generateFakePlayer($name, $position, $rating = null) {
    $rating = $rating ?? rand(65, 95);
    return [
        'uuid' => uniqid('player_'),
        'name' => $name,
        'position' => $position,
        'rating' => $rating,
        'age' => rand(18, 35),
        'nationality' => ['England', 'Spain', 'Brazil', 'Germany', 'France', 'Italy', 'Argentina'][rand(0, 6)],
        'fitness' => rand(85, 100),
        'form' => rand(6, 10),
        'level' => rand(1, 5),
        'experience' => rand(0, 1000),
        'value' => $rating * 1000000 + rand(-10000000, 10000000)
    ];
}

// Generate fake team
function generateFakeTeam($teamName, $formation = '4-4-2') {
    $players = [];
    
    // Team 1 - Manchester City style
    if ($teamName === 'Manchester City') {
        $players = [
            generateFakePlayer('Ederson', 'GK', 88),
            generateFakePlayer('Walker', 'RB', 85),
            generateFakePlayer('Stones', 'CB', 87),
            generateFakePlayer('Dias', 'CB', 89),
            generateFakePlayer('Cancelo', 'LB', 86),
            generateFakePlayer('Rodri', 'CDM', 90),
            generateFakePlayer('De Bruyne', 'CM', 94),
            generateFakePlayer('Bernardo Silva', 'CM', 88),
            generateFakePlayer('Mahrez', 'RW', 86),
            generateFakePlayer('Haaland', 'ST', 92),
            generateFakePlayer('Grealish', 'LW', 84)
        ];
    }
    // Team 2 - Liverpool style
    else if ($teamName === 'Liverpool') {
        $players = [
            generateFakePlayer('Alisson', 'GK', 89),
            generateFakePlayer('Alexander-Arnold', 'RB', 87),
            generateFakePlayer('Van Dijk', 'CB', 91),
            generateFakePlayer('Konate', 'CB', 85),
            generateFakePlayer('Robertson', 'LB', 86),
            generateFakePlayer('Fabinho', 'CDM', 87),
            generateFakePlayer('Henderson', 'CM', 83),
            generateFakePlayer('Thiago', 'CM', 86),
            generateFakePlayer('Salah', 'RW', 90),
            generateFakePlayer('Nunez', 'ST', 84),
            generateFakePlayer('Diaz', 'LW', 85)
        ];
    }
    
    return $players;
}

// Fake data setup
$user_team = generateFakeTeam('Manchester City', '4-3-3');
$opponent_team = generateFakeTeam('Liverpool', '4-3-3');

$user_data = [
    'name' => 'Test Manager',
    'club_name' => 'Manchester City',
    'formation' => '4-3-3',
    'team' => json_encode($user_team),
    'budget' => 1000000000
];

$opponent_data = [
    'name' => 'AI Manager',
    'club_name' => 'Liverpool', 
    'formation' => '4-3-3',
    'team' => json_encode($opponent_team),
    'budget' => 950000000
];

// Challenge system configuration
define('CHALLENGE_BASE_COST', 5000000); // €5M base cost
define('WIN_REWARD_PERCENTAGE', 1.5); // 150% of challenge cost (50% profit + cost back)

$challenge_cost = CHALLENGE_BASE_COST;
$potential_reward = $challenge_cost * WIN_REWARD_PERCENTAGE;

// Calculate team values
$user_team_value = calculateTeamValue($user_team);
$opponent_team_value = calculateTeamValue($opponent_team);

// Simulate the match
$match_result = simulateMatch($user_team, $opponent_team, $user_team_value, $opponent_team_value);

// Process match result and calculate rewards
$financial_result = calculateMatchRewards(1, $match_result, $challenge_cost, $potential_reward);

// Calculate level bonus percentage
function calculateLevelBonus($level)
{
    // Progressive bonus system: 2% per level up to 100%
    return min($level * 0.02, 1.0); // Cap at 100% bonus
}

// Get club level name
function getClubLevelName($level)
{
    switch ($level) {
        case 5:
            return 'Elite';
        case 4:
            return 'Professional';
        case 3:
            return 'Semi-Professional';
        case 2:
            return 'Amateur';
        case 1:
        default:
            return 'Beginner';
    }
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
        'events' => $events,
        'injuries' => []
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

function calculateMatchRewards($user_id, $match_result, $challenge_cost, $potential_reward)
{
    $club_level = 3; // Fake club level
    $level_bonus = calculateLevelBonus($club_level);

    $earnings = 0;
    $bonus_earnings = 0;
    $result_message = '';

    if ($match_result['result'] === 'win') {
        $earnings = $potential_reward;
        $bonus_earnings = $earnings * $level_bonus;
        $result_message = 'Victory! You can earn ' . formatMarketValue($earnings) . ' in prize money';
        if ($bonus_earnings > 0) {
            $result_message .= ' + ' . formatMarketValue($bonus_earnings) . ' club level bonus (Level ' . $club_level . ')!';
        } else {
            $result_message .= '!';
        }
    } elseif ($match_result['result'] === 'draw') {
        $earnings = $challenge_cost * 0.8;
        $bonus_earnings = $earnings * ($level_bonus * 0.5);
        $result_message = 'Draw! You can receive ' . formatMarketValue($earnings) . ' (80% refund)';
        if ($bonus_earnings > 0) {
            $result_message .= ' + ' . formatMarketValue($bonus_earnings) . ' level bonus.';
        } else {
            $result_message .= '.';
        }
    } else {
        if ($club_level >= 2) {
            $consolation_rate = 0.1 + ($club_level * 0.05);
            $bonus_earnings = $challenge_cost * $consolation_rate;
            $result_message = 'Defeat! You lost the challenge fee but can receive ' . formatMarketValue($bonus_earnings) . ' consolation bonus (' . round($consolation_rate * 100) . '% - Level ' . $club_level . ').';
        } else {
            $bonus_earnings = $challenge_cost * 0.1;
            $result_message = 'Defeat! You lost the challenge fee but can receive ' . formatMarketValue($bonus_earnings) . ' consolation bonus (10% - Level ' . $club_level . ').';
        }
    }

    return [
        'earnings' => $earnings + $bonus_earnings,
        'base_earnings' => $earnings,
        'bonus_earnings' => $bonus_earnings,
        'club_level' => $club_level,
        'challenge_cost' => $challenge_cost,
        'message' => $result_message,
        'pending' => true
    ];
}

// Format market value function
function formatMarketValue($value) {
    if ($value >= 1000000) {
        return '€' . number_format($value / 1000000, 1) . 'M';
    } else if ($value >= 1000) {
        return '€' . number_format($value / 1000) . 'K';
    } else {
        return '€' . number_format($value);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Match Simulator - Dream Team</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<div class="p-4">
    <div class="bg-white rounded-lg p-6 mb-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Test Challenge Match</h1>
            <p class="text-gray-600">Competitive Match - Stakes: <?php echo formatMarketValue($challenge_cost); ?></p>

            <!-- Challenge Info -->
            <div class="mt-4 inline-flex items-center gap-4 bg-blue-100 text-blue-800 px-6 py-3 rounded-full text-sm">
                <div class="flex items-center gap-2">
                    <i data-lucide="coins" class="w-4 h-4"></i>
                    <span>Entry Fee: <?php echo formatMarketValue($challenge_cost); ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                    <span>Win Prize: <?php echo formatMarketValue($potential_reward); ?></span>
                </div>
            </div>

            <!-- Match Timer -->
            <div class="mt-4 flex justify-center items-center gap-4">
                <div id="matchTimer"
                    class="inline-flex items-center gap-2 bg-red-600 text-white px-6 py-3 rounded-full text-lg font-bold">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                    <span id="timerDisplay">0'</span>
                </div>
                <div id="halfIndicator"
                    class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-full text-sm font-semibold">
                    <i data-lucide="circle" class="w-4 h-4"></i>
                    <span id="halfDisplay">Pre-Match</span>
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
                <!-- Home Team -->
                <div class="text-center flex-1">
                    <div
                        class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                        <i data-lucide="home" class="w-8 h-8 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($user_data['club_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user_data['name']); ?></p>
                    <p class="text-xs text-blue-600 font-semibold">HOME</p>
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
                        <span id="matchStatusText">Pending - Ready to Start</span>
                    </div>
                </div>

                <!-- Away Team -->
                <div class="text-center flex-1">
                    <div
                        class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                        <i data-lucide="plane" class="w-8 h-8 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <?php echo htmlspecialchars($opponent_data['club_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($opponent_data['name']); ?></p>
                    <p class="text-xs text-red-600 font-semibold">AWAY</p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo formatMarketValue($opponent_team_value); ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <!-- Football Field with Players -->
            <div class="bg-white rounded-lg p-6 mb-6 col-span-7">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map" class="w-5 h-5"></i>
                    Live Field View
                </h3>
                <div class="bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow-lg relative"
                    style="min-height: 600px; height: 600px;" id="flat-field">
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

                    <!-- Ball -->
                    <div id="ball" class="absolute w-4 h-4 bg-white rounded-full shadow-lg border border-gray-300 transition-all duration-300"
                            style="top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10;">
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
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="home" class="w-4 h-4 text-blue-600"></i>
                    Your Team (Home)
                </h4>
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
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="plane" class="w-4 h-4 text-red-600"></i>
                    Opponent (Away)
                </h4>
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

        <!-- Team Performance -->
        <div class="bg-white rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="trending-up" class="w-5 h-5"></i>
                Team Performance Analysis
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="text-center">
                    <h4 class="font-medium text-gray-900 mb-2">
                        <?php echo htmlspecialchars($user_data['club_name']); ?>
                    </h4>
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-blue-600 h-4 rounded-full transition-all duration-1000" style="width:
                                <?php echo $match_result['userStrength']; ?>%">
                        </div>
                    </div>
                    <p class="text-sm text-gray-600">Strength:
                        <?php echo $match_result['userStrength']; ?>%
                    </p>
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
            <button onclick="window.location.reload()" 
                    class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                New Match
            </button>
            <a href="match-simulator.php" 
               class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Real Match Simulator
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
        formations: <?php echo json_encode(FORMATIONS); ?>,
        // Home/Away team details
        homeTeam: {
            name: '<?php echo htmlspecialchars($user_data['club_name']); ?>',
            manager: '<?php echo htmlspecialchars($user_data['name']); ?>',
            formation: '<?php echo $user_data['formation']; ?>',
            value: <?php echo $user_team_value; ?>,
            players: <?php echo json_encode($user_team); ?>,
            type: 'home'
        },
        awayTeam: {
            name: '<?php echo htmlspecialchars($opponent_data['club_name']); ?>',
            manager: '<?php echo htmlspecialchars($opponent_data['name']); ?>',
            formation: '<?php echo $opponent_data['formation']; ?>',
            value: <?php echo $opponent_team_value; ?>,
            players: <?php echo json_encode($opponent_team); ?>,
            type: 'away'
        }
    };

    // Debug logging
    console.log('=== MATCH SIMULATOR DEBUG ===');
    console.log('Home Team Details:', matchData.homeTeam);
    console.log('Away Team Details:', matchData.awayTeam);
    console.log('Match Result Data:', matchData.matchResult);
    console.log('============================');

    let matchTimer = 0;
    let matchInterval = null;
    let isMatchRunning = false;
    let currentEventIndex = 0;
    let currentHalf = 1; // Track current half (1 or 2)
    let halfTimeBreak = false; // Track if we're in half-time break
    let firstHalfBonusTime = 0; // Bonus time for first half
    let secondHalfBonusTime = 0; // Bonus time for second half
    let inBonusTime = false; // Track if we're in bonus time
    let bonusTimeMinutes = 0; // Current bonus time minute

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
        let players = team;
        let positions = formationData.positions;
        let roles = formationData.roles;

        const isOpponent = teamType === 'opponent';
        let playerIdx = 0;

        positions.forEach((line, lineIdx) => {
            line.forEach(xPos => {
                const player = players[playerIdx];
                let yPos;

                if (isOpponent) {
                    // Opponent team in top half (GK at top)
                    xPos = 100 - xPos;
                    yPos = ((lineIdx + 1) * (50 / (positions.length + 1)));
                } else {
                    // User team in bottom half (GK at bottom)
                    yPos = 100 - ((lineIdx + 1) * (50 / (positions.length + 1)));
                }

                // Get the role for this position
                const requiredPosition = roles[playerIdx] || 'GK';
                const teamColor = isOpponent ? 'bg-red-500' : 'bg-blue-500';

                if (player) {
                    $field.append(`
                        <div class="absolute transition-all duration-200" 
                             style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);">
                            <div class="w-11 h-11 ${teamColor} rounded-full flex flex-col items-center justify-center shadow-lg border-2 border-white">
                                <i data-lucide="user" class="w-4 h-4 text-white"></i>
                                <span class="text-[8px] font-bold text-white">${requiredPosition}</span>
                            </div>
                            <div class="absolute ${isOpponent ? 'bottom-full mb-1' : 'top-full mt-1'} left-1/2 transform -translate-x-1/2 whitespace-nowrap">
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

    // Start match simulation
    function startMatch() {
        if (isMatchRunning) return;

        console.log('=== MATCH START DEBUG ===');
        console.log('Starting match between:', matchData.homeTeam.name, 'vs', matchData.awayTeam.name);
        console.log('Home team formation:', matchData.homeTeam.formation);
        console.log('Away team formation:', matchData.awayTeam.formation);

        isMatchRunning = true;
        matchTimer = 0;
        currentEventIndex = 0;
        currentHalf = 1;
        halfTimeBreak = false;
        inBonusTime = false;
        bonusTimeMinutes = 0;
        
        // Generate random bonus time for each half (1-5 minutes)
        firstHalfBonusTime = Math.floor(Math.random() * 5) + 1;
        secondHalfBonusTime = Math.floor(Math.random() * 5) + 1;
        
        console.log('Bonus time generated:');
        console.log('- First half bonus time:', firstHalfBonusTime, 'minutes');
        console.log('- Second half bonus time:', secondHalfBonusTime, 'minutes');

        $('#startMatch').addClass('hidden');
        $('#pauseMatch').removeClass('hidden');
        updateMatchStatus('1st Half - Match in Progress');

        // Reset scores
        $('#userScore').text('0');
        $('#opponentScore').text('0');

        // Clear events and add kick-off
        $('#liveEvents').html('');
        displayEvent({
            minute: 0,
            type: 'kickoff',
            description: `Kick-off! ${matchData.homeTeam.name} (Home) vs ${matchData.awayTeam.name} (Away) - 1st Half begins!`
        });

        console.log('Match events to simulate:', matchData.matchResult.events);

        // Start timer
        matchInterval = setInterval(() => {
            matchTimer++;
            updateTimer();

            // First half logic
            if (currentHalf === 1) {
                // Regular first half (1-45 minutes)
                if (matchTimer === 45 && !inBonusTime) {
                    startBonusTime(1);
                }
                // First half bonus time
                else if (matchTimer > 45 && !halfTimeBreak) {
                    bonusTimeMinutes = matchTimer - 45;
                    if (bonusTimeMinutes >= firstHalfBonusTime) {
                        handleHalfTime();
                    }
                }
                // Resume second half
                else if (matchTimer === 46 + firstHalfBonusTime && halfTimeBreak) {
                    resumeSecondHalf();
                }
            }
            // Second half logic
            else if (currentHalf === 2) {
                const secondHalfStart = 46 + firstHalfBonusTime;
                const secondHalfMinute = matchTimer - secondHalfStart + 1;
                
                // Regular second half (46-90 minutes equivalent)
                if (secondHalfMinute === 45 && !inBonusTime) {
                    startBonusTime(2);
                }
                // Second half bonus time
                else if (secondHalfMinute > 45) {
                    bonusTimeMinutes = secondHalfMinute - 45;
                    if (bonusTimeMinutes >= secondHalfBonusTime) {
                        endMatch();
                    }
                }
            }

            // Check for events at this minute (not during half-time break)
            if (!halfTimeBreak) {
                checkForEvents(matchTimer);
            }
        }, 100); // Fast simulation - 100ms per minute

        lucide.createIcons();
    }

    function updateTimer() {
        let displayText = '';
        let halfText = '';
        
        if (halfTimeBreak) {
            displayText = "HT";
            halfText = 'Half-Time';
        } else if (currentHalf === 1) {
            if (inBonusTime && bonusTimeMinutes > 0) {
                displayText = `45+${bonusTimeMinutes}'`;
                halfText = '1st Half +' + bonusTimeMinutes;
            } else {
                displayText = matchTimer + "'";
                halfText = '1st Half';
            }
        } else if (currentHalf === 2) {
            const secondHalfStart = 46 + firstHalfBonusTime;
            const secondHalfMinute = matchTimer - secondHalfStart + 1;
            
            if (inBonusTime && bonusTimeMinutes > 0) {
                displayText = `90+${bonusTimeMinutes}'`;
                halfText = '2nd Half +' + bonusTimeMinutes;
            } else {
                const displayMinute = Math.min(secondHalfMinute + 45, 90);
                displayText = displayMinute + "'";
                halfText = '2nd Half';
            }
        }
        
        $('#timerDisplay').text(displayText);
        
        // Update half indicator
        if (halfTimeBreak) {
            $('#halfDisplay').text('Half-Time');
            $('#halfIndicator').removeClass('bg-blue-600 bg-green-600 bg-purple-600').addClass('bg-orange-600');
        } else if (inBonusTime) {
            $('#halfDisplay').text(halfText);
            $('#halfIndicator').removeClass('bg-blue-600 bg-green-600 bg-orange-600').addClass('bg-purple-600');
        } else {
            $('#halfDisplay').text(halfText);
            $('#halfIndicator').removeClass('bg-orange-600 bg-purple-600').addClass(currentHalf === 1 ? 'bg-blue-600' : 'bg-green-600');
        }
        
        console.log(`Timer Update: ${displayText} ${halfText} (Total: ${matchTimer})`);
    }

    function updateMatchStatus(status) {
        $('#matchStatusText').text(status);
        console.log('Match Status:', status);
    }

    function startBonusTime(half) {
        console.log(`=== BONUS TIME ${half === 1 ? 'FIRST' : 'SECOND'} HALF DEBUG ===`);
        
        inBonusTime = true;
        bonusTimeMinutes = 0;
        
        const bonusMinutes = half === 1 ? firstHalfBonusTime : secondHalfBonusTime;
        const halfName = half === 1 ? '1st Half' : '2nd Half';
        
        console.log(`${halfName} bonus time: ${bonusMinutes} minutes`);
        
        updateMatchStatus(`${halfName} - Bonus Time (+${bonusMinutes} min)`);
        
        displayEvent({
            minute: half === 1 ? 45 : 90,
            type: 'bonus_time',
            description: `${halfName} - The referee indicates ${bonusMinutes} minute${bonusMinutes > 1 ? 's' : ''} of added time`
        });

        console.log('===============================');
    }

    function handleHalfTime() {
        console.log('=== HALF-TIME DEBUG ===');
        console.log(`45+${firstHalfBonusTime} minutes completed - Half-time break`);
        
        halfTimeBreak = true;
        inBonusTime = false;
        bonusTimeMinutes = 0;
        updateMatchStatus('Half-Time Break');
        
        displayEvent({
            minute: 45 + firstHalfBonusTime,
            type: 'halftime',
            description: `Half-Time! ${matchData.homeTeam.name} ${$('#userScore').text()} - ${$('#opponentScore').text()} ${matchData.awayTeam.name} (After ${firstHalfBonusTime} min bonus time)`
        });

        console.log('Half-time score:', $('#userScore').text(), '-', $('#opponentScore').text());
        console.log('First half bonus time used:', firstHalfBonusTime, 'minutes');
        console.log('======================');
    }

    function resumeSecondHalf() {
        console.log('=== SECOND HALF DEBUG ===');
        console.log('Second half starting...');
        
        currentHalf = 2;
        halfTimeBreak = false;
        inBonusTime = false;
        bonusTimeMinutes = 0;
        updateMatchStatus('2nd Half - Match in Progress');
        
        displayEvent({
            minute: 46 + firstHalfBonusTime,
            type: 'secondhalf',
            description: `Second Half begins! ${matchData.awayTeam.name} kicks off the 2nd half`
        });

        console.log('Second half will have', secondHalfBonusTime, 'minutes of bonus time');
        console.log('========================');
    }

    function checkForEvents(minute) {
        const events = matchData.matchResult.events;

        events.forEach((event, index) => {
            if (event.minute === minute && index >= currentEventIndex) {
                console.log(`Event at ${minute}':`, event);
                
                // Add half information to event
                const halfInfo = minute <= 45 ? '1st Half' : '2nd Half';
                const teamInfo = event.team === 'user' ? 
                    `${matchData.homeTeam.name} (Home)` : 
                    `${matchData.awayTeam.name} (Away)`;
                
                // Enhanced event description with team details
                const enhancedEvent = {
                    ...event,
                    description: `[${halfInfo}] ${event.description} - ${teamInfo}`
                };
                
                displayEvent(enhancedEvent);

                if (event.type === 'goal') {
                    console.log(`GOAL! ${event.player} scores for ${teamInfo} in ${halfInfo}`);
                    
                    if (event.team === 'user') {
                        const currentScore = parseInt($('#userScore').text());
                        $('#userScore').text(currentScore + 1);
                        $('#userScore').addClass('animate-bounce text-green-600');
                        setTimeout(() => $('#userScore').removeClass('animate-bounce text-green-600'), 2000);
                    } else {
                        const currentScore = parseInt($('#opponentScore').text());
                        $('#opponentScore').text(currentScore + 1);
                        $('#opponentScore').addClass('animate-pulse text-red-600');
                        setTimeout(() => $('#opponentScore').removeClass('animate-pulse text-red-600'), 2000);
                    }
                }

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
            case 'kickoff':
                return '<i data-lucide="play-circle" class="w-4 h-4 text-blue-600 inline mr-2"></i>';
            case 'halftime':
                return '<i data-lucide="coffee" class="w-4 h-4 text-orange-600 inline mr-2"></i>';
            case 'secondhalf':
                return '<i data-lucide="rotate-ccw" class="w-4 h-4 text-blue-600 inline mr-2"></i>';
            case 'bonus_time':
                return '<i data-lucide="clock" class="w-4 h-4 text-purple-600 inline mr-2"></i>';
            case 'whistle':
                return '<i data-lucide="flag" class="w-4 h-4 text-gray-600 inline mr-2"></i>';
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
        $('#matchStatusText').text('Processing - Match Paused');

        lucide.createIcons();
    }

    function endMatch() {
        console.log('=== MATCH END DEBUG ===');
        
        clearInterval(matchInterval);
        isMatchRunning = false;
        inBonusTime = false;

        $('#startMatch').addClass('hidden');
        $('#pauseMatch').addClass('hidden');

        const userGoals = parseInt($('#userScore').text());
        const opponentGoals = parseInt($('#opponentScore').text());

        console.log('Final Score:', `${matchData.homeTeam.name} ${userGoals} - ${opponentGoals} ${matchData.awayTeam.name}`);

        let resultText = '';
        let resultClass = '';

        if (userGoals > opponentGoals) {
            resultText = '<i data-lucide="trophy" class="w-5 h-5"></i> VICTORY!';
            resultClass = 'text-green-600';
            console.log('Result: HOME TEAM WINS!');
        } else if (userGoals < opponentGoals) {
            resultText = '<i data-lucide="frown" class="w-5 h-5"></i> DEFEAT!';
            resultClass = 'text-red-600';
            console.log('Result: AWAY TEAM WINS!');
        } else {
            resultText = '<i data-lucide="equal" class="w-5 h-5"></i> DRAW!';
            resultClass = 'text-yellow-600';
            console.log('Result: DRAW!');
        }

        $('#matchStatus').html(resultText + ' <span id="matchStatusText">Full Time - Match Complete</span>').removeClass('text-gray-600').addClass(resultClass);
        $('#timerDisplay').text(`90+${secondHalfBonusTime}' FT`);

        // Add final whistle event with detailed result including bonus time
        const totalMatchTime = 90 + firstHalfBonusTime + secondHalfBonusTime;
        displayEvent({
            minute: totalMatchTime,
            type: 'whistle',
            description: `Full Time! ${matchData.homeTeam.name} (Home) ${userGoals} - ${opponentGoals} ${matchData.awayTeam.name} (Away) - After ${secondHalfBonusTime} min bonus time`
        });

        console.log('Match Statistics:');
        console.log('- Home Team Strength:', matchData.matchResult.userStrength);
        console.log('- Away Team Strength:', matchData.matchResult.opponentStrength);
        console.log('- Total Events:', matchData.matchResult.events.length);
        console.log('- First Half Bonus Time:', firstHalfBonusTime, 'minutes');
        console.log('- Second Half Bonus Time:', secondHalfBonusTime, 'minutes');
        console.log('- Total Match Duration:', totalMatchTime, 'minutes');
        console.log('======================');

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

</body>
</html>