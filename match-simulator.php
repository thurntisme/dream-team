<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';



// Get opponent ID from URL
$opponent_id = $_GET['opponent'] ?? null;
if (!$opponent_id) {
    header('Location: clubs.php');
    exit;
}

// Challenge system configuration
define('CHALLENGE_BASE_COST', 5000000); // €5M base cost
define('WIN_REWARD_PERCENTAGE', 1.5); // 150% of challenge cost (50% profit + cost back)

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

    // Check if challenge was properly initiated
    if (!isset($_SESSION['active_challenge']) || $_SESSION['active_challenge']['opponent_id'] != $opponent_id) {
        $_SESSION['challenge_error'] = 'Invalid challenge session. Please initiate the challenge again.';
        header('Location: clubs.php');
        exit;
    }

    // Get challenge data from session
    $challenge_cost = $_SESSION['active_challenge']['challenge_cost'];
    $potential_reward = $challenge_cost * 1.5; // 150% of challenge cost

    // Validate teams (without deducting budget again)
    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $user_player_count = count(array_filter($user_team, fn($p) => $p !== null));

    if ($user_player_count < 11) {
        $_SESSION['challenge_error'] = 'You need a complete team (11 players) to challenge other clubs!';
        header('Location: clubs.php');
        exit;
    }

    $opponent_team = json_decode($opponent_data['team'] ?? '[]', true);
    $opponent_player_count = count(array_filter($opponent_team, fn($p) => $p !== null));
    if ($opponent_player_count < 11) {
        $_SESSION['challenge_error'] = 'This club doesn\'t have a complete team and cannot be challenged.';
        header('Location: clubs.php');
        exit;
    }

    // Calculate team values
    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $opponent_team = json_decode($opponent_data['team'] ?? '[]', true);

    $user_team_value = calculateTeamValue($user_team);
    $opponent_team_value = calculateTeamValue($opponent_team);

    // Simulate the match
    $match_result = simulateMatch($user_team, $opponent_team, $user_team_value, $opponent_team_value);

    // Apply match injuries to user team
    if (!empty($match_result['injuries'])) {
        $user_team = applyMatchInjuries($user_team, $match_result['injuries']);
        
        // Update user team in database with injuries
        $stmt = $db->prepare('UPDATE users SET team = :team WHERE id = :user_id');
        $stmt->bindValue(':team', json_encode($user_team), SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    // Process match result and calculate rewards (but don't apply yet)
    $financial_result = calculateMatchRewards($_SESSION['user_id'], $match_result, $challenge_cost, $potential_reward);

    // Store pending reward in session for approval
    $_SESSION['pending_reward'] = [
        'amount' => $financial_result['earnings'],
        'challenge_cost' => $challenge_cost,
        'match_result' => $match_result['result'],
        'opponent_name' => $opponent_data['club_name'],
        'financial_details' => $financial_result
    ];

    // Clear the active challenge session since match is now set up
    unset($_SESSION['active_challenge']);

    $db->close();

} catch (Exception $e) {
    header('Location: clubs.php');
    exit;
}

// Challenge validation functions (budget deduction moved to initiate_challenge.php)

function calculateMatchRewards($user_id, $match_result, $challenge_cost, $potential_reward)
{
    global $db;

    // Get user data for club level calculation
    $stmt = $db->prepare('SELECT budget, team FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $club_level = $user_data['club_level'] ?? 1;
    $level_bonus = calculateLevelBonus($club_level);

    $earnings = 0;
    $bonus_earnings = 0;
    $result_message = '';

    if ($match_result['result'] === 'win') {
        // User wins - calculate reward + level bonus (pending approval)
        $earnings = $potential_reward;
        $bonus_earnings = $earnings * $level_bonus;

        $result_message = 'Victory! You can earn ' . formatMarketValue($earnings) . ' in prize money';
        if ($bonus_earnings > 0) {
            $result_message .= ' + ' . formatMarketValue($bonus_earnings) . ' club level bonus (Level ' . $club_level . ')!';
        } else {
            $result_message .= '!';
        }
    } elseif ($match_result['result'] === 'draw') {
        // Draw - calculate 80% refund + level bonus (pending approval)
        $earnings = $challenge_cost * 0.8;
        $bonus_earnings = $earnings * ($level_bonus * 0.5); // Half bonus for draws

        $result_message = 'Draw! You can receive ' . formatMarketValue($earnings) . ' (80% refund)';
        if ($bonus_earnings > 0) {
            $result_message .= ' + ' . formatMarketValue($bonus_earnings) . ' level bonus.';
        } else {
            $result_message .= '.';
        }
    } else {
        // Loss - calculate consolation bonus based on club level (pending approval)
        if ($club_level >= 2) {
            // Level-based consolation: Level 2: 15%, Level 3: 20%, Level 4: 25%, Level 5: 30%
            $consolation_rate = 0.1 + ($club_level * 0.05); // 15% to 30% based on level
            $bonus_earnings = $challenge_cost * $consolation_rate;

            $result_message = 'Defeat! You lost the challenge fee but can receive ' . formatMarketValue($bonus_earnings) . ' consolation bonus (' . round($consolation_rate * 100) . '% - Level ' . $club_level . ').';
        } else {
            // Beginner level still gets small consolation
            $bonus_earnings = $challenge_cost * 0.1; // 10% for beginners

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

    // Check for match injuries
    $match_injuries = checkMatchInjuries($user_team);

    // Generate match events
    $events = generateMatchEvents($user_team, $opponent_team, $user_goals, $opponent_goals, $match_injuries);

    return [
        'userGoals' => $user_goals,
        'opponentGoals' => $opponent_goals,
        'result' => $result,
        'userStrength' => round($user_strength, 1),
        'opponentStrength' => round($opponent_strength, 1),
        'events' => $events,
        'injuries' => $match_injuries
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

// Check for match injuries
function checkMatchInjuries($user_team) {
    $injuries = [];
    
    foreach ($user_team as $player) {
        if ($player && !isPlayerInjured($player)) {
            $injury_result = checkForMatchInjury($player);
            if ($injury_result['injured']) {
                $injuries[] = [
                    'player_uuid' => $player['uuid'],
                    'player_name' => $player['name'],
                    'injury_type' => $injury_result['injury_type'],
                    'minute' => mt_rand(1, 90)
                ];
            }
        }
    }
    
    return $injuries;
}

function checkForMatchInjury($player) {
    $base_injury_chance = 0.03; // 3% base chance during match
    $fitness = $player['fitness'] ?? 100;
    
    // Significantly increase injury chance for low fitness players
    if ($fitness < 40) {
        $base_injury_chance *= 4; // 12% chance for very low fitness
    } elseif ($fitness < 60) {
        $base_injury_chance *= 2.5; // 7.5% chance for low fitness
    } elseif ($fitness < 80) {
        $base_injury_chance *= 1.5; // 4.5% chance for moderate fitness
    }
    
    // Random check
    if (mt_rand(1, 10000) / 10000 <= $base_injury_chance) {
        // Determine injury type (more severe injuries for low fitness)
        $rand = mt_rand(1, 100) / 100;
        if ($fitness < 40) {
            // Low fitness players get more serious injuries
            if ($rand <= 0.5) {
                $injury_type = 'minor_strain';
            } elseif ($rand <= 0.8) {
                $injury_type = 'muscle_injury';
            } else {
                $injury_type = 'serious_injury';
            }
        } else {
            // Normal injury distribution
            if ($rand <= 0.8) {
                $injury_type = 'minor_strain';
            } elseif ($rand <= 0.95) {
                $injury_type = 'muscle_injury';
            } else {
                $injury_type = 'serious_injury';
            }
        }
        
        return ['injured' => true, 'injury_type' => $injury_type];
    }
    
    return ['injured' => false];
}

function isPlayerInjured($player) {
    return isset($player['injury']) && $player['injury']['days_remaining'] > 0;
}

function applyMatchInjuries($user_team, $match_injuries) {
    $injury_types = [
        'minor_strain' => [
            'name' => 'Minor Muscle Strain',
            'duration_days' => rand(3, 7),
            'fitness_penalty' => rand(10, 20)
        ],
        'muscle_injury' => [
            'name' => 'Muscle Injury',
            'duration_days' => rand(7, 14),
            'fitness_penalty' => rand(20, 35)
        ],
        'serious_injury' => [
            'name' => 'Serious Injury',
            'duration_days' => rand(14, 28),
            'fitness_penalty' => rand(35, 50)
        ]
    ];
    
    foreach ($match_injuries as $injury) {
        // Find the player in the team
        for ($i = 0; $i < count($user_team); $i++) {
            if ($user_team[$i] && $user_team[$i]['uuid'] === $injury['player_uuid']) {
                $injury_data = $injury_types[$injury['injury_type']];
                
                $user_team[$i]['injury'] = [
                    'type' => $injury['injury_type'],
                    'name' => $injury_data['name'],
                    'duration_days' => $injury_data['duration_days'],
                    'days_remaining' => $injury_data['duration_days'],
                    'fitness_penalty' => $injury_data['fitness_penalty'],
                    'injury_date' => date('Y-m-d H:i:s')
                ];
                
                // Apply immediate fitness penalty
                $user_team[$i]['fitness'] = max(0, ($user_team[$i]['fitness'] ?? 100) - $injury_data['fitness_penalty']);
                break;
            }
        }
    }
    
    return $user_team;
}

// Generate match events for display
function generateMatchEvents($user_team, $opponent_team, $user_goals, $opponent_goals, $match_injuries = [])
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

    // Add injury events
    foreach ($match_injuries as $injury) {
        $injury_names = [
            'minor_strain' => 'Minor Muscle Strain',
            'muscle_injury' => 'Muscle Injury',
            'serious_injury' => 'Serious Injury'
        ];
        
        $injury_name = $injury_names[$injury['injury_type']] ?? 'Injury';
        $events[] = [
            'minute' => $injury['minute'],
            'type' => 'injury',
            'team' => 'user',
            'player' => $injury['player_name'],
            'description' => "⚕️ {$injury['player_name']} suffers a {$injury_name} and may be out for several days."
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
    <div class="bg-white rounded-lg p-6 mb-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Challenge Match</h1>
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
                        <span id="matchStatusText">Pending - Ready to Start</span>
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
                    style="min-height: 600px; height: 600px;">
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

        <!-- Financial Result -->
        <div class="bg-white rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i data-lucide="banknote" class="w-5 h-5"></i>
                Financial Summary
                <?php if ($financial_result['pending']): ?>
                    <span id="financialStatusBadge"
                        class="ml-2 px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">
                        MATCH PENDING
                    </span>
                <?php endif; ?>
            </h3>
            <div id="financialSummaryCard"
                class="bg-gradient-to-r from-gray-50 to-gray-100 border-gray-200 border rounded-lg p-6 text-center">
                <div id="financialMessage" class="text-2xl font-bold mb-2 text-gray-700">
                    Match Not Started - Awaiting Results
                </div>

                <div class="hidden mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="text-sm text-orange-700 mb-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 inline mr-1"></i>
                        Click "Approve Reward" to receive your earnings and complete the transaction.
                    </div>
                </div>

                <!-- Club Level Display -->
                <div class="mb-4 p-3 bg-white bg-opacity-50 rounded-lg">
                    <div class="text-sm text-gray-600">Your Club Level</div>
                    <div class="text-lg font-bold text-purple-600">
                        Level <?php echo $financial_result['club_level']; ?> -
                        <?php echo getClubLevelName($financial_result['club_level']); ?>
                    </div>
                    <?php if ($financial_result['bonus_earnings'] > 0): ?>
                        <div class="text-xs text-purple-500 mt-1">
                            +<?php echo (calculateLevelBonus($financial_result['club_level']) * 100); ?>% level bonus
                            applied
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <div class="bg-white bg-opacity-50 rounded-lg p-3">
                        <div class="text-sm text-gray-600">Challenge Fee</div>
                        <div class="text-lg font-bold text-red-600">-<?php echo formatMarketValue($challenge_cost); ?>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-50 rounded-lg p-3">
                        <div class="text-sm text-gray-600">Base Earnings</div>
                        <div id="baseEarningsDisplay" class="text-lg font-bold text-gray-500">TBD</div>
                    </div>
                    <div class="bg-white bg-opacity-50 rounded-lg p-3">
                        <div class="text-sm text-gray-600">Level Bonus</div>
                        <div id="levelBonusDisplay" class="text-lg font-bold text-gray-500">TBD</div>
                    </div>
                    <div class="bg-white bg-opacity-50 rounded-lg p-3">
                        <div class="text-sm text-gray-600">Net Result</div>
                        <div id="netResultDisplay" class="text-lg font-bold text-gray-500">TBD</div>
                    </div>
                </div>

                <!-- Approval Button -->
                <?php if ($financial_result['pending']): ?>
                    <div id="approvalSection" class="mt-6 pt-4 border-t border-white border-opacity-50 hidden">
                        <button id="approveRewardBtn"
                            class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transform transition-all duration-200 hover:scale-105 flex items-center gap-2 mx-auto">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                            Approve Reward
                        </button>
                        <div class="text-xs text-gray-600 mt-2 opacity-75">
                            This will deduct the challenge fee and add your earnings to your budget
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Updated Budget Display -->
                <?php
                try {
                    $db = getDbConnection();
                    $stmt = $db->prepare('SELECT budget FROM users WHERE id = :id');
                    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $updated_user = $result->fetchArray(SQLITE3_ASSOC);
                    $db->close();
                    $current_budget = $updated_user['budget'];
                } catch (Exception $e) {
                    $current_budget = $user_data['budget'];
                }
                ?>
                <div class="mt-4 pt-4 border-t border-white border-opacity-50">
                    <div class="text-sm text-gray-600">
                        <?php echo $financial_result['pending'] ? 'Current Budget (Before Transaction)' : 'Updated Budget'; ?>
                    </div>
                    <div class="text-xl font-bold text-blue-600"><?php echo formatMarketValue($user_data['budget']); ?>
                    </div>
                    <?php if ($financial_result['pending']): ?>
                        <div id="afterApprovalBudget" class="text-xs text-gray-500 mt-1">
                            After approval: TBD (Awaiting match completion)
                        </div>
                    <?php endif; ?>
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
    <div class=" flex justify-center gap-4">
        <a href="clubs.php?<?php
        $_SESSION['match_success'] = $financial_result['message'];
        echo 'completed=1';
        ?>"
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
    let matchStatus = 'pending'; // pending, processing, end, processed

    // Function to update match status
    function updateMatchStatus(status) {
        matchStatus = status;
        const statusElement = document.getElementById('matchStatusText');
        const approvalSection = document.getElementById('approvalSection');
        const financialBadge = document.getElementById('financialStatusBadge');
        const financialMessage = document.getElementById('financialMessage');

        switch (status) {
            case 'pending':
                statusElement.textContent = 'Pending - Ready to Start';
                statusElement.className = '';
                if (approvalSection) approvalSection.classList.add('hidden');
                if (financialBadge) {
                    financialBadge.textContent = 'MATCH PENDING';
                    financialBadge.className = 'ml-2 px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full';
                }
                if (financialMessage) {
                    financialMessage.textContent = 'Match Not Started - Awaiting Results';
                    financialMessage.className = 'text-2xl font-bold mb-2 text-gray-700';
                }
                break;
            case 'processing':
                statusElement.textContent = 'Processing - Match in Progress';
                statusElement.className = 'text-blue-600';
                if (approvalSection) approvalSection.classList.add('hidden');
                if (financialBadge) {
                    financialBadge.textContent = 'MATCH IN PROGRESS';
                    financialBadge.className = 'ml-2 px-2 py-1 bg-blue-100 text-blue-600 text-xs font-medium rounded-full';
                }
                if (financialMessage) {
                    financialMessage.textContent = 'Match in Progress - Final Result Pending';
                    financialMessage.className = 'text-2xl font-bold mb-2 text-blue-700';
                }
                break;
            case 'end':
                statusElement.textContent = 'Ended - Processing Results';
                statusElement.className = 'text-orange-600';
                if (approvalSection) approvalSection.classList.add('hidden'); // Keep hidden until processing complete
                if (financialBadge) {
                    financialBadge.textContent = 'PROCESSING RESULTS';
                    financialBadge.className = 'ml-2 px-2 py-1 bg-orange-100 text-orange-600 text-xs font-medium rounded-full';
                }
                if (financialMessage) {
                    financialMessage.textContent = 'Match Ended - Processing Results...';
                    financialMessage.className = 'text-2xl font-bold mb-2 text-orange-700';
                }
                break;
            case 'processed':
                statusElement.textContent = 'Ended - Results Ready';
                statusElement.className = 'text-green-600';
                if (approvalSection) approvalSection.classList.remove('hidden'); // Now show approve button
                if (financialBadge) {
                    financialBadge.textContent = 'PENDING APPROVAL';
                    financialBadge.className = 'ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full';
                }
                break;
        }
    }

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
                    // yPos = 50 + ((lineIdx + 1) * (50 / (positions.length + 1)));
                    yPos = 100 - ((lineIdx + 1) * (50 / (positions.length + 1)));
                }

                // Get the role for this position (now properly aligned)
                const requiredPosition = roles[playerIdx] || 'GK';
                const colors = getPositionColors(requiredPosition);
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
        $('#matchStatus').html('<i data-lucide="play" class="w-5 h-5"></i> <span id="matchStatusText">Processing - Match in Progress</span>');

        // Update match status
        updateMatchStatus('processing');

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
        $('#matchStatus').html('<i data-lucide="pause" class="w-5 h-5"></i> <span id="matchStatusText">Processing - Match Paused</span>');

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

        $('#matchStatus').html(resultText + ' <span id="matchStatusText">Ended - Match Complete</span>').removeClass('text-gray-600').addClass(resultClass);
        $('#timerDisplay').text("90' FT");

        // Update match status to end
        updateMatchStatus('end');

        // Add final whistle event
        displayEvent({
            minute: 90,
            type: 'whistle',
            description: 'Full Time! Match finished.'
        });

        // Update financial summary and show result popup after match ends
        <?php if ($financial_result['pending']): ?>
            setTimeout(() => {
                if (matchStatus === 'end') {
                    showProcessingPopup();
                }
            }, 1000);
        <?php endif; ?>

        lucide.createIcons();
    }

    // Event listeners
    $('#startMatch').click(startMatch);
    $('#pauseMatch').click(pauseMatch);

    // Initialize field
    $(document).ready(() => {
        renderFieldPlayers();
        updateMatchStatus('pending'); // Initialize match status
        lucide.createIcons();
    });
</script>

<!-- Processing Popup -->
<div id="processingPopup" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 w-full max-w-sm mx-4 shadow-2xl">
        <div class="text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                <i data-lucide="loader" class="w-8 h-8 text-blue-600 animate-spin"></i>
            </div>
            <h2 class="text-xl font-bold mb-2 text-gray-900">Processing Match Result</h2>
            <p class="text-gray-600 mb-4">Calculating rewards and updating club data...</p>
            <div class="text-sm text-gray-500">Please wait</div>
        </div>
    </div>
</div>

<!-- Match Result Popup -->
<div id="matchResultPopup" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 w-full max-w-md mx-4 shadow-2xl">
        <div class="text-center">
            <div id="popupResultIcon" class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center">
                <i id="popupResultIconSvg" class="w-8 h-8"></i>
            </div>
            <h2 id="popupResultTitle" class="text-2xl font-bold mb-2"></h2>
            <p id="popupResultMessage" class="text-gray-600 mb-6"></p>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="text-sm text-gray-600 mb-2">Financial Summary</div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-gray-500">Challenge Fee</div>
                        <div id="popupChallengeFee" class="font-bold text-red-600"></div>
                    </div>
                    <div>
                        <div class="text-gray-500">Potential Earnings</div>
                        <div id="popupEarnings" class="font-bold text-green-600"></div>
                    </div>
                </div>
                <div class="border-t mt-3 pt-3">
                    <div class="text-gray-500">Net Result</div>
                    <div id="popupNetResult" class="text-lg font-bold"></div>
                </div>
            </div>

            <div class="flex gap-3">
                <button id="popupApproveBtn"
                    class="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transform transition-all duration-200 hover:scale-105 flex items-center justify-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    Approve Reward
                </button>
                <button id="popupCloseBtn"
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Later
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Show processing popup and handle match result processing
    function showProcessingPopup() {
        // Show processing popup
        document.getElementById('processingPopup').classList.remove('hidden');
        lucide.createIcons();

        // Get live match results
        const userGoals = parseInt($('#userScore').text());
        const opponentGoals = parseInt($('#opponentScore').text());

        // Send AJAX request to process match result
        fetch('api/update_match_result_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                userGoals: userGoals,
                opponentGoals: opponentGoals
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update financial summary with processed results
                    updateFinancialSummary();

                    // Update status to processed (shows approve button)
                    updateMatchStatus('processed');

                    // Hide processing popup
                    document.getElementById('processingPopup').classList.add('hidden');

                    // Show result popup after a brief delay
                    setTimeout(() => {
                        showMatchResultPopup();
                    }, 500);
                } else {
                    // Handle error
                    document.getElementById('processingPopup').classList.add('hidden');
                    Swal.fire({
                        icon: 'error',
                        title: 'Processing Error',
                        text: data.message || 'Failed to process match result',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error processing match result:', error);
                document.getElementById('processingPopup').classList.add('hidden');
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to process match result. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Show result popup when match ends
    function showMatchResultPopup() {
        // Only show popup if match results have been processed
        if (matchStatus !== 'processed') {
            return;
        }

        // Get live match results from the simulation
        const userGoals = parseInt($('#userScore').text());
        const opponentGoals = parseInt($('#opponentScore').text());

        // Determine match result from live simulation
        let matchResult;
        if (userGoals > opponentGoals) {
            matchResult = 'win';
        } else if (userGoals < opponentGoals) {
            matchResult = 'loss';
        } else {
            matchResult = 'draw';
        }

        const challengeCost = <?php echo $challenge_cost; ?>;
        const opponentName = '<?php echo htmlspecialchars($opponent_data["club_name"]); ?>';

        // Calculate earnings based on live result
        const potentialReward = <?php echo $potential_reward; ?>;
        const clubLevel = <?php echo $financial_result["club_level"]; ?>;
        const levelBonus = <?php echo calculateLevelBonus($financial_result["club_level"]); ?>;

        let earnings = 0;
        if (matchResult === 'win') {
            earnings = potentialReward + (potentialReward * levelBonus);
        } else if (matchResult === 'draw') {
            const baseEarnings = challengeCost * 0.8;
            earnings = baseEarnings + (baseEarnings * (levelBonus * 0.5));
        } else {
            // Loss - consolation bonus
            if (clubLevel >= 2) {
                const consolationRate = 0.1 + (clubLevel * 0.05);
                earnings = challengeCost * consolationRate;
            } else {
                earnings = challengeCost * 0.1;
            }
        }

        const netResult = earnings - challengeCost;

        // Set popup content based on result
        const popup = document.getElementById('matchResultPopup');
        const icon = document.getElementById('popupResultIcon');
        const iconSvg = document.getElementById('popupResultIconSvg');
        const title = document.getElementById('popupResultTitle');
        const message = document.getElementById('popupResultMessage');

        if (matchResult === 'win') {
            icon.className = 'w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center bg-green-100';
            iconSvg.setAttribute('data-lucide', 'trophy');
            iconSvg.className = 'w-8 h-8 text-green-600';
            title.textContent = 'Victory!';
            title.className = 'text-2xl font-bold mb-2 text-green-700';
            message.textContent = `Congratulations! You defeated ${opponentName} ${userGoals}-${opponentGoals}!`;
        } else if (matchResult === 'draw') {
            icon.className = 'w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center bg-yellow-100';
            iconSvg.setAttribute('data-lucide', 'equal');
            iconSvg.className = 'w-8 h-8 text-yellow-600';
            title.textContent = 'Draw!';
            title.className = 'text-2xl font-bold mb-2 text-yellow-700';
            message.textContent = `You drew ${userGoals}-${opponentGoals} against ${opponentName}.`;
        } else {
            icon.className = 'w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center bg-red-100';
            iconSvg.setAttribute('data-lucide', 'x-circle');
            iconSvg.className = 'w-8 h-8 text-red-600';
            title.textContent = 'Defeat';
            title.className = 'text-2xl font-bold mb-2 text-red-700';
            message.textContent = `You lost ${userGoals}-${opponentGoals} against ${opponentName}.`;
        }

        // Set financial details
        document.getElementById('popupChallengeFee').textContent = '-' + formatMarketValue(challengeCost);
        document.getElementById('popupEarnings').textContent = '+' + formatMarketValue(earnings);

        const netResultElement = document.getElementById('popupNetResult');
        netResultElement.textContent = (netResult >= 0 ? '+' : '') + formatMarketValue(netResult);
        netResultElement.className = 'text-lg font-bold ' + (netResult >= 0 ? 'text-green-600' : 'text-red-600');

        // Show popup
        popup.classList.remove('hidden');
        lucide.createIcons();
    }

    // Approve reward function
    function approveReward() {
        const approveBtn = document.getElementById('approveRewardBtn');
        const popupApproveBtn = document.getElementById('popupApproveBtn');

        // Disable buttons
        if (approveBtn) {
            approveBtn.disabled = true;
            approveBtn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Processing...';
        }
        if (popupApproveBtn) {
            popupApproveBtn.disabled = true;
            popupApproveBtn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Processing...';
        }

        fetch('api/approve_reward_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reward Approved!',
                        html: `
                            <div class="text-left">
                                <p class="mb-2"><strong>Transaction completed successfully!</strong></p>
                                <p class="mb-1">Net Result: <span class="font-bold ${data.net_result.startsWith('+') ? 'text-green-600' : 'text-red-600'}">${data.net_result}</span></p>
                                <p>New Budget: <span class="font-bold text-blue-600">${data.new_budget}</span></p>
                            </div>
                        `,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Continue'
                    }).then(() => {
                        // Redirect to clubs page
                        window.location.href = 'clubs.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#ef4444'
                    });

                    // Re-enable buttons
                    if (approveBtn) {
                        approveBtn.disabled = false;
                        approveBtn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> Approve Reward';
                    }
                    if (popupApproveBtn) {
                        popupApproveBtn.disabled = false;
                        popupApproveBtn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> Approve Reward';
                    }
                }
                lucide.createIcons();
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to process reward. Please try again.',
                    confirmButtonColor: '#ef4444'
                });

                // Re-enable buttons
                if (approveBtn) {
                    approveBtn.disabled = false;
                    approveBtn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> Approve Reward';
                }
                if (popupApproveBtn) {
                    popupApproveBtn.disabled = false;
                    popupApproveBtn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> Approve Reward';
                }
                lucide.createIcons();
            });
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function () {
        // Approve button event listeners
        const approveBtn = document.getElementById('approveRewardBtn');
        const popupApproveBtn = document.getElementById('popupApproveBtn');
        const popupCloseBtn = document.getElementById('popupCloseBtn');

        if (approveBtn) {
            approveBtn.addEventListener('click', approveReward);
        }
        if (popupApproveBtn) {
            popupApproveBtn.addEventListener('click', approveReward);
        }
        if (popupCloseBtn) {
            popupCloseBtn.addEventListener('click', function () {
                document.getElementById('matchResultPopup').classList.add('hidden');
            });
        }
    });

    // Update financial summary with live match results
    function updateFinancialSummary() {
        // Get live match results
        const userGoals = parseInt($('#userScore').text());
        const opponentGoals = parseInt($('#opponentScore').text());

        // Determine match result
        let matchResult;
        if (userGoals > opponentGoals) {
            matchResult = 'win';
        } else if (userGoals < opponentGoals) {
            matchResult = 'loss';
        } else {
            matchResult = 'draw';
        }

        const challengeCost = <?php echo $challenge_cost; ?>;
        const potentialReward = <?php echo $potential_reward; ?>;
        const clubLevel = <?php echo $financial_result["club_level"]; ?>;
        const levelBonus = <?php echo calculateLevelBonus($financial_result["club_level"]); ?>;

        // Calculate earnings based on live result
        let baseEarnings = 0;
        let bonusEarnings = 0;
        let message = '';
        let cardClass = '';
        let messageClass = '';

        if (matchResult === 'win') {
            baseEarnings = potentialReward;
            bonusEarnings = baseEarnings * levelBonus;
            message = 'Victory! You can earn ' + formatMarketValue(baseEarnings) + ' in prize money';
            if (bonusEarnings > 0) {
                message += ' + ' + formatMarketValue(bonusEarnings) + ' club level bonus!';
            } else {
                message += '!';
            }
            cardClass = 'from-green-50 to-green-100 border-green-200';
            messageClass = 'text-green-700';
        } else if (matchResult === 'draw') {
            baseEarnings = challengeCost * 0.8;
            bonusEarnings = baseEarnings * (levelBonus * 0.5);
            message = 'Draw! You can receive ' + formatMarketValue(baseEarnings) + ' (80% refund)';
            if (bonusEarnings > 0) {
                message += ' + ' + formatMarketValue(bonusEarnings) + ' level bonus.';
            } else {
                message += '.';
            }
            cardClass = 'from-yellow-50 to-yellow-100 border-yellow-200';
            messageClass = 'text-yellow-700';
        } else {
            // Loss - consolation bonus
            if (clubLevel >= 2) {
                const consolationRate = 0.1 + (clubLevel * 0.05);
                baseEarnings = challengeCost * consolationRate;
                message = 'Defeat! You lost the challenge fee but can receive ' + formatMarketValue(baseEarnings) + ' consolation bonus (' + Math.round(consolationRate * 100) + '% - Level ' + clubLevel + ').';
            } else {
                baseEarnings = challengeCost * 0.1;
                message = 'Defeat! You lost the challenge fee but can receive ' + formatMarketValue(baseEarnings) + ' consolation bonus (10% - Level ' + clubLevel + ').';
            }
            cardClass = 'from-red-50 to-red-100 border-red-200';
            messageClass = 'text-red-700';
        }

        const totalEarnings = baseEarnings + bonusEarnings;
        const netResult = totalEarnings - challengeCost;

        // Update the financial summary display
        const summaryCard = document.getElementById('financialSummaryCard');
        const messageElement = document.getElementById('financialMessage');
        const baseEarningsElement = document.getElementById('baseEarningsDisplay');
        const levelBonusElement = document.getElementById('levelBonusDisplay');
        const netResultElement = document.getElementById('netResultDisplay');

        // Update card styling
        summaryCard.className = 'bg-gradient-to-r ' + cardClass + ' border rounded-lg p-6 text-center';

        // Update message
        messageElement.textContent = message;
        messageElement.className = 'text-2xl font-bold mb-2 ' + messageClass;

        // Update financial breakdown
        baseEarningsElement.textContent = '+' + formatMarketValue(baseEarnings);
        baseEarningsElement.className = 'text-lg font-bold text-green-600';

        levelBonusElement.textContent = '+' + formatMarketValue(bonusEarnings);
        levelBonusElement.className = 'text-lg font-bold text-purple-600';

        netResultElement.textContent = (netResult >= 0 ? '+' : '') + formatMarketValue(netResult);
        netResultElement.className = 'text-lg font-bold ' + (netResult >= 0 ? 'text-green-600' : 'text-red-600');

        // Update "After approval" budget
        const currentBudget = <?php echo $user_data['budget']; ?>;
        const afterApprovalBudget = currentBudget - challengeCost + totalEarnings;
        const afterApprovalElement = document.getElementById('afterApprovalBudget');
        if (afterApprovalElement) {
            afterApprovalElement.textContent = 'After approval: ' + formatMarketValue(afterApprovalBudget);
        }
    }

    // Update session with live match result
    function updateSessionWithLiveResult() {
        const userGoals = parseInt($('#userScore').text());
        const opponentGoals = parseInt($('#opponentScore').text());

        fetch('api/update_match_result_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                userGoals: userGoals,
                opponentGoals: opponentGoals
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Session updated with live result:', data.match_result);
                } else {
                    console.error('Failed to update session:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating session:', error);
            });
    }

    // Format market value function (JavaScript version)
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + Math.round(value / 1000) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }
</script>

<?php
// End content capture and render layout
endContent('Live Match Simulation', 'match', true, false, true);
?>