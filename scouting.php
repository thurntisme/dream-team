<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['club_name'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = getDbConnection();

    // Database tables are now created in install.php

    // Get user's current data including team
    $stmt = $db->prepare('SELECT budget, club_name, team, formation FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);
    $user_budget = $user_data['budget'] ?? 0;
    $club_name = $user_data['club_name'] ?? '';
    $user_team = json_decode($user_data['team'] ?? '[]', true);
    $user_formation = $user_data['formation'] ?? '4-4-2';

    // Get user's scouted players
    $stmt = $db->prepare('SELECT player_uuid, scouted_at, report_quality FROM scouting_reports WHERE user_id = :user_id ORDER BY scouted_at DESC');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $scouted_players = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $scouted_players[$row['player_uuid']] = $row;
    }

    $db->close();

} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Load players data
$players_data = [];
if (file_exists('assets/json/players.json')) {
    $players_json = file_get_contents('assets/json/players.json');
    $players_data = json_decode($players_json, true) ?? [];
}

// Function to map specific positions to general categories
function mapPositionToCategory($position)
{
    $position_mapping = getPositionMapping();
    return $position_mapping[$position] ?? 'MID'; // Default to MID if position not found
}

// Function to get recommended players for user's team
function getRecommendedPlayers($players_data, $user_team, $user_formation, $user_budget, $scouted_players)
{
    // Analyze current team composition
    $position_counts = ['GK' => 0, 'DEF' => 0, 'MID' => 0, 'FWD' => 0];
    $team_ratings = [];

    foreach ($user_team as $player_data) {
        // Handle both UUID strings and player objects
        if (is_array($player_data)) {
            // If it's a player object, use it directly
            $player = $player_data;
            $player_uuid = $player['uuid'] ?? null;
        } else {
            // If it's a UUID string, look up the player
            $player_uuid = $player_data;
            $player = isset($players_data[$player_uuid]) ? $players_data[$player_uuid] : null;
        }

        if ($player && isset($player['position']) && isset($player['rating'])) {
            $general_position = mapPositionToCategory($player['position']);
            $position_counts[$general_position]++;
            $team_ratings[] = $player['rating'];
        }
    }

    // Determine formation requirements
    $formation_requirements = getFormationRequirements();
    $required_positions = $formation_requirements[$user_formation] ?? $formation_requirements['4-4-2'];
    $avg_team_rating = !empty($team_ratings) ? array_sum($team_ratings) / count($team_ratings) : 70;

    // Score each player
    $player_scores = [];
    foreach ($players_data as $player_uuid => $player) {
        // Skip if already in team or already scouted
        $is_in_team = false;
        foreach ($user_team as $team_player) {
            if (is_array($team_player)) {
                if (isset($team_player['uuid']) && $team_player['uuid'] === $player_uuid) {
                    $is_in_team = true;
                    break;
                }
            } else {
                if ($team_player === $player_uuid) {
                    $is_in_team = true;
                    break;
                }
            }
        }

        if ($is_in_team || isset($scouted_players[$player_uuid])) {
            continue;
        }

        // Skip if too expensive (more than 50% of budget)
        if ($player['value'] > $user_budget * 0.5) {
            continue;
        }

        $score = 0;

        // Position need score (higher if we need this position)
        $general_position = mapPositionToCategory($player['position']);
        $position_need = max(0, ($required_positions[$general_position] ?? 0) - ($position_counts[$general_position] ?? 0));
        $score += $position_need * 30;

        // Rating score (prefer players slightly better than current average)
        $rating_diff = $player['rating'] - $avg_team_rating;
        if ($rating_diff > 0 && $rating_diff <= 15) {
            $score += $rating_diff * 2;
        } elseif ($rating_diff > 15) {
            $score += 30 - ($rating_diff - 15); // Diminishing returns for very high ratings
        }

        // Value efficiency score (better rating per euro)
        $value_efficiency = $player['rating'] / ($player['value'] / 1000000); // Rating per million
        $score += min(20, $value_efficiency);

        // Age factor (prefer players in prime age)
        $age = $player['age'] ?? 25;
        if ($age >= 23 && $age <= 29) {
            $score += 10;
        } elseif ($age >= 20 && $age <= 32) {
            $score += 5;
        }

        // Random factor for variety
        $score += rand(0, 10);

        $player_scores[$player_uuid] = $score;
    }

    // Sort by score and return top 12
    arsort($player_scores);
    return array_slice(array_keys($player_scores), 0, 12, true);
}

