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

// Require user to be logged in and have a club name
requireClubName('team');

try {
    $db = getDbConnection();

    // Get comprehensive user data
    $stmt = $db->prepare('SELECT name, email, club_name, formation, team, substitutes, budget, max_players, created_at FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $saved_formation = $user['formation'] ?? '4-4-2';
    $saved_team = $user['team'] ?? '[]';
    $saved_substitutes = $user['substitutes'] ?? '[]';
    $user_budget = $user['budget'] ?? DEFAULT_BUDGET;
    $max_players = $user['max_players'] ?? DEFAULT_MAX_PLAYERS;

    // Ensure max_players is set for existing users
    if ($max_players === null) {
        $max_players = DEFAULT_MAX_PLAYERS;
        $stmt = $db->prepare('UPDATE users SET max_players = :max_players WHERE id = :user_id');
        $stmt->bindValue(':max_players', $max_players, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    // Get ranking among all clubs
    $stmt = $db->prepare('SELECT COUNT(*) as total_clubs FROM users WHERE club_name IS NOT NULL AND club_name != ""');
    $result = $stmt->execute();
    $total_clubs = $result->fetchArray(SQLITE3_ASSOC)['total_clubs'];

    // Initialize and update player conditions (fitness and form)
    $team_data = json_decode($saved_team, true);
    $substitutes_data = json_decode($saved_substitutes, true);
    $team_updated = false;
    $subs_updated = false;

    // Initialize fitness and form for main team
    if (is_array($team_data)) {
        for ($i = 0; $i < count($team_data); $i++) {
            if ($team_data[$i]) {
                $original_player = $team_data[$i];
                $team_data[$i] = initializePlayerCondition($team_data[$i]);
                if ($team_data[$i] !== $original_player) {
                    $team_updated = true;
                }
            }
        }
    }

    // Initialize fitness and form for substitutes
    if (is_array($substitutes_data)) {
        for ($i = 0; $i < count($substitutes_data); $i++) {
            if ($substitutes_data[$i]) {
                $original_player = $substitutes_data[$i];
                $substitutes_data[$i] = initializePlayerCondition($substitutes_data[$i]);
                if ($substitutes_data[$i] !== $original_player) {
                    $subs_updated = true;
                }
            }
        }
    }

    // Update database if players were modified
    if ($team_updated || $subs_updated) {
        $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
        $stmt->bindValue(':team', json_encode($team_data), SQLITE3_TEXT);
        $stmt->bindValue(':substitutes', json_encode($substitutes_data), SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();

        // Update saved data
        $saved_team = json_encode($team_data);
        $saved_substitutes = json_encode($substitutes_data);
    }

    // Calculate team value for ranking
    $team_value = 0;
    if (is_array($team_data)) {
        foreach ($team_data as $player) {
            if ($player && isset($player['value'])) {
                $team_value += $player['value'];
            }
        }
    }

    // Get clubs with higher team value for ranking
    $stmt = $db->prepare('SELECT COUNT(*) as higher_clubs FROM users WHERE club_name IS NOT NULL AND club_name != "" AND id != :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $all_clubs = $result->fetchArray(SQLITE3_ASSOC)['higher_clubs'];

    // Count clubs with higher team value
    $higher_clubs = 0;
    $stmt = $db->prepare('SELECT team FROM users WHERE club_name IS NOT NULL AND club_name != "" AND id != :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $other_team = json_decode($row['team'], true);
        $other_value = 0;
        if (is_array($other_team)) {
            foreach ($other_team as $player) {
                if ($player && isset($player['value'])) {
                    $other_value += $player['value'];
                }
            }
        }
        if ($other_value > $team_value) {
            $higher_clubs++;
        }
    }

    $club_ranking = $higher_clubs + 1;

    // Calculate club level
    $club_level = calculateClubLevel($team_data);
    $level_name = getClubLevelName($club_level);

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Club level calculation functions
function calculateClubLevel($team)
{
    if (!is_array($team))
        return 1;

    $total_rating = 0;
    $player_count = 0;
    $total_value = 0;

    foreach ($team as $player) {
        if ($player && isset($player['rating']) && isset($player['value'])) {
            $total_rating += $player['rating'];
            $total_value += $player['value'];
            $player_count++;
        }
    }

    if ($player_count === 0)
        return 1;

    $avg_rating = $total_rating / $player_count;
    $avg_value = $total_value / $player_count;

    // Level calculation based on average rating and value
    if ($avg_rating >= 85 && $avg_value >= 50000000) {
        return 5; // Elite
    } elseif ($avg_rating >= 80 && $avg_value >= 30000000) {
        return 4; // Professional
    } elseif ($avg_rating >= 75 && $avg_value >= 15000000) {
        return 3; // Semi-Professional
    } elseif ($avg_rating >= 70 && $avg_value >= 5000000) {
        return 2; // Amateur
    } else {
        return 1; // Beginner
    }
}

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

function getLevelColor($level)
{
    switch ($level) {
        case 5:
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 4:
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 3:
            return 'bg-green-100 text-green-800 border-green-200';
        case 2:
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 1:
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <!-- League Validation Errors -->
    <?php if (isset($_GET['league_validation_failed']) && isset($_SESSION['league_validation_errors'])): ?>
        <?php
        $validation_errors = $_SESSION['league_validation_errors'];
        unset($_SESSION['league_validation_errors']); // Clear after displaying
        ?>
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600 mt-1"></i>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-red-800 mb-2">
                        Club Not Eligible for League
                    </h3>
                    <p class="text-red-700 mb-4">
                        Your club doesn't meet the minimum requirements to participate in the league.
                        Please address the following issues:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-red-700 mb-4">
                        <?php foreach ($validation_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="flex gap-3">
                        <a href="transfer.php"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            Buy Players
                        </a>
                        <button onclick="window.location.reload()"
                            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            Check Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- Club Overview Section -->
    <div class="mb-6">
        <div class="bg-white rounded-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                        <i data-lucide="shield" class="w-8 h-8 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($user['club_name']); ?>
                        </h1>
                        <p class="text-gray-600">Manager: <?php echo htmlspecialchars($user['name']); ?></p>
                        <div class="flex items-center gap-2 mt-2">
                            <span
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border <?php echo getLevelColor($club_level); ?>">
                                <i data-lucide="star" class="w-4 h-4"></i>
                                Level <?php echo $club_level; ?> - <?php echo $level_name; ?>
                            </span>
                            <span
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                <i data-lucide="trophy" class="w-4 h-4"></i>
                                Rank #
                                <?php echo $club_ranking; ?> of <?php echo $total_clubs; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-600">Club Founded</div>
                    <div class="text-lg font-bold text-gray-900">
                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        <?php echo floor((time() - strtotime($user['created_at'])) / 86400); ?> days ago
                    </div>
                </div>
            </div>

            <!-- Club Statistics Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div
                    class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4 text-center border border-green-200">
                    <div class="text-2xl font-bold text-green-700" id="clubTeamValue">
                        <?php echo formatMarketValue($team_value); ?>
                    </div>
                    <div class="text-sm text-green-600">Team Value</div>
                </div>
                <div
                    class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 text-center border border-blue-200">
                    <div class="text-2xl font-bold text-blue-700" id="clubBudget">
                        <?php echo formatMarketValue($user_budget); ?>
                    </div>
                    <div class="text-sm text-blue-600">Budget</div>
                </div>
                <div
                    class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4 text-center border border-purple-200">
                    <div class="text-2xl font-bold text-purple-700" id="clubPlayerCount">
                        <?php
                        $starting_players = count(array_filter($team_data ?: [], fn($p) => $p !== null));
                        $substitute_data = json_decode($saved_substitutes, true) ?: [];
                        $substitute_players = count(array_filter($substitute_data, fn($p) => $p !== null));
                        $total_players = $starting_players + $substitute_players;
                        echo $total_players . '/' . $max_players;
                        ?>
                    </div>
                    <div class="text-sm text-purple-600">Squad Size</div>
                </div>
                <div
                    class="bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg p-4 text-center border border-yellow-200">
                    <div class="text-2xl font-bold text-yellow-700" id="clubAvgRating">
                        <?php
                        $total_rating = 0;
                        $rated_players = 0;
                        if (is_array($team_data)) {
                            foreach ($team_data as $player) {
                                if ($player && isset($player['rating']) && $player['rating'] > 0) {
                                    $total_rating += $player['rating'];
                                    $rated_players++;
                                }
                            }
                        }
                        echo $rated_players > 0 ? round($total_rating / $rated_players, 1) : '0';
                        ?>
                    </div>
                    <div class="text-sm text-yellow-600">Avg Rating</div>
                </div>
            </div>

            <!-- Formation and Strategy Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <i data-lucide="layout" class="w-4 h-4"></i>
                        Formation
                    </h3>
                    <div class="text-lg font-bold text-gray-700"><?php echo htmlspecialchars($saved_formation); ?></div>
                    <div class="text-sm text-gray-600 mt-1">
                        <?php echo htmlspecialchars(FORMATIONS[$saved_formation]['description'] ?? 'Classic formation'); ?>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <i data-lucide="target" class="w-4 h-4"></i>
                        Challenge Status
                    </h3>
                    <?php
                    $player_count = count(array_filter($team_data ?: [], fn($p) => $p !== null));
                    $can_challenge = $player_count >= 11;
                    ?>
                    <div class="text-lg font-bold <?php echo $can_challenge ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $can_challenge ? 'Ready' : 'Not Ready'; ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <?php echo $can_challenge ? 'Can challenge other clubs' : 'Need ' . (11 - $player_count) . ' more players'; ?>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                        Level Progress
                    </h3>
                    <?php
                    $next_level = $club_level < 5 ? $club_level + 1 : 5;
                    $level_bonus = match ($club_level) {
                        5 => 25,
                        4 => 20,
                        3 => 15,
                        2 => 10,
                        default => 0
                    };
                    ?>
                    <div class="text-lg font-bold text-purple-600">+<?php echo $level_bonus; ?>% Bonus</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <?php echo $club_level < 5 ? 'Next: Level ' . $next_level : 'Maximum level reached'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Training Center Section -->
    <div class="mb-6">
        <div class="bg-white rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="dumbbell" class="w-6 h-6 text-green-600"></i>
                    Training Center
                </h2>
                <button id="trainAllBtn"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                    <i data-lucide="play" class="w-4 h-4"></i>
                    Train All Players
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-700">€2M</div>
                    <div class="text-sm text-green-600">Training Cost</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-700">+5-15</div>
                    <div class="text-sm text-blue-600">Fitness Boost</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-700">24h</div>
                    <div class="text-sm text-purple-600">Cooldown</div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">Training Benefits:</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Improves player fitness by 5-15 points</li>
                    <li>• Helps maintain player form</li>
                    <li>• Reduces injury risk for low-fitness players</li>
                    <li>• Can only be used once per day</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div>
            <!-- Formation Selector -->
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="text-xl font-bold mb-4">Formation</h2>
                <select id="formation"
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach (FORMATIONS as $key => $formation): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"
                            title="<?php echo htmlspecialchars($formation['description']); ?>">
                            <?php echo htmlspecialchars($formation['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <h2 class="text-xl font-bold mt-6 mb-2">Your Players</h2>
                <p class="text-xs text-gray-500 mb-4">Click to select • <i data-lucide="user-plus"
                        class="w-3 h-3 inline"></i> Choose • <i data-lucide="arrow-left-right"
                        class="w-3 h-3 inline"></i>
                    Switch • <i data-lucide="trash-2" class="w-3 h-3 inline"></i> Remove
                </p>
                <div id="teamValueSummary" class="mb-4 p-3 bg-gray-50 rounded-lg border">
                    <div class="flex justify-between items-center mb-2">
                        <div class="text-sm text-gray-600">Budget</div>
                        <div id="remainingBudget" class="text-sm font-bold text-blue-600">€200.0M</div>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <div class="text-sm text-gray-600">Team Value</div>
                        <div id="totalTeamValue" class="text-sm font-bold text-green-600">€0.0M</div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div id="budgetBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                            style="width: 0%"></div>
                    </div>
                    <div id="playerCount" class="text-xs text-gray-500">0/11 players selected</div>
                </div>
                <div id="playerList" class="space-y-2 max-h-80 overflow-y-auto"></div>
            </div>

            <!-- Substitutes Section -->
            <div class="bg-white rounded-lg shadow p-4 mt-4">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    Substitutes
                </h2>
                <p class="text-xs text-gray-500 mb-4">Backup players for your squad • Max
                    <?php echo $max_players - 11; ?>
                    substitutes
                </p>
                <div id="substitutesList" class="space-y-2 max-h-60 overflow-y-auto"></div>
            </div>
        </div>

        <!-- Field -->
        <div class="lg:col-span-2 bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow p-8 relative"
            style="min-height: 700px;">
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
            <div id="field" class="relative h-full"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:block"></div>
        <div class="lg:col-span-2 mt-4 flex justify-center gap-3">
            <button id="resetTeam"
                class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Reset Team
            </button>
            <button id="saveTeam"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>
                Save Team
            </button>
        </div>
    </div>
</div>

<!-- Player Selection Modal -->
<div id="playerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-xl font-bold">Select Player</h3>
            <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="mb-4">
            <label id="customPlayerLabel" class="block text-sm font-medium mb-2">Custom Player Name</label>
            <div class="flex gap-2">
                <input type="text" id="customPlayerName" placeholder="Enter custom name..."
                    class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button id="addCustomPlayer" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    Add
                </button>
            </div>
        </div>

        <div class="border-t pt-4">
            <input type="text" id="playerSearch" placeholder="Search player..."
                class="w-full px-3 py-2 border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div id="modalPlayerList" class="space-y-2 max-h-64 overflow-y-auto"></div>
        </div>
    </div>
</div>

<!-- Player Info Modal -->
<div id="playerInfoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-end mb-6">
            <button id="closePlayerInfoModal" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div id="playerInfoContent">
            <!-- Player info will be loaded here -->
        </div>
    </div>
</div>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    const players = <?php echo json_encode(getDefaultPlayers()); ?>;
    let maxBudget = <?php echo $user_budget; ?>; // User's maximum budget
    const maxPlayers = <?php echo $max_players; ?>; // Maximum squad size

    let selectedPlayerIdx = null; // Track which player is currently selected

    let savedTeam = <?php echo $saved_team; ?>;
    let selectedPlayers = Array.isArray(savedTeam) && savedTeam.length > 0 ? savedTeam : [];
    let savedSubstitutes = <?php echo $saved_substitutes; ?>;
    let substitutePlayers = Array.isArray(savedSubstitutes) && savedSubstitutes.length > 0 ? savedSubstitutes : [];
    let currentSlotIdx = null;
    let isSelectingSubstitute = false; // Track if we're selecting for substitutes
    const formations = <?php echo json_encode(FORMATIONS); ?>;

    lucide.createIcons();

    // UUID generation function
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    $('#formation').val('<?php echo $saved_formation; ?>');

    // Initialize selectedPlayers array if empty
    if (selectedPlayers.length === 0) {
        const formation = $('#formation').val();
        const positions = formations[formation].positions;
        let totalSlots = 0;
        positions.forEach(line => totalSlots += line.length);
        selectedPlayers = new Array(totalSlots).fill(null);
    }

    renderPlayers();
    renderField();
    renderSubstitutes();

    // Initialize club overview stats on page load
    let initialTotalValue = 0;
    let initialPlayerCount = 0;
    let initialTotalRating = 0;
    let initialRatedPlayers = 0;

    selectedPlayers.forEach(player => {
        if (player) {
            initialPlayerCount++;
            initialTotalValue += player.value || 0;
            if (player.rating && player.rating > 0) {
                initialTotalRating += player.rating;
                initialRatedPlayers++;
            }
        }
    });

    updateClubOverviewStats(initialTotalValue, initialPlayerCount, initialRatedPlayers > 0 ? initialTotalRating / initialRatedPlayers : 0);

    // Format market value for display
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + Math.round(value / 1000) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }

    // Get effective player rating based on fitness and form
    function getEffectiveRating(player) {
        const baseRating = player.rating || 70;
        const fitness = player.fitness || 100;
        const form = player.form || 7;

        // Fitness affects rating (0.5-1.0 multiplier)
        const fitnessMultiplier = 0.5 + (fitness / 200);

        // Form affects rating (-5 to +5 points)
        const formBonus = (form - 7) * 0.7;

        const effectiveRating = (baseRating * fitnessMultiplier) + formBonus;

        return Math.max(1, Math.min(99, Math.round(effectiveRating)));
    }

    // Get fitness status text
    function getFitnessStatusText(fitness) {
        if (fitness >= 90) return 'Excellent';
        if (fitness >= 75) return 'Good';
        if (fitness >= 60) return 'Average';
        if (fitness >= 40) return 'Poor';
        return 'Injured';
    }

    // Get fitness status color
    function getFitnessStatusColor(fitness) {
        if (fitness >= 90) return 'bg-green-100 text-green-800';
        if (fitness >= 75) return 'bg-blue-100 text-blue-800';
        if (fitness >= 60) return 'bg-yellow-100 text-yellow-800';
        if (fitness >= 40) return 'bg-orange-100 text-orange-800';
        return 'bg-red-100 text-red-800';
    }

    // Get form status text
    function getFormStatusText(form) {
        if (form >= 8.5) return 'Superb';
        if (form >= 7.5) return 'Excellent';
        if (form >= 6.5) return 'Good';
        if (form >= 5.5) return 'Average';
        if (form >= 4) return 'Poor';
        return 'Terrible';
    }

    // Get form status color
    function getFormStatusColor(form) {
        if (form >= 8.5) return 'bg-purple-100 text-purple-800';
        if (form >= 7.5) return 'bg-green-100 text-green-800';
        if (form >= 6.5) return 'bg-blue-100 text-blue-800';
        if (form >= 5.5) return 'bg-yellow-100 text-yellow-800';
        if (form >= 4) return 'bg-orange-100 text-orange-800';
        return 'bg-red-100 text-red-800';
    }

    // Get fitness progress bar color
    function getFitnessProgressColor(fitness) {
        if (fitness >= 90) return 'bg-green-500';
        if (fitness >= 75) return 'bg-blue-500';
        if (fitness >= 60) return 'bg-yellow-500';
        if (fitness >= 40) return 'bg-orange-500';
        return 'bg-red-500';
    }

    // Get form badge color
    function getFormBadgeColor(form) {
        if (form >= 8.5) return 'bg-purple-100 text-purple-800 border border-purple-200';
        if (form >= 7.5) return 'bg-green-100 text-green-800 border border-green-200';
        if (form >= 6.5) return 'bg-blue-100 text-blue-800 border border-blue-200';
        if (form >= 5.5) return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        if (form >= 4) return 'bg-orange-100 text-orange-800 border border-orange-200';
        return 'bg-red-100 text-red-800 border border-red-200';
    }

    // Get form arrow icon based on form level
    function getFormArrowIcon(form) {
        if (form >= 8) return '<i data-lucide="trending-up" class="w-3 h-3"></i>';
        if (form >= 6.5) return '<i data-lucide="arrow-up" class="w-3 h-3"></i>';
        if (form >= 5.5) return '<i data-lucide="minus" class="w-3 h-3"></i>';
        if (form >= 4) return '<i data-lucide="arrow-down" class="w-3 h-3"></i>';
        return '<i data-lucide="trending-down" class="w-3 h-3"></i>';
    }

    // Get contract status information
    function getContractStatus(player) {
        const remaining = player.contract_matches_remaining || player.contract_matches || 25;

        if (remaining <= 0) {
            return {
                text: 'Expired',
                color: 'text-red-600',
                bg: 'bg-red-100',
                border: 'border-red-200',
                urgency: 'critical'
            };
        } else if (remaining <= 3) {
            return {
                text: 'Expiring Soon',
                color: 'text-red-600',
                bg: 'bg-red-100',
                border: 'border-red-200',
                urgency: 'high'
            };
        } else if (remaining <= 8) {
            return {
                text: 'Renewal Needed',
                color: 'text-orange-600',
                bg: 'bg-orange-100',
                border: 'border-orange-200',
                urgency: 'medium'
            };
        } else if (remaining <= 15) {
            return {
                text: 'Active',
                color: 'text-yellow-600',
                bg: 'bg-yellow-100',
                border: 'border-yellow-200',
                urgency: 'low'
            };
        } else {
            return {
                text: 'Secure',
                color: 'text-green-600',
                bg: 'bg-green-100',
                border: 'border-green-200',
                urgency: 'none'
            };
        }
    }

    // Get player level display information
    function getLevelDisplayInfo(level) {
        if (level >= 40) {
            return {
                text: 'Legendary',
                color: 'text-purple-600',
                bg: 'bg-purple-100',
                border: 'border-purple-200'
            };
        } else if (level >= 30) {
            return {
                text: 'Elite',
                color: 'text-yellow-600',
                bg: 'bg-yellow-100',
                border: 'border-yellow-200'
            };
        } else if (level >= 20) {
            return {
                text: 'Expert',
                color: 'text-blue-600',
                bg: 'bg-blue-100',
                border: 'border-blue-200'
            };
        } else if (level >= 10) {
            return {
                text: 'Professional',
                color: 'text-green-600',
                bg: 'bg-green-100',
                border: 'border-green-200'
            };
        } else if (level >= 5) {
            return {
                text: 'Experienced',
                color: 'text-orange-600',
                bg: 'bg-orange-100',
                border: 'border-orange-200'
            };
        } else {
            return {
                text: 'Rookie',
                color: 'text-gray-600',
                bg: 'bg-gray-100',
                border: 'border-gray-200'
            };
        }
    }

    // Get player level status
    function getPlayerLevelStatus(player) {
        const level = player.level || 1;
        const experience = player.experience || 0;

        if (level >= 50) {
            return {
                level: level,
                experience: experience,
                experienceForNext: 0,
                progressPercentage: 100,
                isMaxLevel: true
            };
        }

        // Calculate experience requirements (matching PHP logic)
        function getExperienceForLevel(lvl) {
            return lvl * 100 + (lvl - 1) * 50;
        }

        function getTotalExperienceForLevel(targetLevel) {
            let total = 0;
            for (let i = 1; i < targetLevel; i++) {
                total += getExperienceForLevel(i + 1);
            }
            return total;
        }

        const totalRequiredCurrent = getTotalExperienceForLevel(level);
        const totalRequiredNext = getTotalExperienceForLevel(level + 1);
        const experienceForNext = totalRequiredNext - experience;
        const experienceInCurrentLevel = experience - totalRequiredCurrent;
        const experienceNeededForLevel = totalRequiredNext - totalRequiredCurrent;

        const progressPercentage = experienceNeededForLevel > 0
            ? (experienceInCurrentLevel / experienceNeededForLevel) * 100
            : 0;

        return {
            level: level,
            experience: experience,
            experienceForNext: experienceForNext,
            experienceProgress: experienceInCurrentLevel,
            experienceNeeded: experienceNeededForLevel,
            progressPercentage: Math.min(100, Math.max(0, progressPercentage)),
            isMaxLevel: false
        };
    }

    // Get card level display information
    function getCardLevelDisplayInfo(cardLevel) {
        if (cardLevel >= 10) {
            return {
                text: 'Diamond',
                color: 'text-cyan-600',
                bg: 'bg-cyan-100',
                border: 'border-cyan-200',
                icon: 'diamond'
            };
        } else if (cardLevel >= 8) {
            return {
                text: 'Platinum',
                color: 'text-purple-600',
                bg: 'bg-purple-100',
                border: 'border-purple-200',
                icon: 'star'
            };
        } else if (cardLevel >= 6) {
            return {
                text: 'Gold',
                color: 'text-yellow-600',
                bg: 'bg-yellow-100',
                border: 'border-yellow-200',
                icon: 'award'
            };
        } else if (cardLevel >= 4) {
            return {
                text: 'Silver',
                color: 'text-gray-600',
                bg: 'bg-gray-100',
                border: 'border-gray-200',
                icon: 'medal'
            };
        } else if (cardLevel >= 2) {
            return {
                text: 'Bronze',
                color: 'text-orange-600',
                bg: 'bg-orange-100',
                border: 'border-orange-200',
                icon: 'shield'
            };
        } else {
            return {
                text: 'Basic',
                color: 'text-green-600',
                bg: 'bg-green-100',
                border: 'border-green-200',
                icon: 'user'
            };
        }
    }

    // Calculate card level upgrade cost
    function getCardLevelUpgradeCost(currentLevel, playerValue) {
        const baseCost = currentLevel * 500000; // €0.5M per level
        const valueMultiplier = 1 + (playerValue / 50000000); // +1 for every €50M value
        return Math.floor(baseCost * valueMultiplier);
    }

    // Calculate player salary
    function calculatePlayerSalary(player) {
        const baseSalary = player.base_salary || Math.max(1000, (player.value || 1000000) * 0.001);
        const cardLevel = player.card_level || 1;
        const salaryMultiplier = 1 + ((cardLevel - 1) * 0.2);
        return Math.floor(baseSalary * salaryMultiplier);
    }

    // Get card level benefits
    function getCardLevelBenefits(cardLevel) {
        const ratingBonus = (cardLevel - 1) * 1.0;
        const fitnessBonus = (cardLevel - 1) * 2;
        const salaryIncrease = (cardLevel - 1) * 20;

        return {
            ratingBonus: ratingBonus,
            fitnessBonus: fitnessBonus,
            salaryIncreasePercent: salaryIncrease,
            maxFitness: Math.min(100, 100 + fitnessBonus)
        };
    }

    // Calculate card level upgrade success rate
    function getCardLevelUpgradeSuccessRate(currentLevel) {
        const baseSuccessRate = 85;
        const levelPenalty = (currentLevel - 1) * 10;
        return Math.max(30, baseSuccessRate - levelPenalty);
    }

    function renderPlayers() {
        const $list = $('#playerList').empty();
        let totalValue = 0;
        let playerCount = 0;
        let totalRating = 0;
        let ratedPlayers = 0;

        selectedPlayers.forEach((player, idx) => {
            if (player) {
                playerCount++;
                totalValue += player.value || 0;

                // Calculate ratings for average
                if (player.rating && player.rating > 0) {
                    totalRating += player.rating;
                    ratedPlayers++;
                }

                const isCustom = player.isCustom || false;
                const isSelected = selectedPlayerIdx === idx;

                // Base styling
                let bgClass = isCustom ? 'bg-purple-50 border-purple-200' : 'bg-blue-50';
                const nameClass = isCustom ? 'font-medium text-purple-700' : 'font-medium';
                const valueClass = isCustom ? 'text-sm text-purple-600 font-semibold' : 'text-sm text-green-600 font-semibold';
                const customBadge = isCustom ? '<span class="text-xs text-purple-600 bg-purple-100 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                // Selected styling
                if (isSelected) {
                    bgClass = isCustom ? 'bg-purple-100 border-purple-400' : 'bg-blue-100 border-blue-400';
                }

                $list.append(`
                        <div class="flex items-center justify-between p-2 border rounded ${bgClass} cursor-pointer transition-all duration-200 player-list-item" data-idx="${idx}">
                            <div class="flex-1" onclick="selectPlayer(${idx})">
                                <div class="${nameClass}">${player.name}${customBadge}</div>
                                <div class="${valueClass}">${formatMarketValue(player.value || 0)}</div>
                                <div class="text-xs text-gray-500 mt-1">${player.position} • ★${getEffectiveRating(player)} • Lv.${player.level || 1} • Card Lv.${player.card_level || 1} • ${(player.contract_matches_remaining || player.contract_matches || 25)} matches left</div>
                                <div class="flex gap-2 mt-1 items-center">
                                    <div class="flex-1">
                                        <div class="text-xs text-gray-500 mb-1">Fitness</div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full transition-all duration-300 ${getFitnessProgressColor(player.fitness || 100)}" style="width: ${player.fitness || 100}%"></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs px-2 py-1 rounded-full ${getFormBadgeColor(player.form || 7)} flex items-center gap-1">
                                            ${getFormArrowIcon(player.form || 7)}
                                            ${(player.form || 7).toFixed(1)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 ml-2">
                                ${!isCustom ? `<button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')})" class="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Player Info">
                                    <i data-lucide="info" class="w-4 h-4"></i>
                                </button>` : ''}
                                <button onclick="removePlayer(${idx})" class="p-1 text-red-600 hover:bg-red-100 rounded transition-colors" title="Remove Player">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    `);
            }
        });

        // Update team value and budget summary
        const remainingBudget = maxBudget - totalValue;
        const budgetUsedPercentage = (totalValue / maxBudget) * 100;

        $('#totalTeamValue').text(formatMarketValue(totalValue));
        $('#remainingBudget').text(formatMarketValue(remainingBudget));
        const totalSquadSize = playerCount + substitutePlayers.filter(p => p !== null).length;
        $('#playerCount').text(`${playerCount}/11 starting • ${totalSquadSize}/${maxPlayers} total`);

        // Update budget bar
        $('#budgetBar').css('width', Math.min(budgetUsedPercentage, 100) + '%');

        // Change budget bar color based on usage
        const $budgetBar = $('#budgetBar');
        $budgetBar.removeClass('bg-blue-600 bg-yellow-500 bg-red-600');
        if (budgetUsedPercentage >= 90) {
            $budgetBar.addClass('bg-red-600');
        } else if (budgetUsedPercentage >= 70) {
            $budgetBar.addClass('bg-yellow-500');
        } else {
            $budgetBar.addClass('bg-blue-600');
        }

        // Change remaining budget color if over budget
        const $remainingBudget = $('#remainingBudget');
        $remainingBudget.removeClass('text-blue-600 text-red-600');
        if (remainingBudget < 0) {
            $remainingBudget.addClass('text-red-600');
        } else {
            $remainingBudget.addClass('text-blue-600');
        }

        // Update club overview statistics in real-time
        const totalSquadPlayers = playerCount + substitutePlayers.filter(p => p !== null).length;
        updateClubOverviewStats(totalValue, totalSquadPlayers, ratedPlayers > 0 ? totalRating / ratedPlayers : 0);

        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-8">No players selected<br><small class="text-xs">Click on field positions to add players</small></div>');
        } else if (selectedPlayerIdx === null && playerCount > 0) {
            $list.append('<div class="text-center text-gray-400 py-2 text-xs border-t mt-2">💡 Click on a player to select and see options</div>');
        }

        lucide.createIcons();
    }

    // Render substitutes list
    function renderSubstitutes() {
        const $list = $('#substitutesList').empty();
        const maxSubstitutes = maxPlayers - 11; // Max substitutes = total squad - starting 11

        substitutePlayers.forEach((player, idx) => {
            if (player) {
                const isCustom = player.isCustom || false;
                const bgClass = isCustom ? 'bg-purple-50 border-purple-200' : 'bg-gray-50';
                const nameClass = isCustom ? 'font-medium text-purple-700' : 'font-medium';
                const valueClass = isCustom ? 'text-sm text-purple-600 font-semibold' : 'text-sm text-green-600 font-semibold';
                const customBadge = isCustom ? '<span class="text-xs text-purple-600 bg-purple-100 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                $list.append(`
                    <div class="flex items-center justify-between p-2 border rounded ${bgClass}">
                        <div class="flex-1">
                            <div class="${nameClass}">${player.name}${customBadge}</div>
                            <div class="${valueClass}">${formatMarketValue(player.value || 0)}</div>
                            <div class="text-xs text-gray-500 mt-1">${player.position} • ★${getEffectiveRating(player)} • Lv.${player.level || 1} • Card Lv.${player.card_level || 1} • ${(player.contract_matches_remaining || player.contract_matches || 25)} matches left</div>
                            <div class="flex gap-2 mt-1 items-center">
                                <div class="flex-1">
                                    <div class="text-xs text-gray-500 mb-1">Fitness</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full transition-all duration-300 ${getFitnessProgressColor(player.fitness || 100)}" style="width: ${player.fitness || 100}%"></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs px-2 py-1 rounded-full ${getFormBadgeColor(player.form || 7)} flex items-center gap-1">
                                        ${getFormArrowIcon(player.form || 7)}
                                        ${(player.form || 7).toFixed(1)}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 ml-2">
                            ${!isCustom ? `<button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')})" class="p-1 text-gray-600 hover:bg-gray-100 rounded transition-colors" title="Player Info">
                                <i data-lucide="info" class="w-4 h-4"></i>
                            </button>` : ''}
                            <button onclick="promoteSubstitute(${idx})" class="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Promote to Starting XI">
                                <i data-lucide="arrow-up" class="w-4 h-4"></i>
                            </button>
                            <button onclick="removeSubstitute(${idx})" class="p-1 text-red-600 hover:bg-red-100 rounded transition-colors" title="Remove Substitute">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                `);
            }
        });



        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-4">No substitutes selected<br><small class="text-xs">Substitutes will appear here when added</small></div>');
        }

        lucide.createIcons();
    }

    // Function to update club overview statistics in real-time
    function updateClubOverviewStats(teamValue, playerCount, avgRating) {
        // Update team value in club overview
        $('#clubTeamValue').text(formatMarketValue(teamValue));

        // Update player count in club overview (total squad size)
        $('#clubPlayerCount').text(`${playerCount}/${maxPlayers}`);

        // Update average rating in club overview
        $('#clubAvgRating').text(avgRating > 0 ? avgRating.toFixed(1) : '0');

        // Calculate and update club level
        const clubLevel = calculateClubLevelJS(selectedPlayers);
        const levelName = getClubLevelNameJS(clubLevel);
        const levelColors = getLevelColorJS(clubLevel);

        // Update level badge
        const $levelBadge = $('.inline-flex.items-center.gap-1.px-3.py-1.rounded-full.text-sm.font-medium.border').first();
        if ($levelBadge.length) {
            // Remove old color classes
            $levelBadge.removeClass('bg-purple-100 text-purple-800 border-purple-200 bg-blue-100 text-blue-800 border-blue-200 bg-green-100 text-green-800 border-green-200 bg-yellow-100 text-yellow-800 border-yellow-200 bg-gray-100 text-gray-800 border-gray-200');
            // Add new color classes
            $levelBadge.addClass(levelColors);
            // Update text
            $levelBadge.html(`<i data-lucide="star" class="w-4 h-4"></i> Level ${clubLevel} - ${levelName}`);
        }

        // Update level progress bonus
        const levelBonus = getLevelBonusJS(clubLevel);
        const $levelProgressBonus = $('.text-lg.font-bold.text-purple-600');
        if ($levelProgressBonus.length) {
            $levelProgressBonus.text(`+${levelBonus}% Bonus`);
        }

        // Update challenge status
        const canChallenge = playerCount >= 11;
        const $challengeStatus = $('.text-lg.font-bold').filter(function () {
            return $(this).text() === 'Ready' || $(this).text() === 'Not Ready';
        });

        if ($challengeStatus.length) {
            $challengeStatus.removeClass('text-green-600 text-red-600');
            $challengeStatus.addClass(canChallenge ? 'text-green-600' : 'text-red-600');
            $challengeStatus.text(canChallenge ? 'Ready' : 'Not Ready');

            // Update challenge status description
            const $challengeDesc = $challengeStatus.siblings('.text-sm.text-gray-600.mt-1');
            if ($challengeDesc.length) {
                $challengeDesc.text(canChallenge ? 'Can challenge other clubs' : `Need ${11 - playerCount} more players`);
            }
        }

        // Recreate icons after updating content
        lucide.createIcons();
    }

    // JavaScript version of club level calculation
    function calculateClubLevelJS(team) {
        if (!Array.isArray(team)) return 1;

        let totalRating = 0;
        let playerCount = 0;
        let totalValue = 0;

        team.forEach(player => {
            if (player && player.rating && player.value) {
                totalRating += player.rating;
                totalValue += player.value;
                playerCount++;
            }
        });

        if (playerCount === 0) return 1;

        const avgRating = totalRating / playerCount;
        const avgValue = totalValue / playerCount;

        // Level calculation based on average rating and value
        if (avgRating >= 85 && avgValue >= 50000000) {
            return 5; // Elite
        } else if (avgRating >= 80 && avgValue >= 30000000) {
            return 4; // Professional
        } else if (avgRating >= 75 && avgValue >= 15000000) {
            return 3; // Semi-Professional
        } else if (avgRating >= 70 && avgValue >= 5000000) {
            return 2; // Amateur
        } else {
            return 1; // Beginner
        }
    }

    // JavaScript version of club level name
    function getClubLevelNameJS(level) {
        switch (level) {
            case 5: return 'Elite';
            case 4: return 'Professional';
            case 3: return 'Semi-Professional';
            case 2: return 'Amateur';
            case 1:
            default: return 'Beginner';
        }
    }

    // JavaScript version of level colors
    function getLevelColorJS(level) {
        switch (level) {
            case 5: return 'bg-purple-100 text-purple-800 border-purple-200';
            case 4: return 'bg-blue-100 text-blue-800 border-blue-200';
            case 3: return 'bg-green-100 text-green-800 border-green-200';
            case 2: return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 1:
            default: return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    }

    // JavaScript version of level bonus calculation
    function getLevelBonusJS(level) {
        switch (level) {
            case 5: return 25;
            case 4: return 20;
            case 3: return 15;
            case 2: return 10;
            case 1:
            default: return 0;
        }
    }

    // Function to update club statistics after player changes
    function updateClubStats() {
        // Since renderPlayers() already handles all the summary box updates,
        // we just need to call it to refresh everything
        renderPlayers();
        renderSubstitutes();
    }



    // Remove substitute player
    function removeSubstitute(idx) {
        const player = substitutePlayers[idx];

        Swal.fire({
            title: `Remove ${player.name}?`,
            text: 'This will remove the substitute from your squad',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Remove Player',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                substitutePlayers[idx] = null;
                renderSubstitutes();
                updateClubStats();

                // Auto-save the changes to database
                $.post('save_team.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers)
                }, function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Substitute Removed',
                            text: `${player.name} has been removed from your substitutes`,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        // If save failed, revert the change
                        substitutePlayers[idx] = player;
                        renderSubstitutes();
                        updateClubStats();

                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Remove Substitute',
                            text: response.message || 'Could not save changes. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function () {
                    // If request failed, revert the change
                    substitutePlayers[idx] = player;
                    renderSubstitutes();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not save changes. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    }

    // Promote substitute to starting XI
    function promoteSubstitute(subIdx) {
        const substitute = substitutePlayers[subIdx];

        // Find empty slot in starting XI or ask user to replace
        const emptyStartingSlot = selectedPlayers.findIndex(p => p === null);

        if (emptyStartingSlot !== -1) {
            // Move to empty starting slot
            selectedPlayers[emptyStartingSlot] = substitute;
            substitutePlayers[subIdx] = null;

            renderPlayers();
            renderField();
            renderSubstitutes();
            updateClubStats();

            // Auto-save the changes to database
            $.post('save_team.php', {
                formation: $('#formation').val(),
                team: JSON.stringify(selectedPlayers),
                substitutes: JSON.stringify(substitutePlayers)
            }, function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Player Promoted!',
                        text: `${substitute.name} has been promoted to the starting XI`,
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    // If save failed, revert the change
                    selectedPlayers[emptyStartingSlot] = null;
                    substitutePlayers[subIdx] = substitute;
                    renderPlayers();
                    renderField();
                    renderSubstitutes();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Promote Player',
                        text: response.message || 'Could not save changes. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            }, 'json').fail(function () {
                // If request failed, revert the change
                selectedPlayers[emptyStartingSlot] = null;
                substitutePlayers[subIdx] = substitute;
                renderPlayers();
                renderField();
                renderSubstitutes();
                updateClubStats();

                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Could not save changes. Please check your connection and try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
        } else {
            // Ask user which starting player to replace
            Swal.fire({
                title: 'Replace Starting Player?',
                text: 'Starting XI is full. Which player would you like to replace?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Choose Player to Replace',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show starting players for selection
                    showStartingPlayersForReplacement(subIdx);
                }
            });
        }
    }

    // Show starting players for replacement
    function showStartingPlayersForReplacement(subIdx) {
        const substitute = substitutePlayers[subIdx];
        let playersHtml = '';

        selectedPlayers.forEach((player, idx) => {
            if (player) {
                const position = getPositionForSlot(idx);
                playersHtml += `
                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50 cursor-pointer" onclick="replaceStartingPlayer(${idx}, ${subIdx})">
                        <div>
                            <div class="font-medium">${player.name}</div>
                            <div class="text-sm text-gray-600">${position} • ★${player.rating || 'N/A'}</div>
                        </div>
                        <div class="text-sm text-green-600 font-semibold">${formatMarketValue(player.value || 0)}</div>
                    </div>
                `;
            }
        });

        Swal.fire({
            title: `Promote ${substitute.name}`,
            html: `
                <div class="text-left">
                    <p class="mb-4 text-gray-600">Select a starting player to replace:</p>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        ${playersHtml}
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            }
        });
    }

    // Replace starting player with substitute
    function replaceStartingPlayer(startingIdx, subIdx) {
        const startingPlayer = selectedPlayers[startingIdx];
        const substitute = substitutePlayers[subIdx];

        // Swap players
        selectedPlayers[startingIdx] = substitute;
        substitutePlayers[subIdx] = startingPlayer;

        renderPlayers();
        renderField();
        renderSubstitutes();
        updateClubStats();

        Swal.close();

        // Auto-save the changes to database
        $.post('save_team.php', {
            formation: $('#formation').val(),
            team: JSON.stringify(selectedPlayers),
            substitutes: JSON.stringify(substitutePlayers)
        }, function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Players Swapped!',
                    text: `${substitute.name} promoted to starting XI, ${startingPlayer.name} moved to substitutes`,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                // If save failed, revert the change
                selectedPlayers[startingIdx] = startingPlayer;
                substitutePlayers[subIdx] = substitute;
                renderPlayers();
                renderField();
                renderSubstitutes();
                updateClubStats();

                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Swap Players',
                    text: response.message || 'Could not save changes. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        }, 'json').fail(function () {
            // If request failed, revert the change
            selectedPlayers[startingIdx] = startingPlayer;
            substitutePlayers[subIdx] = substitute;
            renderPlayers();
            renderField();
            renderSubstitutes();
            updateClubStats();

            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not save changes. Please check your connection and try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // Function to select a player (highlight only)
    function selectPlayer(idx) {
        selectedPlayerIdx = selectedPlayerIdx === idx ? null : idx; // Toggle selection
        renderPlayers();
        renderField(); // Update field to show selection
    }

    // Function to choose a player (open modal to select any player)
    function choosePlayer(idx) {
        currentSlotIdx = idx;
        openPlayerModal();
    }

    // Function to switch player with currently selected player
    function switchPlayer(idx) {
        if (selectedPlayerIdx === null) {
            // No player selected, just open modal to choose
            choosePlayer(idx);
            return;
        }

        if (selectedPlayerIdx === idx) {
            // Clicking on the same selected player, open modal to change
            choosePlayer(idx);
            return;
        }

        // Switch positions between selected player and clicked player
        const selectedPlayer = selectedPlayers[selectedPlayerIdx];
        const clickedPlayer = selectedPlayers[idx];

        // Swap the players
        selectedPlayers[selectedPlayerIdx] = clickedPlayer;
        selectedPlayers[idx] = selectedPlayer;

        // Clear selection after switch
        selectedPlayerIdx = null;

        // Update display
        renderPlayers();
        renderField();
        updateClubStats();

        // Show confirmation
        Swal.fire({
            icon: 'success',
            title: 'Players Switched!',
            text: `${selectedPlayer ? selectedPlayer.name : 'Empty position'} and ${clickedPlayer ? clickedPlayer.name : 'Empty position'} have been switched`,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    // Function to remove a player
    function removePlayer(idx) {
        const player = selectedPlayers[idx];

        Swal.fire({
            title: `Remove ${player.name}?`,
            text: 'This will remove the player from your team',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Remove Player',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                selectedPlayers[idx] = null;
                if (selectedPlayerIdx === idx) {
                    selectedPlayerIdx = null; // Clear selection if removed player was selected
                }
                renderPlayers();
                renderField();
                updateClubStats();

                // Auto-save the changes to database
                $.post('save_team.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers)
                }, function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Player Removed',
                            text: `${player.name} has been removed from your team`,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        // If save failed, revert the change
                        selectedPlayers[idx] = player;
                        renderPlayers();
                        renderField();
                        updateClubStats();

                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Remove Player',
                            text: response.message || 'Could not save changes. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function () {
                    // If request failed, revert the change
                    selectedPlayers[idx] = player;
                    renderPlayers();
                    renderField();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not save changes. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    }

    function getPositionForSlot(slotIdx) {
        const formation = $('#formation').val();
        const formationData = formations[formation];
        const roles = formationData.roles;

        // Now roles array directly maps to slot indices
        if (slotIdx >= 0 && slotIdx < roles.length) {
            return roles[slotIdx];
        }
        return 'GK';
    }

    function getPositionColors(position) {
        const colorMap = {
            // Goalkeeper - Yellow/Orange
            'GK': {
                bg: 'bg-amber-400',
                border: 'border-amber-500',
                text: 'text-amber-800',
                emptyBg: 'bg-amber-400 bg-opacity-30',
                emptyBorder: 'border-amber-400'
            },
            // Defenders - Green
            'CB': {
                bg: 'bg-emerald-400',
                border: 'border-emerald-500',
                text: 'text-emerald-800',
                emptyBg: 'bg-emerald-400 bg-opacity-30',
                emptyBorder: 'border-emerald-400'
            },
            'LB': {
                bg: 'bg-emerald-400',
                border: 'border-emerald-500',
                text: 'text-emerald-800',
                emptyBg: 'bg-emerald-400 bg-opacity-30',
                emptyBorder: 'border-emerald-400'
            },
            'RB': {
                bg: 'bg-emerald-400',
                border: 'border-emerald-500',
                text: 'text-emerald-800',
                emptyBg: 'bg-emerald-400 bg-opacity-30',
                emptyBorder: 'border-emerald-400'
            },
            'LWB': {
                bg: 'bg-emerald-400',
                border: 'border-emerald-500',
                text: 'text-emerald-800',
                emptyBg: 'bg-emerald-400 bg-opacity-30',
                emptyBorder: 'border-emerald-400'
            },
            'RWB': {
                bg: 'bg-emerald-400',
                border: 'border-emerald-500',
                text: 'text-emerald-800',
                emptyBg: 'bg-emerald-400 bg-opacity-30',
                emptyBorder: 'border-emerald-400'
            },
            // Midfielders - Blue
            'CDM': {
                bg: 'bg-blue-400',
                border: 'border-blue-500',
                text: 'text-blue-800',
                emptyBg: 'bg-blue-400 bg-opacity-30',
                emptyBorder: 'border-blue-400'
            },
            'CM': {
                bg: 'bg-blue-400',
                border: 'border-blue-500',
                text: 'text-blue-800',
                emptyBg: 'bg-blue-400 bg-opacity-30',
                emptyBorder: 'border-blue-400'
            },
            'CAM': {
                bg: 'bg-blue-400',
                border: 'border-blue-500',
                text: 'text-blue-800',
                emptyBg: 'bg-blue-400 bg-opacity-30',
                emptyBorder: 'border-blue-400'
            },
            'LM': {
                bg: 'bg-blue-400',
                border: 'border-blue-500',
                text: 'text-blue-800',
                emptyBg: 'bg-blue-400 bg-opacity-30',
                emptyBorder: 'border-blue-400'
            },
            'RM': {
                bg: 'bg-blue-400',
                border: 'border-blue-500',
                text: 'text-blue-800',
                emptyBg: 'bg-blue-400 bg-opacity-30',
                emptyBorder: 'border-blue-400'
            },
            // Forwards/Strikers - Red
            'LW': {
                bg: 'bg-red-400',
                border: 'border-red-500',
                text: 'text-red-800',
                emptyBg: 'bg-red-400 bg-opacity-30',
                emptyBorder: 'border-red-400'
            },
            'RW': {
                bg: 'bg-red-400',
                border: 'border-red-500',
                text: 'text-red-800',
                emptyBg: 'bg-red-400 bg-opacity-30',
                emptyBorder: 'border-red-400'
            },
            'ST': {
                bg: 'bg-red-400',
                border: 'border-red-500',
                text: 'text-red-800',
                emptyBg: 'bg-red-400 bg-opacity-30',
                emptyBorder: 'border-red-400'
            },
            'CF': {
                bg: 'bg-red-400',
                border: 'border-red-500',
                text: 'text-red-800',
                emptyBg: 'bg-red-400 bg-opacity-30',
                emptyBorder: 'border-red-400'
            }
        };

        return colorMap[position] || colorMap['GK'];
    }

    function renderField() {
        const formation = $('#formation').val();
        const positions = formations[formation].positions;
        const $field = $('#field').empty();

        let playerIdx = 0;
        positions.forEach((line, lineIdx) => {
            line.forEach(xPos => {
                const player = selectedPlayers[playerIdx];
                const yPos = 100 - ((lineIdx + 1) * (100 / (positions.length + 1)));
                const idx = playerIdx;

                const requiredPosition = getPositionForSlot(idx);
                const colors = getPositionColors(requiredPosition);

                if (player) {
                    const isSelected = selectedPlayerIdx === idx;

                    $field.append(`
                            <div class="absolute cursor-pointer player-slot transition-all duration-200" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="relative">
                                    <div class="w-16 h-16 bg-white rounded-full flex flex-col items-center justify-center shadow-lg border-2 ${colors.border} transition-all duration-200 player-circle ${isSelected ? 'ring-4 ring-yellow-400 ring-opacity-80' : ''}">
                                        <i data-lucide="user" class="w-5 h-5 text-gray-600"></i>
                                        <span class="text-xs font-bold text-gray-700">${requiredPosition}</span>
                                    </div>
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 whitespace-nowrap">
                                        <div class="text-white text-xs font-bold bg-black bg-opacity-70 px-2 py-1 rounded">${player.name}</div>
                                    </div>
                                    
                                    <!-- Action buttons for selected player -->
                                    ${isSelected ? `
                                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 flex gap-2 action-buttons">
                                            <button onclick="removePlayer(${idx})" class="w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Remove Player">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                            </button>
                                            <button onclick="choosePlayer(${idx})" class="w-7 h-7 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Choose Different Player">
                                                <i data-lucide="user-plus" class="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Hover switch button for non-selected players -->
                                    ${!isSelected && selectedPlayerIdx !== null ? `
                                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 hover-switch-btn opacity-0 transition-all duration-200">
                                            <button onclick="switchPlayer(${idx})" class="w-7 h-7 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Switch with Selected Player">
                                                <i data-lucide="arrow-left-right" class="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `);
                } else {
                    $field.append(`
                            <div class="absolute cursor-pointer empty-slot" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex flex-col items-center justify-center border-2 border-white border-dashed hover:border-blue-300 hover:bg-opacity-30 transition-all duration-200">
                                    <i data-lucide="plus" class="w-4 h-4 text-white"></i>
                                    <span class="text-xs font-bold text-white">${requiredPosition}</span>
                                </div>
                            </div>
                        `);
                }
                playerIdx++;
            });
        });

        lucide.createIcons();

        // Click handlers
        $('.player-slot').click(function () {
            const idx = $(this).data('idx');
            selectPlayer(idx);
        });

        $('.empty-slot').click(function () {
            const idx = $(this).data('idx');
            choosePlayer(idx);
        });

        // Hover effects
        $('.player-slot').hover(
            function () {
                const idx = $(this).data('idx');
                const isSelected = selectedPlayerIdx === idx;

                if (!isSelected) {
                    // Highlight border for non-selected players
                    if (selectedPlayerIdx !== null) {
                        // If there's a selected player, show switch-ready highlight
                        $(this).find('.player-circle').addClass('ring-2 ring-blue-400 ring-opacity-70');
                        $(this).find('.hover-switch-btn').removeClass('opacity-0').addClass('opacity-100');
                    } else {
                        // No selected player, just basic hover
                        $(this).find('.player-circle').addClass('ring-2 ring-gray-300 ring-opacity-50');
                    }
                }
            },
            function () {
                const idx = $(this).data('idx');
                const isSelected = selectedPlayerIdx === idx;

                if (!isSelected) {
                    // Remove all hover effects
                    $(this).find('.player-circle').removeClass('ring-2 ring-blue-400 ring-opacity-70 ring-gray-300 ring-opacity-50');
                    $(this).find('.hover-switch-btn').removeClass('opacity-100').addClass('opacity-0');
                }
            }
        );

        // Right-click context menu for quick removal
        $('.player-slot').on('contextmenu', function (e) {
            e.preventDefault();
            const idx = $(this).data('idx');
            removePlayer(idx);
        });
    }

    function openPlayerModal() {
        let requiredPosition = '';
        let modalTitle = '';

        if (isSelectingSubstitute) {
            modalTitle = 'Select Substitute Player';
            requiredPosition = 'Any Position';
        } else {
            requiredPosition = getPositionForSlot(currentSlotIdx);
            modalTitle = `Select ${requiredPosition} Player`;
        }

        // Calculate current team value (excluding the slot we're replacing)
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && (!isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        // Add substitute values
        substitutePlayers.forEach((p, idx) => {
            if (p && (isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        const remainingBudget = maxBudget - currentTeamValue;

        $('#modalTitle').html(`${modalTitle} <span class="text-sm font-normal text-blue-600">(Budget: ${formatMarketValue(remainingBudget)})</span>`);
        $('#customPlayerLabel').text(`Custom ${requiredPosition} Player Name`);
        $('#customPlayerName').attr('placeholder', `Enter custom ${requiredPosition} name...`);
        $('#playerModal').removeClass('hidden');
        $('#customPlayerName').val('');
        $('#playerSearch').val('');
        renderModalPlayers('');
        lucide.createIcons();
    }

    function renderModalPlayers(search) {
        const $list = $('#modalPlayerList').empty();
        const searchLower = search.toLowerCase();
        let requiredPosition = '';

        if (isSelectingSubstitute) {
            requiredPosition = ''; // Any position for substitutes
        } else {
            requiredPosition = getPositionForSlot(currentSlotIdx);
        }

        // Calculate current team value (excluding the slot we're replacing)
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && (!isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        // Add substitute values
        substitutePlayers.forEach((p, idx) => {
            if (p && (isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        // Show system players
        players.forEach((player, idx) => {
            const isSelectedInStarting = selectedPlayers.some(p => p && p.name === player.name);
            const isSelectedInSubs = substitutePlayers.some(p => p && p.name === player.name);
            const isSelected = isSelectedInStarting || isSelectedInSubs;

            const matchesPosition = isSelectingSubstitute ? true : (player.position === requiredPosition);
            const matchesSearch = player.name.toLowerCase().includes(searchLower);
            const wouldExceedBudget = (currentTeamValue + (player.value || 0)) > maxBudget;

            if (!isSelected && matchesPosition && matchesSearch) {
                const isAffordable = !wouldExceedBudget;
                const itemClass = isAffordable ? 'hover:bg-blue-50 cursor-pointer modal-player-item' : 'bg-gray-100 cursor-not-allowed opacity-60';
                const priceClass = isAffordable ? 'text-green-600' : 'text-red-600';
                const budgetWarning = wouldExceedBudget ? '<div class="text-xs text-red-500 mt-1">Exceeds budget</div>' : '';

                $list.append(`
                        <div class="flex items-center justify-between p-3 border rounded ${itemClass}" ${isAffordable ? `data-idx="${idx}"` : ''}>
                            <div class="flex-1" ${isAffordable ? `onclick="selectModalPlayer(${idx})"` : ''}>
                                <div class="font-medium">${player.name}</div>
                                <div class="text-sm ${priceClass} font-semibold">${formatMarketValue(player.value || 0)}</div>
                                ${budgetWarning}
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex flex-col items-end gap-1">
                                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span>
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-yellow-600">★</span>
                                        <span class="text-xs text-gray-600">${player.rating || 'N/A'}</span>
                                    </div>
                                </div>
                                <button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')}); event.stopPropagation();" class="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Player Info">
                                    <i data-lucide="info" class="w-3 h-3"></i>
                                </button>
                            </div>
                        </div>
                    `);
            }
        });

        // Show custom players already in team (for reference/information)
        const customPlayersInTeam = selectedPlayers.filter(p => p && p.isCustom && p.position === requiredPosition);
        if (customPlayersInTeam.length > 0 && searchLower === '') {
            if ($list.children().length > 0) {
                $list.append('<div class="border-t my-2"></div>');
            }
            $list.append('<div class="text-xs text-purple-600 font-semibold mb-2 px-2">Custom Players in Team:</div>');

            customPlayersInTeam.forEach(player => {
                $list.append(`
                        <div class="flex items-center justify-between p-3 border border-purple-200 rounded bg-purple-50 opacity-60">
                            <div class="flex-1">
                                <div class="font-medium text-purple-700">${player.name}
                                    <span class="text-xs text-purple-600 bg-purple-100 px-1 py-0.5 rounded ml-1">CUSTOM</span>
                                </div>
                                <div class="text-sm text-purple-600 font-semibold">${formatMarketValue(player.value || 0)}</div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="text-xs text-purple-500 bg-purple-100 px-2 py-1 rounded">${player.position}</span>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-yellow-600">★</span>
                                    <span class="text-xs text-purple-600">${player.rating || 'N/A'}</span>
                                </div>
                            </div>
                            <div class="ml-2 text-xs text-purple-500">Already selected</div>
                        </div>
                    `);
            });
        }

        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-4">No players available</div>');
        }

        // Handle modal player selection
        window.selectModalPlayer = function (idx) {
            if (idx !== undefined) {
                const player = players[idx];

                // Double-check budget before adding
                let currentTeamValue = 0;
                selectedPlayers.forEach((p, i) => {
                    if (p && i !== currentSlotIdx) {
                        currentTeamValue += p.value || 0;
                    }
                });

                if ((currentTeamValue + (player.value || 0)) > maxBudget) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Budget Exceeded',
                        text: `Adding ${player.name} would exceed your budget of ${formatMarketValue(maxBudget)}`,
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }

                // Show confirmation alert before buying player
                const currentPlayer = selectedPlayers[currentSlotIdx];
                const isReplacement = currentPlayer !== null;
                const requiredPosition = getPositionForSlot(currentSlotIdx);

                let confirmTitle = isReplacement ? 'Replace Player?' : 'Buy Player?';
                let confirmText = isReplacement
                    ? `Replace ${currentPlayer.name} with ${player.name} for ${formatMarketValue(player.value || 0)}?`
                    : `Buy ${player.name} (${requiredPosition}) for ${formatMarketValue(player.value || 0)}?`;

                Swal.fire({
                    title: confirmTitle,
                    html: `
                        <div class="text-left space-y-3">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-gray-900 mb-2">Player Details:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium">${player.name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Position:</span>
                                        <span class="font-medium">${requiredPosition}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Rating:</span>
                                        <span class="font-medium">${player.rating || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Cost:</span>
                                        <span class="font-medium text-red-600">${formatMarketValue(player.value || 0)}</span>
                                    </div>
                                </div>
                            </div>
                            
                            ${isReplacement ? `
                            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                <h4 class="font-semibold text-yellow-900 mb-2">Current Player:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium">${currentPlayer.name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Value:</span>
                                        <span class="font-medium text-green-600">${formatMarketValue(currentPlayer.value || 0)}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-yellow-700 mt-2">This player will be removed from your team</p>
                            </div>
                            ` : ''}
                            
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <h4 class="font-semibold text-blue-900 mb-2">Budget Impact:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Current Budget:</span>
                                        <span class="font-medium text-blue-600">${formatMarketValue(maxBudget - currentTeamValue)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">After Purchase:</span>
                                        <span class="font-medium text-green-600">${formatMarketValue(maxBudget - currentTeamValue - (player.value || 0))}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: isReplacement ? '<i data-lucide="refresh-cw" class="w-4 h-4 inline mr-1"></i> Replace Player' : '<i data-lucide="shopping-cart" class="w-4 h-4 inline mr-1"></i> Buy Player',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'swal-wide'
                    },
                    didOpen: () => {
                        lucide.createIcons();
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading message
                        Swal.fire({
                            title: 'Processing Purchase...',
                            text: 'Please wait while we complete your purchase',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Add player to team or substitutes
                        if (isSelectingSubstitute) {
                            substitutePlayers[currentSlotIdx] = player;
                        } else {
                            selectedPlayers[currentSlotIdx] = player;
                        }

                        // Save team and update budget
                        $.post('purchase_player.php', {
                            formation: $('#formation').val(),
                            team: JSON.stringify(selectedPlayers),
                            substitutes: JSON.stringify(substitutePlayers),
                            player_cost: player.value || 0,
                            player_uuid: player.uuid
                        }, function (response) {
                            if (response.success) {
                                // Update local budget variable
                                maxBudget = response.new_budget;

                                // Update budget display in club overview
                                $('#clubBudget').text(formatMarketValue(response.new_budget));

                                $('#playerModal').addClass('hidden');
                                isSelectingSubstitute = false;

                                // Update displays
                                renderPlayers();
                                renderField();
                                renderSubstitutes();
                                updateClubStats();

                                // Show success message
                                Swal.fire({
                                    icon: 'success',
                                    title: isReplacement ? 'Player Replaced!' : 'Player Purchased!',
                                    html: `
                                        <div class="text-center">
                                            <p class="mb-2">${player.name} has been added to your ${isSelectingSubstitute ? 'substitutes' : 'team'}!</p>
                                            <p class="text-sm text-gray-600">Cost: ${formatMarketValue(player.value || 0)}</p>
                                            <p class="text-sm text-blue-600">Remaining Budget: ${formatMarketValue(response.new_budget)}</p>
                                        </div>
                                    `,
                                    timer: 3000,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else {
                                // Revert the change if save failed
                                if (isSelectingSubstitute) {
                                    substitutePlayers[currentSlotIdx] = null;
                                } else {
                                    selectedPlayers[currentSlotIdx] = currentPlayer;
                                }

                                renderPlayers();
                                renderField();
                                renderSubstitutes();
                                updateClubStats();

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Purchase Failed',
                                    text: response.message || 'Failed to complete purchase. Please try again.',
                                    confirmButtonColor: '#ef4444'
                                });
                            }
                        }, 'json').fail(function () {
                            // Revert the change if request failed
                            if (isSelectingSubstitute) {
                                substitutePlayers[currentSlotIdx] = null;
                            } else {
                                selectedPlayers[currentSlotIdx] = currentPlayer;
                            }

                            renderPlayers();
                            renderField();
                            renderSubstitutes();
                            updateClubStats();

                            Swal.fire({
                                icon: 'error',
                                title: 'Connection Error',
                                text: 'Unable to complete purchase. Please check your connection and try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        });
                    }
                });
            }
        };
    }

    $('#addCustomPlayer').click(function () {
        const customName = $('#customPlayerName').val().trim();

        // Basic validation
        if (!customName) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Name',
                text: 'Please enter a player name',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Check minimum length
        if (customName.length < 2) {
            Swal.fire({
                icon: 'warning',
                title: 'Name Too Short',
                text: 'Player name must be at least 2 characters long',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Check if player name already exists in system
        const existingPlayer = players.find(p => p.name.toLowerCase() === customName.toLowerCase());
        if (existingPlayer) {
            Swal.fire({
                icon: 'warning',
                title: 'Player Already Exists',
                text: `${customName} is already available in the system. Please select from the player list instead.`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Check if custom player name is already used in current team
        const isNameUsed = selectedPlayers.some(p => p && p.name.toLowerCase() === customName.toLowerCase());
        if (isNameUsed) {
            Swal.fire({
                icon: 'warning',
                title: 'Name Already Used',
                text: `${customName} is already in your team. Please choose a different name.`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Check budget for custom player
        const customPlayerValue = 500000; // €0.5M for custom players
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && idx !== currentSlotIdx) {
                currentTeamValue += p.value || 0;
            }
        });

        if ((currentTeamValue + customPlayerValue) > maxBudget) {
            Swal.fire({
                icon: 'warning',
                title: 'Budget Exceeded',
                text: `Adding a custom player (${formatMarketValue(customPlayerValue)}) would exceed your budget of ${formatMarketValue(maxBudget)}`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        const requiredPosition = getPositionForSlot(currentSlotIdx);
        const currentPlayer = selectedPlayers[currentSlotIdx];
        const isReplacement = currentPlayer !== null;

        // Show confirmation alert before creating custom player
        let confirmTitle = isReplacement ? 'Replace with Custom Player?' : 'Create Custom Player?';
        let confirmText = isReplacement
            ? `Replace ${currentPlayer.name} with custom player ${customName}?`
            : `Create custom player ${customName} for ${formatMarketValue(customPlayerValue)}?`;

        Swal.fire({
            title: confirmTitle,
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Custom Player Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Name:</span>
                                <span class="font-medium">${customName}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Position:</span>
                                <span class="font-medium">${requiredPosition}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Rating:</span>
                                <span class="font-medium">70 (Default)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Cost:</span>
         
    <span class="font-medium text-red-600">${formatMarketValue(customPlayerValue)}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${isReplacement ? `
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-900 mb-2">Current Player:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Name:</span>
                                <span class="font-medium">${currentPlayer.name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Value:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(currentPlayer.value || 0)}</span>
                            </div>
                        </div>
                        <p class="text-xs text-yellow-700 mt-2">This player will be removed from your team</p>
                    </div>
                    ` : ''}
                    
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Budget Impact:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Budget:</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(maxBudget - currentTeamValue)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">After Creation:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(maxBudget - currentTeamValue - customPlayerValue)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                        <p class="text-sm text-purple-800">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            Custom players start with a rating of 70 and cost ${formatMarketValue(customPlayerValue)}
                        </p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="user-plus" class="w-4 h-4 inline mr-1"></i> Create Player',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const playerPosition = isSelectingSubstitute ? 'CM' : requiredPosition; // Default position for substitutes

                // Create custom player
                const customPlayer = {
                    uuid: generateUUID(),
                    name: customName,
                    position: playerPosition,
                    value: customPlayerValue,
                    rating: 70, // Default rating for custom players
                    level: 1, // Default level for new players
                    experience: 0, // Starting experience
                    isCustom: true // Flag to identify custom players
                };

                // Show loading message
                Swal.fire({
                    title: 'Creating Player...',
                    text: 'Please wait while we create your custom player',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                if (isSelectingSubstitute) {
                    substitutePlayers[currentSlotIdx] = customPlayer;
                } else {
                    selectedPlayers[currentSlotIdx] = customPlayer;
                }

                // Save team and update budget
                $.post('purchase_player.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers),
                    player_cost: customPlayerValue,
                    player_uuid: customPlayer.uuid
                }, function (response) {
                    if (response.success) {
                        // Update local budget variable
                        maxBudget = response.new_budget;

                        // Update budget display in club overview
                        $('#clubBudget').text(formatMarketValue(response.new_budget));

                        $('#playerModal').addClass('hidden');
                        isSelectingSubstitute = false;

                        renderPlayers();
                        renderField();
                        renderSubstitutes();
                        updateClubStats();

                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Custom Player Created!',
                            html: `
                                <div class="text-center">
                                    <p class="mb-2">${customName} has been added to your ${isSelectingSubstitute ? 'substitutes' : 'team'}!</p>
                                    <p class="text-sm text-gray-600">Cost: ${formatMarketValue(customPlayerValue)}</p>
                                    <p class="text-sm text-blue-600">Remaining Budget: ${formatMarketValue(response.new_budget)}</p>
                                </div>
                            `,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        // Revert the change if save failed
                        if (isSelectingSubstitute) {
                            substitutePlayers[currentSlotIdx] = null;
                        } else {
                            selectedPlayers[currentSlotIdx] = currentPlayer;
                        }

                        renderPlayers();
                        renderField();
                        renderSubstitutes();
                        updateClubStats();

                        Swal.fire({
                            icon: 'error',
                            title: 'Creation Failed',
                            text: response.message || 'Failed to create custom player. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function () {
                    // Revert the change if request failed
                    if (isSelectingSubstitute) {
                        substitutePlayers[currentSlotIdx] = null;
                    } else {
                        selectedPlayers[currentSlotIdx] = currentPlayer;
                    }

                    renderPlayers();
                    renderField();
                    renderSubstitutes();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Unable to create custom player. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    });

    $('#customPlayerName').keypress(function (e) {
        if (e.which === 13) {
            $('#addCustomPlayer').click();
        }
    });

    $('#playerSearch').on('input', function () {
        renderModalPlayers($(this).val());
    });

    $('#closeModal').click(function () {
        $('#playerModal').addClass('hidden');
        isSelectingSubstitute = false;
    });

    $('#playerModal').click(function (e) {
        if (e.target === this) {
            $(this).addClass('hidden');
            isSelectingSubstitute = false;
        }
    });



    $('#formation').change(function () {
        const formation = $('#formation').val();
        const newFormation = formations[formation];
        const newRoles = newFormation.roles;

        // Keep ALL existing players
        const existingPlayers = selectedPlayers.filter(p => p !== null);
        const newPlayers = new Array(newRoles.length).fill(null);

        // Group existing players by their exact position
        const playersByPosition = {};
        existingPlayers.forEach(player => {
            if (!playersByPosition[player.position]) {
                playersByPosition[player.position] = [];
            }
            playersByPosition[player.position].push(player);
        });

        // Assign players to new formation slots based on exact position match
        newRoles.forEach((requiredRole, slotIdx) => {
            if (playersByPosition[requiredRole] && playersByPosition[requiredRole].length > 0) {
                newPlayers[slotIdx] = playersByPosition[requiredRole].shift();
            }
        });

        // Try to fit remaining players in compatible positions
        const remainingPlayers = [];
        Object.values(playersByPosition).forEach(positionPlayers => {
            remainingPlayers.push(...positionPlayers);
        });

        // Fill empty slots with any remaining players (less strict matching)
        for (let i = 0; i < newPlayers.length && remainingPlayers.length > 0; i++) {
            if (newPlayers[i] === null) {
                newPlayers[i] = remainingPlayers.shift();
            }
        }

        selectedPlayers = newPlayers;
        renderPlayers();
        renderField();
    });

    $('#resetTeam').click(function () {
        Swal.fire({
            icon: 'warning',
            title: 'Reset Team?',
            text: 'This will reload your last saved team and discard current changes.',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Reset Team',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                location.reload();
            }
        });
    });

    $('#saveTeam').click(function () {
        const filledSlots = selectedPlayers.filter(p => p !== null).length;
        const totalSlots = selectedPlayers.length;

        if (filledSlots === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Players Selected',
                text: 'Please select at least 1 player before saving',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        let confirmTitle = `Save Team (${filledSlots}/${totalSlots} players)`;
        let confirmText = filledSlots < totalSlots
            ? 'Your team is not complete. You can continue adding players later.'
            : 'Save your complete team?';

        Swal.fire({
            icon: 'question',
            title: confirmTitle,
            text: confirmText,
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Save Team',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('save_team.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers)
                }, function (response) {
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Team Saved!',
                            text: filledSlots === totalSlots
                                ? 'Your complete team has been saved successfully!'
                                : `Team saved successfully! (${filledSlots}/${totalSlots} players selected)`,
                            confirmButtonColor: '#10b981'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Save Failed',
                            text: response.message || 'Failed to save team. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Unable to save team. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    });

    // Player Info Modal Functions
    function showPlayerInfo(playerData) {
        const player = playerData;



        // Get contract matches (initialize if not set)
        const contractMatches = player.contract_matches || Math.floor(Math.random() * 36) + 15; // 15-50 matches
        const contractRemaining = player.contract_matches_remaining || contractMatches;

        // Generate some stats (random for demo)
        const stats = generatePlayerStats(player.position, player.rating);

        const playerInfoHtml = `
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Player Header -->
                <div class="lg:col-span-2 bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg p-6">
                    <div class="flex items-center gap-6">
                        <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <i data-lucide="user" class="w-12 h-12"></i>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-3xl font-bold mb-2">${player.name}</h2>
                            <div class="flex items-center gap-4 text-blue-100">
                                <span class="bg-blue-500 px-2 py-1 rounded text-sm font-semibold">${player.position}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold">★${player.rating}</div>
                            <div class="text-blue-200 text-sm">Overall Rating</div>
                        </div>
                    </div>
                </div>

                <!-- Career Information -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="briefcase" class="w-5 h-5 text-green-600"></i>
                        Career Information
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Current Club:</span>
                            <span class="font-medium">${player.club || 'Free Agent'}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Market Value:</span>
                            <span class="font-medium text-green-600">${formatMarketValue(player.value)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Primary Position:</span>
                            <span class="font-medium">${player.position}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Contract:</span>
                            <span class="font-medium">${contractRemaining} match${contractRemaining !== 1 ? 'es' : ''} remaining</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Matches Played:</span>
                            <span class="font-medium">${player.matches_played || 0}</span>
                        </div>
                        ${contractRemaining <= 8 ? `
                        <div class="mt-3 p-3 rounded-lg border ${getContractStatus(player).bg} ${getContractStatus(player).border}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium ${getContractStatus(player).color}">
                                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                                        ${getContractStatus(player).text}
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">Contract renewal recommended</div>
                                </div>
                                <button onclick="renewContract('${player.uuid}', '${player.name}', ${contractRemaining})" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Renew
                                </button>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Player Condition -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="activity" class="w-5 h-5 text-blue-600"></i>
                        Player Condition
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Fitness:</span>
                                <span class="font-medium text-gray-700">${getFitnessStatusText(player.fitness || 100)}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="${getFitnessProgressColor(player.fitness || 100)} h-2 rounded-full transition-all duration-300" style="width: ${player.fitness || 100}%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${player.fitness || 100}/100</div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Form:</span>
                                <span class="px-3 py-1 rounded-full ${getFormBadgeColor(player.form || 7)} flex items-center gap-2 text-sm font-medium">
                                    ${getFormArrowIcon(player.form || 7)}
                                    ${(player.form || 7).toFixed(1)}/10.0
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${getFormStatusText(player.form || 7)} form level</div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Level:</span>
                                <span class="px-3 py-1 rounded-full ${getLevelDisplayInfo(player.level || 1).bg} ${getLevelDisplayInfo(player.level || 1).border} flex items-center gap-2 text-sm font-medium ${getLevelDisplayInfo(player.level || 1).color}">
                                    <i data-lucide="star" class="w-3 h-3"></i>
                                    ${player.level || 1} - ${getLevelDisplayInfo(player.level || 1).text}
                                </span>
                            </div>
                            ${(player.level || 1) < 50 ? `
                            <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300" style="width: ${getPlayerLevelStatus(player).progressPercentage}%"></div>
                            </div>
                            <div class="text-xs text-gray-500">
                                ${getPlayerLevelStatus(player).experienceProgress}/${getPlayerLevelStatus(player).experienceNeeded} XP to next level
                            </div>
                            ` : `
                            <div class="text-xs text-yellow-600 font-semibold">MAX LEVEL REACHED</div>
                            `}
                        </div>
                        <div class="pt-2 border-t border-gray-200">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Effective Rating:</span>
                                <span class="font-bold text-lg text-blue-600">★${getEffectiveRating(player)}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Base: ★${player.rating} (modified by fitness, form, level & card level)</div>
                        </div>
                    </div>
                </div>

                <!-- Card Level & Upgrades -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5 text-indigo-600"></i>
                        Card Level & Salary
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Card Level:</span>
                                <span class="px-3 py-1 rounded-full ${getCardLevelDisplayInfo(player.card_level || 1).bg} ${getCardLevelDisplayInfo(player.card_level || 1).border} flex items-center gap-2 text-sm font-medium ${getCardLevelDisplayInfo(player.card_level || 1).color}">
                                    <i data-lucide="${getCardLevelDisplayInfo(player.card_level || 1).icon}" class="w-3 h-3"></i>
                                    ${player.card_level || 1} - ${getCardLevelDisplayInfo(player.card_level || 1).text}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Weekly Salary:</span>
                                <span class="font-medium text-red-600">${formatMarketValue(calculatePlayerSalary(player))}</span>
                            </div>
                            <div class="text-xs text-gray-500">Base: ${formatMarketValue(player.base_salary || Math.max(1000, (player.value || 1000000) * 0.001))} (+${getCardLevelBenefits(player.card_level || 1).salaryIncreasePercent}% from card level)</div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <h4 class="font-semibold text-blue-900 mb-2">Card Level Benefits:</h4>
                            <div class="space-y-1 text-sm text-blue-800">
                                <div>• Rating Bonus: +${getCardLevelBenefits(player.card_level || 1).ratingBonus} points</div>
                                <div>• Max Fitness: ${getCardLevelBenefits(player.card_level || 1).maxFitness}/100</div>
                                <div>• Salary Increase: +${getCardLevelBenefits(player.card_level || 1).salaryIncreasePercent}%</div>
                            </div>
                        </div>
                        ${(player.card_level || 1) < 10 ? `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-green-900">Upgrade Available</div>
                                    <div class="text-sm text-green-700">Level ${(player.card_level || 1) + 1} - ${getCardLevelDisplayInfo((player.card_level || 1) + 1).text}</div>
                                    <div class="text-xs text-green-600 mt-1">Cost: ${formatMarketValue(getCardLevelUpgradeCost(player.card_level || 1, player.value || 1000000))}</div>
                                </div>
                                <button onclick="upgradeCardLevel('${player.uuid}', '${player.name}', ${player.card_level || 1}, ${player.value || 1000000})" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                    Upgrade
                                </button>
                            </div>
                        </div>
                        ` : `
                        <div class="bg-cyan-50 border border-cyan-200 rounded-lg p-3 text-center">
                            <div class="text-cyan-800 font-semibold">Maximum Card Level Reached!</div>
                            <div class="text-xs text-cyan-600 mt-1">This player has reached the highest card level</div>
                        </div>
                        `}
                    </div>
                </div>

                <!-- Positions & Skills -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="target" class="w-5 h-5 text-purple-600"></i>
                        Positions & Skills
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <span class="text-gray-600 text-sm">Playable Positions:</span>
                            <div class="flex flex-wrap gap-2 mt-2">
                                ${player?.playablePositions?.map(pos =>
            `<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium">${pos}</span>`
        ).join('')}
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-600 text-sm">Key Attributes:</span>
                            <div class="mt-2 space-y-2">
                                ${Object.entries(stats).map(([stat, value]) => `
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm">${stat}</span>
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: ${value}%"></div>
                                            </div>
                                            <span class="text-sm font-medium w-8">${value}</span>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="lg:col-span-2 bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-orange-600"></i>
                        Player Description
                    </h3>
                    <p class="text-gray-700 leading-relaxed">${player.description || ""}</p>
                </div>
            </div>
        `;

        $('#playerInfoContent').html(playerInfoHtml);
        $('#playerInfoModal').removeClass('hidden');
        lucide.createIcons();
    }

    // Contract renewal functionality
    window.renewContract = function (playerUuid, playerName, currentRemaining) {
        const renewalCost = Math.floor(Math.random() * 5000000) + 2000000; // €2M - €7M
        const newMatches = Math.floor(Math.random() * 21) + 20; // 20-40 new matches

        Swal.fire({
            title: `Renew Contract for ${playerName}?`,
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Contract Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Remaining:</span>
                                <span class="font-medium">${currentRemaining} matches</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">New Contract:</span>
                                <span class="font-medium text-green-600">+${newMatches} matches</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total After Renewal:</span>
                                <span class="font-medium text-blue-600">${currentRemaining + newMatches} matches</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                        <h4 class="font-semibold text-red-900 mb-2">Renewal Cost:</h4>
                        <div class="text-lg font-bold text-red-600">${formatMarketValue(renewalCost)}</div>
                        <div class="text-xs text-red-700 mt-1">This amount will be deducted from your budget</div>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="file-signature" class="w-4 h-4 inline mr-1"></i> Renew Contract',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Process contract renewal
                $.post('renew_contract.php', {
                    player_uuid: playerUuid,
                    renewal_cost: renewalCost,
                    new_matches: newMatches
                }, function (response) {
                    if (response.success) {
                        // Update local budget
                        maxBudget = response.new_budget;
                        $('#clubBudget').text(formatMarketValue(response.new_budget));

                        // Update player data
                        selectedPlayers.forEach((player, idx) => {
                            if (player && player.uuid === playerUuid) {
                                selectedPlayers[idx].contract_matches_remaining = (selectedPlayers[idx].contract_matches_remaining || 0) + newMatches;
                            }
                        });

                        substitutePlayers.forEach((player, idx) => {
                            if (player && player.uuid === playerUuid) {
                                substitutePlayers[idx].contract_matches_remaining = (substitutePlayers[idx].contract_matches_remaining || 0) + newMatches;
                            }
                        });

                        // Close modal and refresh displays
                        $('#playerInfoModal').addClass('hidden');
                        renderPlayers();
                        renderSubstitutes();

                        Swal.fire({
                            icon: 'success',
                            title: 'Contract Renewed!',
                            text: `${playerName}'s contract has been extended by ${newMatches} matches for ${formatMarketValue(renewalCost)}.`,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Renewal Failed',
                            text: response.message || 'Could not renew contract. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not process contract renewal. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    };

    // Card level upgrade functionality
    window.upgradeCardLevel = function (playerUuid, playerName, currentCardLevel, playerValue) {
        const upgradeCost = getCardLevelUpgradeCost(currentCardLevel, playerValue);
        const newCardLevel = currentCardLevel + 1;
        const cardInfo = getCardLevelDisplayInfo(newCardLevel);
        const benefits = getCardLevelBenefits(newCardLevel);
        
        // Calculate success rate
        const baseSuccessRate = 85;
        const levelPenalty = (currentCardLevel - 1) * 10;
        const successRate = Math.max(30, baseSuccessRate - levelPenalty);

        Swal.fire({
            title: `Upgrade ${playerName}'s Card Level?`,
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Upgrade Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Level:</span>
                                <span class="font-medium">${currentCardLevel} - ${getCardLevelDisplayInfo(currentCardLevel).text}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Target Level:</span>
                                <span class="font-medium text-green-600">${newCardLevel} - ${cardInfo.text}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Success Rate:</span>
                                <span class="font-medium ${successRate >= 70 ? 'text-green-600' : successRate >= 50 ? 'text-yellow-600' : 'text-red-600'}">${successRate}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Upgrade Cost:</span>
                                <span class="font-medium text-red-600">${formatMarketValue(upgradeCost)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-900 mb-2">⚠️ Important Notice:</h4>
                        <div class="space-y-1 text-sm text-yellow-800">
                            <div>• Upgrade cost is paid regardless of success or failure</div>
                            <div>• Higher card levels have lower success rates</div>
                            <div>• You can retry failed upgrades (additional cost applies)</div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <h4 class="font-semibold text-green-900 mb-2">Success Benefits:</h4>
                        <div class="space-y-1 text-sm text-green-800">
                            <div>• Rating Bonus: +${benefits.ratingBonus} points</div>
                            <div>• Max Fitness: ${benefits.maxFitness}/100</div>
                            <div>• Fitness Recovery: Improved</div>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                        <h4 class="font-semibold text-red-900 mb-2">Consequences:</h4>
                        <div class="space-y-1 text-sm text-red-800">
                            <div>• Salary Increase: +${benefits.salaryIncreasePercent}% weekly cost</div>
                            <div>• Upgrade Cost: ${formatMarketValue(upgradeCost)} (paid now)</div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Budget Impact:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Budget:</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(maxBudget)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">After Payment:</span>
                                <span class="font-medium text-orange-600">${formatMarketValue(maxBudget - upgradeCost)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="dice-6" class="w-4 h-4 inline mr-1"></i> Try Upgrade',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show processing popup with progress bar
                showUpgradeProcessing(playerUuid, playerName, currentCardLevel, successRate);
            }
        });
    };

    // Show upgrade processing with progress bar and luck animation
    function showUpgradeProcessing(playerUuid, playerName, currentCardLevel, successRate) {
        let progress = 0;
        let currentStep = 0;
        let progressInterval = null;
        
        const steps = [
            'Preparing upgrade materials...',
            'Analyzing player potential...',
            'Calculating enhancement factors...',
            'Processing upgrade...',
            'Finalizing results...'
        ];

        // Function to clean up all intervals and timeouts
        const cleanupIntervals = () => {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        };

        Swal.fire({
            title: `Upgrading ${playerName}`,
            html: `
                <div class="text-center space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-lg font-semibold text-gray-900 mb-2">Card Level ${currentCardLevel} → ${currentCardLevel + 1}</div>
                        <div class="text-sm text-gray-600">Success Rate: <span class="font-medium ${successRate >= 70 ? 'text-green-600' : successRate >= 50 ? 'text-yellow-600' : 'text-red-600'}">${successRate}%</span></div>
                    </div>
                    
                    <div class="space-y-2">
                        <div id="upgradeStep" class="text-sm text-gray-600">${steps[0]}</div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div id="upgradeProgress" class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                        </div>
                        <div id="upgradePercentage" class="text-xs text-gray-500">0%</div>
                    </div>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            customClass: {
                popup: 'swal-wide'
            },
            willClose: () => {
                // Ensure intervals are cleaned up when modal closes
                cleanupIntervals();
            }
        });

        // Add timeout as safety mechanism (max 10 seconds)
        const timeoutId = setTimeout(() => {
            cleanupIntervals();
            processUpgrade(playerUuid, playerName, currentCardLevel);
        }, 10000);

        // Animate progress
        progressInterval = setInterval(() => {
            progress += Math.random() * 15 + 5; // Random progress between 5-20%
            
            if (progress >= 100) {
                progress = 100;
                clearTimeout(timeoutId); // Clear timeout since we're completing normally
                cleanupIntervals();
                
                // Process the actual upgrade
                processUpgrade(playerUuid, playerName, currentCardLevel);
                return;
            }

            // Update progress bar (check if elements exist to prevent errors)
            const progressBar = document.getElementById('upgradeProgress');
            const progressText = document.getElementById('upgradePercentage');
            const stepText = document.getElementById('upgradeStep');
            
            if (progressBar) progressBar.style.width = progress + '%';
            if (progressText) progressText.textContent = Math.floor(progress) + '%';
            
            // Update step text
            const stepIndex = Math.min(Math.floor(progress / 20), steps.length - 1);
            if (stepIndex !== currentStep) {
                currentStep = stepIndex;
                if (stepText) stepText.textContent = steps[stepIndex];
            }
        }, 200);
    }

    // Process the actual upgrade
    function processUpgrade(playerUuid, playerName, currentCardLevel) {
        // Determine player type (team or substitute)
        let playerType = 'team';
        let playerFound = false;

        // Check main team
        selectedPlayers.forEach((player, idx) => {
            if (player && player.uuid === playerUuid) {
                playerFound = true;
                playerType = 'team';
            }
        });

        // Check substitutes if not found in main team
        if (!playerFound) {
            substitutePlayers.forEach((player, idx) => {
                if (player && player.uuid === playerUuid) {
                    playerFound = true;
                    playerType = 'substitute';
                }
            });
        }

        // If player not found, show error and return
        if (!playerFound) {
            Swal.fire({
                icon: 'error',
                title: 'Player Not Found',
                text: 'Could not find the player in your squad. Please refresh and try again.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        // Process upgrade
        $.post('upgrade_card_level.php', {
            player_uuid: playerUuid,
            player_type: playerType
        }, function (response) {
            if (response.success) {
                // Update local budget
                maxBudget = response.new_budget;
                $('#clubBudget').text(formatMarketValue(response.new_budget));

                // Update player data in local arrays
                if (playerType === 'team') {
                    selectedPlayers.forEach((player, idx) => {
                        if (player && player.uuid === playerUuid) {
                            selectedPlayers[idx] = response.updated_player;
                        }
                    });
                } else {
                    substitutePlayers.forEach((player, idx) => {
                        if (player && player.uuid === playerUuid) {
                            substitutePlayers[idx] = response.updated_player;
                        }
                    });
                }

                // Close modal and refresh displays
                $('#playerInfoModal').addClass('hidden');
                renderPlayers();
                renderSubstitutes();

                // Show result based on upgrade success/failure
                if (response.upgrade_result === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Upgrade Successful!',
                        html: `
                            <div class="text-center space-y-3">
                                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <div class="text-lg font-bold text-green-900">${response.player_name}</div>
                                    <div class="text-sm text-green-700">Card Level ${response.old_card_level} → ${response.new_card_level}</div>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Success Rate:</span>
                                            <span class="font-medium text-green-600">${response.success_rate}%</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Your Roll:</span>
                                            <span class="font-medium text-blue-600">${response.luck_roll}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Cost Paid:</span>
                                            <span class="font-medium text-red-600">${formatMarketValue(response.upgrade_cost)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                    <div class="text-sm text-blue-800">
                                        <div>✨ New Rating Bonus: +${response.benefits.ratingBonus}</div>
                                        <div>💪 Max Fitness: ${response.benefits.maxFitness}/100</div>
                                        <div>💰 Weekly Salary: ${formatMarketValue(response.new_salary)}</div>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonText: 'Awesome!',
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '💔 Upgrade Failed',
                        html: `
                            <div class="text-center space-y-3">
                                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                    <div class="text-lg font-bold text-red-900">${response.player_name}</div>
                                    <div class="text-sm text-red-700">Upgrade attempt failed</div>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Success Rate:</span>
                                            <span class="font-medium text-orange-600">${response.success_rate}%</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Your Roll:</span>
                                            <span class="font-medium text-red-600">${response.luck_roll}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Cost Paid:</span>
                                            <span class="font-medium text-red-600">${formatMarketValue(response.upgrade_cost)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                    <div class="text-sm text-yellow-800">
                                        <div>🔄 You can try again (additional cost applies)</div>
                                        <div>💡 Higher card levels have lower success rates</div>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonText: 'Try Again',
                        showCancelButton: true,
                        cancelButtonText: 'Maybe Later',
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#6b7280'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Retry the upgrade
                            upgradeCardLevel(playerUuid, playerName, currentCardLevel, response.updated_player.value);
                        }
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Upgrade Failed',
                    text: response.message || 'Could not upgrade card level. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        }, 'json').fail(function () {
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not process card level upgrade. Please check your connection and try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // Training functionality
    $('#trainAllBtn').click(function () {
        Swal.fire({
            icon: 'question',
            title: 'Train All Players?',
            html: `
                <div class="text-left">
                    <p class="mb-3">This will improve fitness for all players in your squad.</p>
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="flex justify-between mb-2">
                            <span>Training Cost:</span>
                            <span class="font-bold text-red-600">€2,000,000</span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span>Fitness Improvement:</span>
                            <span class="font-bold text-green-600">+5 to +15 per player</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cooldown:</span>
                            <span class="font-bold text-blue-600">24 hours</span>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Start Training',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Training in Progress...',
                    html: 'Improving player fitness',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send training request
                $.post('train_players.php', {
                    action: 'train_all'
                })
                    .done(function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Training Complete!',
                                html: `
                                <div class="text-left">
                                    <p class="mb-3">All players have completed training successfully!</p>
                                    <div class="bg-green-50 p-3 rounded">
                                        <div class="text-sm text-green-800">
                                            <strong>Results:</strong><br>
                                            • ${response.players_trained} players trained<br>
                                            • Average fitness improvement: +${response.avg_improvement}<br>
                                            • Cost: €${response.cost.toLocaleString()}
                                        </div>
                                    </div>
                                </div>
                            `,
                                confirmButtonColor: '#16a34a'
                            }).then(() => {
                                // Reload page to show updated player conditions
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
         icon: 'error ',
                                title: 'Training Failed ',
                                text: response.message || ' Unable to complete training session ',
                                    confirmButtonColor: '#ef4444'
                            });
            }
        })
            .fail(function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Training Failed',
                    text: 'Network error occurred during training',
                    confirmButtonColor: '#ef4444'
                });
            });
    }
        });
    });





    // Helper function to generate player stats based on position and rating
    function generatePlayerStats(position, rating) {
        const baseStats = {
            'GK': ['Diving', 'Handling', 'Kicking', 'Reflexes', 'Positioning'],
            'CB': ['Defending', 'Heading', 'Strength', 'Marking', 'Tackling'],
            'LB': ['Pace', 'Crossing', 'Defending', 'Stamina', 'Dribbling'],
            'RB': ['Pace', 'Crossing', 'Defending', 'Stamina', 'Dribbling'],
            'CDM': ['Passing', 'Tackling', 'Positioning', 'Strength', 'Vision'],
            'CM': ['Passing', 'Dribbling', 'Vision', 'Stamina', 'Shooting'],
            'CAM': ['Passing', 'Dribbling', 'Vision', 'Shooting', 'Creativity'],
            'LM': ['Pace', 'Crossing', 'Dribbling', 'Stamina', 'Passing'],
            'RM': ['Pace', 'Crossing', 'Dribbling', 'Stamina', 'Passing'],
            'LW': ['Pace', 'Dribbling', 'Crossing', 'Shooting', 'Agility'],
            'RW': ['Pace', 'Dribbling', 'Crossing', 'Shooting', 'Agility'],
            'ST': ['Shooting', 'Finishing', 'Positioning', 'Strength', 'Heading'],
            'CF': ['Shooting', 'Dribbling', 'Passing', 'Positioning', 'Creativity']
        };

        const positionStats = baseStats[position] || baseStats['CM'];
        const stats = {};

        positionStats.forEach(stat => {
            // Generate stats based on overall rating with some variation
            const variation = Math.floor(Math.random() * 10) - 5; // -5 to +5
            const statValue = Math.max(30, Math.min(99, rating + variation));
            stats[stat] = statValue;
        });

        return stats;
    }

    // Close player info modal
    $('#closePlayerInfoModal').click(function () {
        $('#playerInfoModal ').addClass('hidden ');
    });

    $('#playerInfoModal').click(function (e) {
        if (e.target === this) {
            $(this).addClass('hidden');
        }
    });

    // Make substitute functions globally available
    window.removeSubstitute = removeSubstitute;
    window.promoteSubstitute = promoteSubstitute;
    window.replaceStartingPlayer = replaceStartingPlayer;
    window.showPlayerInfo = showPlayerInfo;

</script>

<style>
    .swal-wide {
        width: 600px !important;
    }

    #playerInfoModal .max-w-2xl {
        max-width: 48rem;
    }

    .player-info-stat-bar {
        transition: width 0.3s ease;
    }

    .pla yer-info-flag {
        object-fit: cover;
        border-radius: 2px;
    }

    .pla yer-info-header {
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    }
</style>
<?php
// End content capture and render layout
endContent($_SESSION['club_name'], 'team');
?>