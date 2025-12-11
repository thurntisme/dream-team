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

try {
    $db = getDbConnection();

    // Get comprehensive user data
    $stmt = $db->prepare('SELECT name, email, club_name, formation, team, budget, created_at FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $saved_formation = $user['formation'] ?? '4-4-2';
    $saved_team = $user['team'] ?? '[]';
    $user_budget = $user['budget'] ?? DEFAULT_BUDGET;

    // Get ranking among all clubs
    $stmt = $db->prepare('SELECT COUNT(*) as total_clubs FROM users WHERE club_name IS NOT NULL AND club_name != ""');
    $result = $stmt->execute();
    $total_clubs = $result->fetchArray(SQLITE3_ASSOC)['total_clubs'];

    // Calculate team value for ranking
    $team_data = json_decode($saved_team, true);
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
    <!-- Club Overview Section -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow-lg p-6">
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
                    <div class="text-2xl font-bold text-blue-700"><?php echo formatMarketValue($user_budget); ?></div>
                    <div class="text-sm text-blue-600">Budget</div>
                </div>
                <div
                    class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4 text-center border border-purple-200">
                    <div class="text-2xl font-bold text-purple-700" id="clubPlayerCount">
                        <?php echo count(array_filter($team_data ?: [], fn($p) => $p !== null)); ?>/11
                    </div>
                    <div class="text-sm text-purple-600">Players</div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
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
                    class="w-3 h-3 inline"></i> Choose • <i data-lucide="arrow-left-right" class="w-3 h-3 inline"></i>
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

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    const players = <?php echo json_encode(getDefaultPlayers()); ?>;
    const maxBudget = <?php echo $user_budget; ?>; // User's maximum budget

    let selectedPlayerIdx = null; // Track which player is currently selected

    let savedTeam = <?php echo $saved_team; ?>;
    let selectedPlayers = Array.isArray(savedTeam) && savedTeam.length > 0 ? savedTeam : [];
    let currentSlotIdx = null;
    const formations = <?php echo json_encode(FORMATIONS); ?>;

    lucide.createIcons();

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
                                <div class="text-xs text-gray-500 mt-1">${player.position} • ★${player.rating || 'N/A'}</div>
                            </div>
                            <div class="flex items-center gap-1 ml-2">
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
        $('#playerCount').text(`${playerCount}/11 players selected`);

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
        updateClubOverviewStats(totalValue, playerCount, ratedPlayers > 0 ? totalRating / ratedPlayers : 0);

        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-8">No players selected<br><small class="text-xs">Click on field positions to add players</small></div>');
        } else if (selectedPlayerIdx === null && playerCount > 0) {
            $list.append('<div class="text-center text-gray-400 py-2 text-xs border-t mt-2">💡 Click on a player to select and see options</div>');
        }

        lucide.createIcons();
    }

    // Function to update club overview statistics in real-time
    function updateClubOverviewStats(teamValue, playerCount, avgRating) {
        // Update team value in club overview
        $('#clubTeamValue').text(formatMarketValue(teamValue));

        // Update player count in club overview
        $('#clubPlayerCount').text(`${playerCount}/11`);

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

                Swal.fire({
                    icon: 'success',
                    title: 'Player Removed',
                    text: `${player.name} has been removed from your team`,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
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
        const requiredPosition = getPositionForSlot(currentSlotIdx);

        // Calculate current team value (excluding the slot we're replacing)
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && idx !== currentSlotIdx) {
                currentTeamValue += p.value || 0;
            }
        });
        const remainingBudget = maxBudget - currentTeamValue;

        $('#modalTitle').html(`Select ${requiredPosition} Player <span class="text-sm font-normal text-blue-600">(Budget: ${formatMarketValue(remainingBudget)})</span>`);
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
        const requiredPosition = getPositionForSlot(currentSlotIdx);

        // Calculate current team value (excluding the slot we're replacing)
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && idx !== currentSlotIdx) {
                currentTeamValue += p.value || 0;
            }
        });

        // Show system players
        players.forEach((player, idx) => {
            const isSelected = selectedPlayers.some(p => p && p.name === player.name);
            const matchesPosition = player.position === requiredPosition;
            const matchesSearch = player.name.toLowerCase().includes(searchLower);
            const wouldExceedBudget = (currentTeamValue + (player.value || 0)) > maxBudget;

            if (!isSelected && matchesPosition && matchesSearch) {
                const isAffordable = !wouldExceedBudget;
                const itemClass = isAffordable ? 'hover:bg-blue-50 cursor-pointer modal-player-item' : 'bg-gray-100 cursor-not-allowed opacity-60';
                const priceClass = isAffordable ? 'text-green-600' : 'text-red-600';
                const budgetWarning = wouldExceedBudget ? '<div class="text-xs text-red-500 mt-1">Exceeds budget</div>' : '';

                $list.append(`
                        <div class="flex items-center justify-between p-3 border rounded ${itemClass}" ${isAffordable ? `data-idx="${idx}"` : ''}>
                            <div class="flex-1">
                                <div class="font-medium">${player.name}</div>
                                <div class="text-sm ${priceClass} font-semibold">${formatMarketValue(player.value || 0)}</div>
                                ${budgetWarning}
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-yellow-600">★</span>
                                    <span class="text-xs text-gray-600">${player.rating || 'N/A'}</span>
                                </div>
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

        $('.modal-player-item').click(function () {
            const idx = $(this).data('idx');
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
                        // Add player to team
                        selectedPlayers[currentSlotIdx] = player;
                        $('#playerModal').addClass('hidden');

                        // Update displays
                        renderPlayers();
                        renderField();
                        updateClubStats();

                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: isReplacement ? 'Player Replaced!' : 'Player Purchased!',
                            text: `${player.name} has been added to your team`,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                });
            }
        });
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
                // Create custom player
                selectedPlayers[currentSlotIdx] = {
                    name: customName,
                    position: requiredPosition,
                    value: customPlayerValue,
                    rating: 70, // Default rating for custom players
                    isCustom: true // Flag to identify custom players
                };

                $('#playerModal').addClass('hidden');
                renderPlayers();
                renderField();
                updateClubStats();

                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Custom Player Created!',
                    text: `${customName} has been added to your team`,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
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
    });

    $('#playerModal').click(function (e) {
        if (e.target === this) {
            $(this).addClass('hidden');
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
                    team: JSON.stringify(selectedPlayers)
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
                        confirmButtonColor: ' #ef4444 '
                    });
                });
            }
        });
    });

</script>

<?php
// End content capture and render layout
endContent($_SESSION['club_name'], 'team');
?>