// Get recommended players
$recommended_player_ids = getRecommendedPlayers($players_data, $user_team, $user_formation, $user_budget, $scouted_players);
$recommended_players = [];
foreach ($recommended_player_ids as $player_uuid) {
    if (isset($players_data[$player_uuid])) {
        $recommended_players[$player_uuid] = $players_data[$player_uuid];
    }
}

// Scouting costs are now defined in config/constants.php
$scouting_costs = getScoutingCosts();

// Start content capture
startContent();
?>

<div class="container mx-auto p-4">
    <!-- Scouting Header -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center shadow-lg">
                        <i data-lucide="search" class="w-8 h-8 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Player Scouting</h1>
                        <p class="text-gray-600">Discover and analyze players for
                            <?php echo htmlspecialchars($club_name); ?>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-600">Your Budget</div>
                    <div class="text-2xl font-bold text-green-600"><?php echo formatMarketValue($user_budget); ?></div>
                </div>
            </div>

            <!-- Scouting Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-blue-600"><?php echo count($scouted_players); ?></div>
                    <div class="text-sm text-gray-600">Players Scouted</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900"><?php echo count($recommended_players); ?></div>
                    <div class="text-sm text-gray-600">Recommended Players</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo formatMarketValue($scouting_costs['basic']); ?>
                    </div>
                    <div class="text-sm text-gray-600">Basic Scout Cost</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo formatMarketValue($scouting_costs['premium']); ?>
                    </div>
                    <div class="text-sm text-gray-600">Premium Scout Cost</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scouting Tabs -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
            <nav class="flex">
                <button id="discoverTab"
                    class="px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50">
                    <i data-lucide="compass" class="w-4 h-4 inline mr-2"></i>
                    Discover Players
                </button>
                <button id="scoutedTab" class="px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700">
                    <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>
                    Scouting Reports (<?php echo count($scouted_players); ?>)
                </button>
            </nav>
        </div>

        <!-- Discover Players Tab -->
        <div id="discoverContent" class="p-6">
            <!-- Recommendation Info -->
            <div class="mb-6 bg-blue-50 rounded-lg p-4 border border-blue-200">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="target" class="w-5 h-5 text-blue-600"></i>
                    <h3 class="font-semibold text-blue-900">Recommended for Your Club</h3>
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-sm text-blue-700">
                        Based on your current formation (<?php echo $user_formation; ?>), team composition, and budget,
                        here are the top 12 players we recommend for <?php echo htmlspecialchars($club_name); ?>.
                    </p>
                    <button id="viewAllBtn"
                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors whitespace-nowrap">
                        View All Players
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="mb-6 bg-gray-50 rounded-lg p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Player</label>
                        <input type="text" id="playerSearch" placeholder="Player name..."
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                        <select id="positionFilter"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Positions</option>
                            <option value="GK">Goalkeeper</option>
                            <option value="DEF">Defender</option>
                            <option value="MID">Midfielder</option>
                            <option value="FWD">Forward</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Scout Status</label>
                        <select id="scoutFilter"
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Players</option>
                            <option value="scouted">Already Scouted</option>
                            <option value="unscouted">Not Scouted</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Players Grid -->
            <div id="playersGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if (empty($recommended_players)): ?>
                    <div class="col-span-full text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="users" class="w-8 h-8 text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Recommendations Available</h3>
                        <p class="text-gray-600">Complete your team setup to get personalized player recommendations.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recommended_players as $player): ?>
                        <?php
                        $player_uuid = $player['uuid'];
                        $is_scouted = isset($scouted_players[$player_uuid]);
                        $scout_quality = $is_scouted ? $scouted_players[$player_uuid]['report_quality'] : 0;
                        ?>
                        <div class="player-card bg-white border rounded-lg p-4 hover:shadow-md transition-shadow"
                            data-player-uuid="<?php echo $player_uuid; ?>" data-position="<?php echo $player['position']; ?>"
                            data-rating="<?php echo $player['rating']; ?>"
                            data-scouted="<?php echo $is_scouted ? 'true' : 'false'; ?>"
                            data-name="<?php echo strtolower($player['name']); ?>">

                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-sm text-gray-600"><?php echo $player['position']; ?></span>
                                        <span class="text-sm font-medium text-blue-600">⭐
                                            <?php echo $player['rating']; ?></span>
                                    </div>
                                </div>
                                <?php if ($is_scouted): ?>
                                    <div class="flex items-center gap-1 text-green-600">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                                        <span class="text-xs">Scouted</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($is_scouted && $scout_quality >= 2): ?>
                                <!-- Show detailed info for scouted players -->
                                <div class="space-y-2 mb-3">
                                    <div class="text-sm text-gray-600">
                                        <span class="font-medium">Value:</span> <?php echo formatMarketValue($player['value']); ?>
                                    </div>
                                    <?php if ($scout_quality >= 3): ?>
                                        <div class="text-sm text-gray-600">
                                            <span class="font-medium">Age:</span> <?php echo $player['age'] ?? 'Unknown'; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <span class="font-medium">Nationality:</span>
                                            <?php echo $player['nationality'] ?? 'Unknown'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Show limited info for unscouted players -->
                                <div class="space-y-2 mb-3">
                                    <div class="text-sm text-gray-500">
                                        <span class="font-medium">Value:</span>
                                        <?php if ($is_scouted): ?>
                                            <?php echo formatMarketValue($player['value']); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">Scout to reveal</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <span class="font-medium">Details:</span>
                                        <span class="text-gray-400">Scout to reveal</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="flex gap-2">
                                <?php if (!$is_scouted): ?>
                                    <button
                                        onclick="showScoutOptions('<?php echo $player_uuid; ?>', '<?php echo htmlspecialchars($player['name']); ?>', 0)"
                                        class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                                        <i data-lucide="search" class="w-3 h-3 inline mr-1"></i>
                                        Scout Player
                                    </button>
                                <?php else: ?>
                                    <button onclick="showPlayerInfo('<?php echo $player_uuid; ?>')"
                                        class="flex-1 px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                                        <i data-lucide="eye" class="w-3 h-3 inline mr-1"></i>
                                        View Report
                                    </button>
                                <?php endif; ?>

                                <?php if ($is_scouted && $scout_quality < 3): ?>
                                    <button
                                        onclick="showScoutOptions('<?php echo $player_uuid; ?>', '<?php echo htmlspecialchars($player['name']); ?>', <?php echo $scout_quality; ?>)"
                                        class="px-3 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 transition-colors">
                                        <i data-lucide="star" class="w-3 h-3 inline mr-1"></i>
                                        Upgrade
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scouting Reports Tab -->
        <div id="scoutedContent" class="p-6 hidden">
            <?php if (empty($scouted_players)): ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="search" class="w-8 h-8 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Scouting Reports</h3>
                    <p class="text-gray-600 mb-4">You haven't scouted any players yet. Start discovering talent!</p>
                    <button onclick="switchTab('discover')"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Discover Players
                    </button>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php
                    foreach ($scouted_players as $player_uuid => $scout_data):
                        $found_players = array_filter($players_data, function ($player) use ($player_uuid) {
                            return $player['uuid'] === $player_uuid;
                        });
                        if (count($found_players) > 0) {
                            $player = array_values($found_players)[0];
                        } else {
                            $player = null;
                        }
                        ?>
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                        </h3>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                            <?php echo $player['position']; ?>
                                        </span>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                                            ⭐ <?php echo $player['rating']; ?>
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600">Value:</span>
                                            <span
                                                class="font-medium ml-1"><?php echo formatMarketValue($player['value']); ?></span>
                                        </div>
                                        <?php if ($scout_data['report_quality'] >= 3): ?>
                                            <div>
                                                <span class="text-gray-600">Age:</span>
                                                <span class="font-medium ml-1"><?php echo $player['age'] ?? 'Unknown'; ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-600">Nationality:</span>
                                                <span
                                                    class="font-medium ml-1"><?php echo $player['nationality'] ?? 'Unknown'; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <span class="text-gray-600">Scouted:</span>
                                            <span
                                                class="font-medium ml-1"><?php echo date('M j, Y', strtotime($scout_data['scouted_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <div class="text-right text-sm">
                                        <div class="text-gray-600">Report Quality</div>
                                        <div class="font-medium">
                                            <?php
                                            $quality_names = SCOUTING_QUALITY_NAMES;
                                            echo $quality_names[$scout_data['report_quality']] ?? 'Unknown';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="showPlayerInfo('<?php echo $player_uuid; ?>')"
                                            class="px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>
                                        <?php if ($scout_data['report_quality'] < 3): ?>
                                            <button
                                                onclick="showScoutOptions('<?php echo $player_uuid; ?>', '<?php echo htmlspecialchars($player['name']); ?>', <?php echo $scout_data['report_quality']; ?>)"
                                                class="px-3 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 transition-colors">
                                                <i data-lucide="arrow-up" class="w-4 h-4"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scouting Info -->
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5"></i>
            Scouting System
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h3 class="font-semibold text-blue-900 mb-2">Basic Scout
                    (
                    <?php echo formatMarketValue($scouting_costs['basic']); ?>)
                </h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Reveals player market value</li>
                    <li>• Basic player information</li>
                    <li>• Quick and affordable</li>
                </ul>
            </div>

            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h3 class="font-semibold text-green-900 mb-2">Detailed Scout
                    (
                    <?php echo formatMarketValue($scouting_costs['detailed']); ?>)
                </h3>
                <ul class="text-sm text-green-700 space-y-1">
                    <li>• All basic information</li>
                    <li>• Player statistics</li>
                    <li>• Performance analysis</li>
                </ul>
            </div>

            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <h3 class="font-semibold text-yellow-900 mb-2">Premium Scout
                    (
                    <?php echo formatMarketValue($scouting_costs['premium']); ?>)
                </h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Complete player profile</li>
                    <li>• Age and nationality</li>
                    <li>• Detailed attributes</li>
                    <li>• Transfer recommendations</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="assets/css/scouting.css">

<script>
    // Tab switching
    function switchTab(tab) {
        const discoverTab = document.getElementById('discoverTab');
        const scoutedTab = document.getElementById('scoutedTab');
        const discoverContent = document.getElementById('discoverContent');
        const scoutedContent = document.getElementById('scoutedContent');

        // Check if all elements exist before manipulating them
        if (!discoverTab || !scoutedTab || !discoverContent || !scoutedContent) {
            return;
        }

        if (tab === 'discover') {
            discoverTab.className = 'px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50';
            scoutedTab.className = 'px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700';
            discoverContent.classList.remove('hidden');
            scoutedContent.classList.add('hidden');
        } else {
            scoutedTab.className = 'px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-600 bg-blue-50';
            discoverTab.className = 'px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700';
            scoutedContent.classList.remove('hidden');
            discoverContent.classList.add('hidden');
        }
    }

    // Event listeners for tabs
    const discoverTab = document.getElementById('discoverTab');
    const scoutedTab = document.getElementById('scoutedTab');

    if (discoverTab) discoverTab.addEventListener('click', () => switchTab('discover'));
    if (scoutedTab) scoutedTab.addEventListener('click', () => switchTab('scouted'));

    // Filter functionality
    function filterPlayers() {
        const playerSearchEl = document.getElementById('playerSearch');
        const positionFilterEl = document.getElementById('positionFilter');
        const scoutFilterEl = document.getElementById('scoutFilter');

        // Check if elements exist before accessing their values
        const searchTerm = playerSearchEl ? playerSearchEl.value.toLowerCase() : '';
        const positionFilter = positionFilterEl ? positionFilterEl.value : '';
        const scoutFilter = scoutFilterEl ? scoutFilterEl.value : '';

        const playerCards = document.querySelectorAll('.player-card');

        playerCards.forEach(card => {
            const playerName = card.dataset.name;
            const position = card.dataset.position;
            const scouted = card.dataset.scouted === 'true';

            let show = true;

            // Search filter
            if (searchTerm && !playerName.includes(searchTerm)) {
                show = false;
            }

            // Position filter
            if (positionFilter && position !== positionFilter) {
                show = false;
            }

            // Scout filter
            if (scoutFilter === 'scouted' && !scouted) {
                show = false;
            } else if (scoutFilter === 'unscouted' && scouted) {
                show = false;
            }

            card.style.display = show ? 'block' : 'none';
        });
    }

    // Add event listeners for filters
    const playerSearch = document.getElementById('playerSearch');
    const positionFilter = document.getElementById('positionFilter');
    const scoutFilter = document.getElementById('scoutFilter');

    if (playerSearch) playerSearch.addEventListener('input', filterPlayers);
    if (positionFilter) positionFilter.addEventListener('change', filterPlayers);
    if (scoutFilter) scoutFilter.addEventListener('change', filterPlayers);

    // Show scout options popup
    function showScoutOptions(playerUuid, playerName, currentQuality) {
        const costs = {
            'basic': <?php echo $scouting_costs['basic']; ?>,
            'detailed': <?php echo $scouting_costs['detailed']; ?>,
            'premium': <?php echo $scouting_costs['premium']; ?>
        };

        const userBudget = <?php echo $user_budget; ?>;

        const scoutTypes = [
            {
                type: 'basic',
                name: 'Basic Scout',
                cost: costs.basic,
                quality: 1,
                icon: 'search',
                color: 'blue',
                features: [
                    'Reveals player market value',
                    'Basic player information',
                    'Quick and affordable',
                    'Perfect for initial assessment'
                ]
            },
            {
                type: 'detailed',
                name: 'Detailed Scout',
                cost: costs.detailed,
                quality: 2,
                icon: 'file-text',
                color: 'green',
                features: [
                    'All basic information',
                    'Player statistics',
                    'Performance analysis',
                    'Detailed attributes'
                ]
            },
            {
                type: 'premium',
                name: 'Premium Scout',
                cost: costs.premium,
                quality: 3,
                icon: 'star',
                color: 'yellow',
                features: [
                    'Complete player profile',
                    'Age and nationality',
                    'Detailed attributes',
                    'Transfer recommendations',
                    'Hidden potential analysis'
                ]
            }
        ];

        // Filter available scout types based on current quality and budget
        const availableScouts = scoutTypes.filter(scout => {
            return scout.quality > currentQuality && scout.cost <= userBudget;
        });

        if (availableScouts.length === 0) {
            let message = currentQuality >= 3
                ? 'This player already has the highest quality scouting report.'
                : 'You don\'t have enough budget for any scout upgrades.';

            Swal.fire({
                icon: 'info',
                title: 'No Scout Options Available',
                text: message,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Build scout options HTML
        let scoutOptionsHtml = '';
        availableScouts.forEach(scout => {
            const canAfford = scout.cost <= userBudget;
            const isRecommended = scout.type === 'detailed' && currentQuality === 0;

            scoutOptionsHtml += `
                <div class="scout-option border-2 rounded-lg p-4 cursor-pointer transition-all hover:shadow-md ${canAfford ? 'border-gray-200 hover:border-' + scout.color + '-300' : 'border-gray-100 opacity-50'}" 
                     data-scout-type="${scout.type}" data-cost="${scout.cost}">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-${scout.color}-100 rounded-full flex items-center justify-center">
                                <i data-lucide="${scout.icon}" class="w-5 h-5 text-${scout.color}-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${scout.name} ${isRecommended ? '(Recommended)' : ''}</h4>
                                <p class="text-sm text-gray-600">Quality Level ${scout.quality}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-${scout.color}-600">${formatMarketValue(scout.cost)}</div>
                            <div class="text-xs text-gray-500">Scout Cost</div>
                        </div>
                    </div>
                    <div class="space-y-1">
                        ${scout.features.map(feature => `<div class="flex items-center gap-2 text-sm text-gray-600">
                            <i data-lucide="check" class="w-3 h-3 text-green-500"></i>
                            <span>${feature}</span>
                        </div>`).join('')}
                    </div>
                    ${!canAfford ? '<div class="mt-2 text-xs text-red-500">Insufficient budget</div>' : ''}
                </div>
            `;
        });

        Swal.fire({
            title: `🔍 Scout ${playerName}`,
            html: `
                <div class="text-left">
                    <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="info" class="w-4 h-4 text-blue-600"></i>
                            <span class="font-medium text-blue-900">Choose Your Scouting Package</span>
                        </div>
                        <p class="text-sm text-blue-700">
                            ${currentQuality === 0
                    ? 'Select a scouting package to reveal detailed information about this player.'
                    : `Current report quality: Level ${currentQuality}. You can upgrade to get more detailed information.`
                }
                        </p>
                        <div class="mt-2 text-sm text-blue-600">
                            <strong>Your Budget:</strong> ${formatMarketValue(userBudget)}
                        </div>
                    </div>
                    
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        ${scoutOptionsHtml}
                    </div>
                </div>
            `,
            icon: null,
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '🔍 Scout Player',
            cancelButtonText: 'Maybe Later',
            // width: '600px',
            // showClass: {
            //     popup: 'animate__animated animate__fadeInDown'
            // },
            // hideClass: {
            //     popup: 'animate__animated animate__fadeOutUp'
            // },
            preConfirm: () => {
                const selectedOption = document.querySelector('.scout-option.selected');
                if (!selectedOption) {
                    Swal.showValidationMessage('Please select a scouting package');
                    return false;
                }
                return {
                    scoutType: selectedOption.dataset.scoutType,
                    cost: parseInt(selectedOption.dataset.cost)
                };
            },
            didOpen: () => {
                // Add click handlers for scout options
                document.querySelectorAll('.scout-option').forEach(option => {
                    option.addEventListener('click', function () {
                        if (this.classList.contains('opacity-50')) return; // Skip if can't afford

                        // Remove previous selection
                        document.querySelectorAll('.scout-option').forEach(opt => {
                            opt.classList.remove('selected', 'border-green-500', 'bg-green-50');
                        });

                        // Add selection to clicked option
                        this.classList.add('selected', 'border-green-500', 'bg-green-50');
                    });
                });

                // Auto-select first affordable option
                const firstAffordable = document.querySelector('.scout-option:not(.opacity-50)');
                if (firstAffordable) {
                    firstAffordable.click();
                }

                // Initialize Lucide icons
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { scoutType, cost } = result.value;
                performScout(playerUuid, scoutType, cost);
            }
        });
    }

    // Scout player function
    function scoutPlayer(playerUuid, scoutType) {
        const costs = {
            'basic': <?php echo $scouting_costs['basic']; ?>,
            'detailed': <?php echo $scouting_costs['detailed']; ?>,
            'premium': <?php echo $scouting_costs['premium']; ?>
        };

        const cost = costs[scoutType];
        const userBudget = <?php echo $user_budget; ?>;

        if (cost > userBudget) {
            Swal.fire({
                icon: 'error',
                title: 'Insufficient Budget',
                text: `You need ${formatMarketValue(cost)} to scout this player.`,
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.fire({
            title: `${scoutType.charAt(0).toUpperCase() + scoutType.slice(1)} Scout`,
            html: `
            <div class="text-left space-y-4">
                <p>Scout this player for detailed information?</p>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Scout Cost:</span>
                        <span class="font-medium text-red-600">${formatMarketValue(cost)}</span>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-gray-600">Your Budget:</span>
                        <span class="font-medium text-blue-600">${formatMarketValue(userBudget)}</span>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-gray-600">Remaining:</span>
                        <span class="font-medium text-green-600">${formatMarketValue(userBudget - cost)}</span>
                    </div>
                </div>
            </div>
        `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Scout Player',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                performScout(playerUuid, scoutType, cost);
            }
        });
    }

    // Perform scout function
    function performScout(playerUuid, scoutType, cost) {
        Swal.fire({
            title: 'Scouting Player...',
            text: 'Please wait while we gather information',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('api/scout_player_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                player_uuid: playerUuid,
                scout_type: scoutType,
                cost: cost
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Player Scouted!',
                        text: `Successfully scouted player with ${scoutType} report!`,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Scouting Failed',
                        text: data.message || 'Failed to scout player. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to scout player. Please check your connection and try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Show player info function
    function showPlayerInfo(playerUuid) {
        // Get player data from PHP
        const playersData = <?php echo json_encode($players_data); ?>;
        const scoutedPlayers = <?php echo json_encode($scouted_players); ?>;

        const player = playersData.filter(p => p.uuid === playerUuid)[0];
        const scoutData = scoutedPlayers[playerUuid];

        if (!player || !scoutData) {
            Swal.fire({
                icon: 'error',
                title: 'Player Not Found',
                text: 'Unable to load player information.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        const reportQuality = scoutData.report_quality;
        const scoutedDate = new Date(scoutData.scouted_at).toLocaleDateString();

        // Quality names and colors
        const qualityInfo = {
            1: { name: 'Basic', color: 'blue', icon: 'search' },
            2: { name: 'Detailed', color: 'green', icon: 'file-text' },
            3: { name: 'Premium', color: 'yellow', icon: 'star' }
        };

        const quality = qualityInfo[reportQuality] || qualityInfo[1];

        // Build player information based on report quality
        let playerInfoHtml = `
            <div class="text-left">
                <!-- Player Header -->
                <div class="bg-gradient-to-r from-${quality.color}-50 to-${quality.color}-100 p-4 rounded-lg mb-4 border border-${quality.color}-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-${quality.color}-600 rounded-full flex items-center justify-center">
                                <i data-lucide="user" class="w-6 h-6 text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">${player.name}</h3>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 text-sm rounded-full font-medium">
                                        ${player.position}
                                    </span>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-sm rounded-full font-medium">
                                        ⭐ ${player.rating}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center gap-1 text-${quality.color}-600 mb-1">
                                <i data-lucide="${quality.icon}" class="w-4 h-4"></i>
                                <span class="text-sm font-medium">${quality.name} Report</span>
                            </div>
                            <div class="text-xs text-gray-500">Scouted: ${scoutedDate}</div>
                        </div>
                    </div>
                </div>

                <!-- Basic Information (Always Available) -->
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4"></i>
                        Basic Information
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-sm text-gray-600">Market Value</div>
                            <div class="text-lg font-bold text-green-600">${formatMarketValue(player.value)}</div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-sm text-gray-600">Overall Rating</div>
                            <div class="text-lg font-bold text-blue-600">${player.rating}</div>
                        </div>
                    </div>
                </div>
        `;

        // Detailed Information (Quality 2+)
        if (reportQuality >= 2) {
            playerInfoHtml += `
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="bar-chart" class="w-4 h-4"></i>
                        Performance Analysis
                    </h4>
                    <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Position:</span>
                                <span class="font-medium">${player.position}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Rating:</span>
                                <span class="font-medium">${player.rating}/99</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Form:</span>
                                <span class="font-medium text-green-600">Excellent</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Fitness:</span>
                                <span class="font-medium text-blue-600">100%</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Premium Information (Quality 3)
        if (reportQuality >= 3) {
            const age = player.age || (20 + Math.floor(Math.random() * 15));
            const nationality = player.nationality || 'International';

            playerInfoHtml += `
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                        Personal Details
                    </h4>
                    <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Age:</span>
                                <span class="font-medium">${age} years</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nationality:</span>
                                <span class="font-medium">${nationality}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Contract:</span>
                                <span class="font-medium">Available</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Potential:</span>
                                <span class="font-medium text-purple-600">${Math.min(99, player.rating + Math.floor(Math.random() * 10))}/99</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="target" class="w-4 h-4"></i>
                        Scout Recommendation
                    </h4>
                    <div class="bg-purple-50 p-3 rounded-lg border border-purple-200">
                        <div class="flex items-start gap-2">
                            <i data-lucide="check-circle" class="w-5 h-5 text-purple-600 mt-0.5"></i>
                            <div class="text-sm text-purple-800">
                                <p class="font-medium mb-1">Transfer Recommendation: 
                                    <span class="text-green-600">
                                        ${player.rating >= 85 ? 'Highly Recommended' : player.rating >= 75 ? 'Recommended' : 'Consider'}
                                    </span>
                                </p>
                                <p class="text-purple-700">
                                    ${player.rating >= 85
                    ? 'Exceptional player who would significantly strengthen your squad. Worth the investment.'
                    : player.rating >= 75
                        ? 'Solid player who would be a good addition to your team. Fair value for money.'
                        : 'Decent player but consider if they fit your tactical needs and budget constraints.'
                }
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Upgrade suggestion
        if (reportQuality < 3) {
            const nextQuality = reportQuality + 1;
            const nextQualityName = qualityInfo[nextQuality].name;

            playerInfoHtml += `
                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="arrow-up" class="w-4 h-4 text-blue-600"></i>
                        <span class="text-sm font-medium text-blue-900">Upgrade Available</span>
                    </div>
                    <p class="text-sm text-blue-700 mb-2">
                        Get more detailed information with a ${nextQualityName} Scout report.
                    </p>
                    <button onclick="Swal.close(); showScoutOptions('${playerUuid}', '${player.name}', ${reportQuality});" 
                            class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition-colors">
                        Upgrade Report
                    </button>
                </div>
            `;
        }

        playerInfoHtml += '</div>';

        Swal.fire({
            title: null,
            html: playerInfoHtml,
            icon: null,
            confirmButtonColor: '#3b82f6',
            confirmButtonText: 'Close Report',
            width: '600px',
            // showClass: {
            //     popup: 'animate__animated animate__fadeInDown'
            // },
            // hideClass: {
            //     popup: 'animate__animated animate__fadeOutUp'
            // },
            didOpen: () => {
                // Initialize Lucide icons
                lucide.createIcons();
            }
        });
    }

    // Format market value function
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + (value / 1000).toFixed(0) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }

    // View All Players functionality
    const viewAllBtn = document.getElementById('viewAllBtn');
    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'View All Players',
                text: 'This would show all available players instead of just recommendations. Feature coming soon!',
                icon: 'info',
                confirmButtonColor: '#2563eb'
            });
        });
    }

    // Initialize Lucide icons
    lucide.createIcons();
</script>

<?php
// End content capture and render layout
endContent('Scouting - Dream Team', 'scouting');
?>