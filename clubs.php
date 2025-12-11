<?php
session_start();

require_once 'config.php';
require_once 'constants.php';
require_once 'layout.php';
require_once 'field-component.php';

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

    // Get current user's data for budget validation
    $stmt = $db->prepare('SELECT budget, team FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Get all clubs except current user's club
    $stmt = $db->prepare('SELECT id, name, email, club_name, formation, team, budget, created_at FROM users WHERE id != :current_user_id');
    $stmt->bindValue(':current_user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $clubs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Calculate team value for sorting
        $row['team_value'] = calculateTeamValue($row['team']);
        $clubs[] = $row;
    }

    // Sort clubs by team value (highest first)
    usort($clubs, fn($a, $b) => $b['team_value'] - $a['team_value']);

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Helper function to calculate team value
function calculateTeamValue($teamJson)
{
    $team = json_decode($teamJson, true);
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

// Helper function to count players
function countPlayers($teamJson)
{
    $team = json_decode($teamJson, true);
    if (!is_array($team))
        return 0;

    return count(array_filter($team, fn($player) => $player !== null));
}

// Helper function to get club level name
function getClubLevelNamePHP($level)
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

// Helper function to get level color classes
function getLevelColorPHP($level)
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
    <!-- Challenge Messages -->
    <?php if (isset($_SESSION['challenge_error'])): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
                <span class="font-medium">Challenge Failed:</span>
                <span><?php echo htmlspecialchars($_SESSION['challenge_error']); ?></span>
            </div>
        </div>
        <?php unset($_SESSION['challenge_error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['match_success'])): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <span class="font-medium">Match Completed:</span>
                <span><?php echo htmlspecialchars($_SESSION['match_success']); ?></span>
            </div>
        </div>
        <?php unset($_SESSION['match_success']); ?>
    <?php endif; ?>

    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Other Clubs</h1>
                <p class="text-gray-600">Challenge other managers in competitive matches</p>
            </div>
            <div class="flex gap-4">
                <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg">
                    <div class="text-sm text-blue-600">Your Budget</div>
                    <div class="text-lg font-bold"><?php echo formatMarketValue($user_data['budget']); ?></div>
                </div>
                <?php
                // Calculate user's club level
                $userTeam = json_decode($user_data['team'] ?? '[]', true);
                $userClubLevel = 1;
                if (is_array($userTeam)) {
                    $totalRating = 0;
                    $totalValue = 0;
                    $validPlayers = 0;

                    foreach ($userTeam as $player) {
                        if ($player && isset($player['rating']) && isset($player['value'])) {
                            $totalRating += $player['rating'];
                            $totalValue += $player['value'];
                            $validPlayers++;
                        }
                    }

                    if ($validPlayers > 0) {
                        $avgRating = $totalRating / $validPlayers;
                        $avgValue = $totalValue / $validPlayers;

                        if ($avgRating >= 85 && $avgValue >= 50000000) {
                            $userClubLevel = 5;
                        } elseif ($avgRating >= 80 && $avgValue >= 30000000) {
                            $userClubLevel = 4;
                        } elseif ($avgRating >= 75 && $avgValue >= 15000000) {
                            $userClubLevel = 3;
                        } elseif ($avgRating >= 70 && $avgValue >= 5000000) {
                            $userClubLevel = 2;
                        }
                    }
                }
                ?>
                <div class="bg-purple-100 text-purple-800 px-4 py-2 rounded-lg">
                    <div class="text-sm text-purple-600">Your Level</div>
                    <div class="text-lg font-bold">Level <?php echo $userClubLevel; ?> -
                        <?php echo function_exists('getClubLevelNamePHP') ? getClubLevelNamePHP($userClubLevel) : 'Beginner'; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($clubs)): ?>
            <?php
            $topTeamValue = $clubs[0]['team_value'];
            $avgTeamValue = array_sum(array_column($clubs, 'team_value')) / count($clubs);
            ?>
            <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
                <span class="flex items-center gap-1">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <?php echo count($clubs); ?> clubs
                </span>
                <span class="flex items-center gap-1">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                    Top value: <?php echo formatMarketValue($topTeamValue); ?>
                </span>
                <span class="flex items-center gap-1">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    Avg value: <?php echo formatMarketValue($avgTeamValue); ?>
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-2">Clubs ordered by team value (highest first)</p>
        <?php endif; ?>
    </div>

    <?php if (empty($clubs)): ?>
        <div class="text-center py-12">
            <i data-lucide="users" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Other Clubs Yet</h3>
            <p class="text-gray-600 mb-6">You're the first manager here! Other clubs will appear as more users join.</p>
            <a href="team.php"
                class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to My Team
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($clubs as $index => $club): ?>
                <?php
                $teamValue = $club['team_value']; // Use pre-calculated value
                $playerCount = countPlayers($club['team']);
                $budgetUsed = $teamValue > 0 ? ($teamValue / $club['budget']) * 100 : 0;

                // Calculate club level
                $team = json_decode($club['team'], true);
                $clubLevel = 1;
                if (is_array($team)) {
                    $totalRating = 0;
                    $totalValue = 0;
                    $validPlayers = 0;

                    foreach ($team as $player) {
                        if ($player && isset($player['rating']) && isset($player['value'])) {
                            $totalRating += $player['rating'];
                            $totalValue += $player['value'];
                            $validPlayers++;
                        }
                    }

                    if ($validPlayers > 0) {
                        $avgRating = $totalRating / $validPlayers;
                        $avgValue = $totalValue / $validPlayers;

                        if ($avgRating >= 85 && $avgValue >= 50000000) {
                            $clubLevel = 5; // Elite
                        } elseif ($avgRating >= 80 && $avgValue >= 30000000) {
                            $clubLevel = 4; // Professional
                        } elseif ($avgRating >= 75 && $avgValue >= 15000000) {
                            $clubLevel = 3; // Semi-Professional
                        } elseif ($avgRating >= 70 && $avgValue >= 5000000) {
                            $clubLevel = 2; // Amateur
                        }
                    }
                }


                ?>
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
                    <div class="p-6">
                        <!-- Club Header -->
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                <i data-lucide="shield" class="w-6 h-6 text-white"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-lg font-bold text-gray-900">
                                        <?php echo htmlspecialchars($club['club_name']); ?>
                                    </h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border <?php echo getLevelColorPHP($clubLevel); ?>">
                                        <i data-lucide="star" class="w-3 h-3"></i>
                                        Level <?php echo $clubLevel; ?> - <?php echo getClubLevelNamePHP($clubLevel); ?>
                                    </span>
                                    <?php if ($club['team_value'] > 0): ?>
                                        <?php if ($index === 0): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-1 rounded-full"
                                                title="Highest Team Value">
                                                <i data-lucide="crown" class="w-3 h-3"></i>
                                                #1
                                            </span>
                                        <?php elseif ($index === 1): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-xs font-semibold px-2 py-1 rounded-full"
                                                title="Second Highest Team Value">
                                                <i data-lucide="medal" class="w-3 h-3"></i>
                                                #2
                                            </span>
                                        <?php elseif ($index === 2): ?>
                                            <span
                                                class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-1 rounded-full"
                                                title="Third Highest Team Value">
                                                <i data-lucide="award" class="w-3 h-3"></i>
                                                #3
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-600">Manager: <?php echo htmlspecialchars($club['name']); ?></p>
                            </div>
                        </div>

                        <!-- Club Stats -->
                        <div class="space-y-3 mb-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Formation</span>
                                <span class="text-sm font-semibold"><?php echo htmlspecialchars($club['formation']); ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Players</span>
                                <span class="text-sm font-semibold"><?php echo $playerCount; ?>/11</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Team Value</span>
                                <span
                                    class="text-sm font-semibold text-green-600"><?php echo formatMarketValue($teamValue); ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Budget Used</span>
                                <span
                                    class="text-sm font-semibold <?php echo $budgetUsed > 90 ? 'text-red-600' : ($budgetUsed > 70 ? 'text-yellow-600' : 'text-blue-600'); ?>">
                                    <?php echo number_format($budgetUsed, 1); ?>%
                                </span>
                            </div>
                        </div>

                        <!-- Budget Bar -->
                        <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                            <div class="h-2 rounded-full transition-all duration-300 <?php echo $budgetUsed > 90 ? 'bg-red-500' : ($budgetUsed > 70 ? 'bg-yellow-500' : 'bg-blue-500'); ?>"
                                style="width: <?php echo min($budgetUsed, 100); ?>%"></div>
                        </div>

                        <!-- Challenge Cost Info -->
                        <?php
                        $challengeCost = 5000000 + ($teamValue * 0.01); // Same calculation as JavaScript
                        $canAfford = $user_data['budget'] >= $challengeCost;
                        $canChallenge = $playerCount >= 11 && $canAfford;
                        ?>
                        <div
                            class="mb-4 p-2 <?php echo $canChallenge ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?> rounded-lg border">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Challenge Cost:</span>
                                <span class="font-medium <?php echo $canAfford ? 'text-green-700' : 'text-red-600'; ?>">
                                    <?php echo formatMarketValue($challengeCost); ?>
                                </span>
                            </div>
                            <?php if ($playerCount < 11): ?>
                                <div class="text-xs text-red-600 mt-1">
                                    <i data-lucide="alert-triangle" class="w-3 h-3 inline mr-1"></i>
                                    Incomplete team (<?php echo $playerCount; ?>/11)
                                </div>
                            <?php elseif (!$canAfford): ?>
                                <div class="text-xs text-red-600 mt-1">
                                    <i data-lucide="alert-triangle" class="w-3 h-3 inline mr-1"></i>
                                    Insufficient funds
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-green-600 mt-1">
                                    <i data-lucide="check-circle" class="w-3 h-3 inline mr-1"></i>
                                    Ready to challenge
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button onclick="viewClub(<?php echo $club['id']; ?>)"
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                                View Club
                            </button>
                            <?php if ($playerCount > 0): ?>
                                <button onclick="compareTeams(<?php echo $club['id']; ?>)"
                                    class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center justify-center">
                                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Club Age -->
                        <div class="mt-3 text-xs text-gray-500 text-center">
                            Created <?php echo date('M j, Y', strtotime($club['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Club Detail Modal -->
<div id="clubModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalClubName" class="text-2xl font-bold text-gray-900"></h2>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div id="modalContent" class="space-y-6">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
    .swal-wide {
        width: 600px !important;
    }

    .swal-wide .swal2-html-container {
        max-height: 400px;
        overflow-y: auto;
    }
</style>

<script>
    lucide.createIcons();

    // Club data for JavaScript
    const clubsData = <?php echo json_encode($clubs); ?>;

    function viewClub(clubId) {
        const club = clubsData.find(c => c.id == clubId);
        if (!club) return;

        const team = JSON.parse(club.team || '[]');
        const formations = <?php echo json_encode(FORMATIONS); ?>;
        const formationData = formations[club.formation];

        document.getElementById('modalClubName').textContent = club.club_name;

        let content = `
            <div class="space-y-6">
                <!-- Club Information -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4">Club Information</h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                        <div class="text-center">
                            <div class="text-gray-600">Manager</div>
                            <div class="font-semibold">${club.name}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-600">Formation</div>
                            <div class="font-semibold">${club.formation}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-600">Budget</div>
                            <div class="font-semibold">${formatMarketValue(club.budget)}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-600">Team Value</div>
                            <div class="font-semibold text-green-600">${formatMarketValue(calculateTeamValue(team))}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-600">Remaining</div>
                            <div class="font-semibold text-blue-600">${formatMarketValue(club.budget - calculateTeamValue(team))}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <!-- Team Lineup List -->
                    <div class="col-span-6">
                        <h3 class="text-lg font-semibold mb-4">Team Lineup</h3>
                        <div class="grid grid-cols-1 gap-2 max-h-[500px] overflow-y-auto pr-2">
            `;

        if (formationData && formationData.roles) {
            formationData.roles.forEach((position, index) => {
                const player = team[index];
                if (player) {
                    const isCustom = player.isCustom || false;
                    const playerClass = isCustom ? 'text-purple-600' : 'text-gray-900';
                    const badge = isCustom ? '<span class="text-xs bg-purple-100 text-purple-600 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                    content += `
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded text-sm">
                                <div>
                                    <span class="font-medium text-gray-600">${position}:</span>
                                    <span class="ml-2 ${playerClass}">${player.name}${badge}</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-green-600 font-semibold">${formatMarketValue(player.value || 0)}</div>
                                    <div class="text-xs text-gray-500">★${player.rating || 'N/A'}</div>
                                </div>
                            </div>
                        `;
                } else {
                    content += `
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded opacity-50 text-sm">
                                <span class="text-gray-500">${position}: <em>Empty</em></span>
                            </div>
                        `;
                }
            });
        }

        content += `
                        </div>
                    </div>

                    <!-- Football Field -->
                    <div class="mt-10 col-span-6 relative bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow ">
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
                        <div id="modalFieldContainer" class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <span class="ml-2 text-gray-600">Loading field...</span>
                        </div>
                    </div>
                </div>

                <!-- Challenge Button -->
                <div class="flex justify-center pt-4 border-t">
                    <button onclick="challengeClub(${club.id})" class="inline-flex items-center gap-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold px-6 py-3 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105">
                        <i data-lucide="sword" class="w-5 h-5"></i>
                        Challenge ${club.club_name}
                    </button>
                </div>
            </div>
        `;

        document.getElementById('modalContent').innerHTML = content;
        document.getElementById('clubModal').classList.remove('hidden');

        const positions = formations[club.formation].positions;
        const roles = formationData.roles;
        const $field = $('#modalFieldContainer');
        $field.empty();


        function getPositionForSlot(slotIdx) {
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

        // Load field via AJAX
        fetch(`field-modal.php?club_id=${clubId}&formation=${encodeURIComponent(club.formation)}`)
            .then(response => response.text())
            .then(result => {
                const { club, formation, players } = JSON.parse(result);
                console.log(JSON.parse(result))

                let playerIdx = 0;
                positions.forEach((line, lineIdx) => {
                    line.forEach(xPos => {
                        const player = players[playerIdx];
                        const yPos = 100 - ((lineIdx + 1) * (100 / (positions.length + 1)));
                        const idx = playerIdx;

                        const requiredPosition = getPositionForSlot(idx);
                        const colors = getPositionColors(requiredPosition);

                        $field.append(`
                            <div class="absolute cursor-pointer player-slot transition-all duration-200" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="relative">
                                    <div class="w-12 h-12 bg-white rounded-full flex flex-col items-center justify-center shadow-lg border-2 ${colors.border} transition-all duration-200 player-circle ">
                                        <i data-lucide="user" class="w-3 h-3 text-gray-600"></i>
                                        <span class="text-[10px] font-bold text-gray-700">${requiredPosition}</span>
                                    </div>
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 whitespace-nowrap">
                                        <div class="text-white text-[10px] font-bold bg-black bg-opacity-70 px-1.5 py-0.5 rounded">${player.name}</div>
                                    </div>
                                </div>
                            </div>
                        `);
                        playerIdx++;
                    });
                });

                lucide.createIcons();
            })
            .catch(error => {
                console.error('Field loading error:', error);
                document.getElementById('modalFieldContainer').innerHTML =
                    '<div class="text-center text-red-600 py-8">Failed to load field</div>';
            });
    }

    function compareTeams(clubId) {
        // Future feature: Team comparison
        Swal.fire({
            icon: 'info',
            title: 'Coming Soon',
            text: 'Team comparison feature will be available in a future update!',
            confirmButtonColor: '#3b82f6'
        });
    }

    function challengeClub(clubId) {
        const club = clubsData.find(c => c.id == clubId);
        if (!club) return;

        const team = JSON.parse(club.team || '[]');
        const playerCount = team.filter(p => p !== null).length;
        const teamValue = calculateTeamValue(team);

        // Calculate challenge cost
        const baseCost = 5000000; // €5M base cost
        const challengeCost = baseCost + (teamValue * 0.005); // 0.5% of opponent's team value
        const potentialReward = challengeCost * 1.5; // 150% reward for winning (50% profit)

        // Get current user data (from PHP session)
        const currentBudget = <?php echo $user_data['budget'] ?? 0; ?>;
        const userTeam = <?php echo json_encode(json_decode($user_data['team'] ?? '[]', true)); ?>;
        const userPlayerCount = userTeam.filter(p => p !== null).length;

        // Check if user has enough players
        if (userPlayerCount < 11) {
            Swal.fire({
                icon: 'warning',
                title: 'Cannot Challenge',
                text: `You need a complete team (11 players) to challenge other clubs! You currently have ${userPlayerCount}/11 players. Go to "My Team" to add more players.`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Check if opponent has enough players
        if (playerCount < 11) {
            Swal.fire({
                icon: 'warning',
                title: 'Cannot Challenge',
                text: `${club.club_name} doesn't have a complete team (11 players). They have ${playerCount}/11 players and cannot be challenged yet.`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Check if user has enough budget
        if (currentBudget < challengeCost) {
            Swal.fire({
                icon: 'error',
                title: 'Insufficient Funds',
                html: `
                    <div class="text-left space-y-3">
                        <p class="text-gray-700">You don't have enough budget to challenge this club.</p>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Challenge Cost:</span>
                                    <span class="font-medium text-red-600">${formatMarketValue(challengeCost)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Your Budget:</span>
                                    <span class="font-medium text-blue-600">${formatMarketValue(currentBudget)}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-gray-600">Shortfall:</span>
                                    <span class="font-medium text-red-600">${formatMarketValue(challengeCost - currentBudget)}</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600">💡 Tip: Sell some players or challenge weaker opponents to earn more budget!</p>
                    </div>
                `,
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'Back to Clubs'
            });
            return;
        }

        Swal.fire({
            title: `Challenge ${club.club_name}?`,
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Opponent Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Manager:</span>
                                <span class="font-medium">${club.name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Formation:</span>
                                <span class="font-medium">${club.formation}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Players:</span>
                                <span class="font-medium">${playerCount}/11</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Team Value:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(teamValue)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-900 mb-2 flex items-center gap-2">
                            <i data-lucide="coins" class="w-4 h-4"></i>
                            Challenge Stakes
                        </h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Entry Fee:</span>
                                <span class="font-medium text-red-600">${formatMarketValue(challengeCost)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Win Prize:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(potentialReward)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Draw Refund:</span>
                                <span class="font-medium text-yellow-600">${formatMarketValue(challengeCost * 0.5)}</span>
                            </div>
                            <div class="flex justify-between border-t pt-1 mt-2">
                                <span class="text-gray-600">Your Budget:</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(currentBudget)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                        <h4 class="font-semibold text-purple-900 mb-2 flex items-center gap-2">
                            <i data-lucide="star" class="w-4 h-4"></i>
                            Club Level Bonus
                        </h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Your Level:</span>
                                <span class="font-medium text-purple-600">Level ${calculateClubLevel(userTeam)} - ${getClubLevelName(calculateClubLevel(userTeam))}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Win Bonus:</span>
                                <span class="font-medium text-purple-600">+${getLevelBonus(calculateClubLevel(userTeam))}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Draw Bonus:</span>
                                <span class="font-medium text-purple-600">+${Math.round(getLevelBonus(calculateClubLevel(userTeam)) / 2)}%</span>
                            </div>
                            ${calculateClubLevel(userTeam) >= 3 ? `
                            <div class="flex justify-between">
                                <span class="text-gray-600">Loss Consolation:</span>
                                <span class="font-medium text-purple-600">${formatMarketValue(challengeCost * 0.1)}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            This is a competitive challenge match with real financial stakes. Higher club levels earn bonus rewards!
                        </p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="sword" class="w-4 h-4 inline mr-1"></i> Send Challenge',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to match simulator
                window.location.href = `match-simulator.php?opponent=${clubId}`;
            }
        });
    }



    function calculateTeamValue(team) {
        if (!Array.isArray(team)) return 0;
        return team.reduce((total, player) => {
            return total + (player && player.value ? player.value : 0);
        }, 0);
    }

    function calculateClubLevel(team) {
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
        if (avgRating >= 85 && avgValue >= 50000000) { // €50M+ avg, 85+ rating
            return 5; // Elite
        } else if (avgRating >= 80 && avgValue >= 30000000) { // €30M+ avg, 80+ rating
            return 4; // Professional
        } else if (avgRating >= 75 && avgValue >= 15000000) { // €15M+ avg, 75+ rating
            return 3; // Semi-Professional
        } else if (avgRating >= 70 && avgValue >= 5000000) { // €5M+ avg, 70+ rating
            return 2; // Amateur
        } else {
            return 1; // Beginner
        }
    }

    function getClubLevelName(level) {
        switch (level) {
            case 5: return 'Elite';
            case 4: return 'Professional';
            case 3: return 'Semi-Professional';
            case 2: return 'Amateur';
            case 1:
            default: return 'Beginner';
        }
    }

    function getLevelBonus(level) {
        switch (level) {
            case 5: return 25; // 25% bonus for Elite clubs
            case 4: return 20; // 20% bonus for Professional clubs
            case 3: return 15; // 15% bonus for Semi-Professional clubs
            case 2: return 10; // 10% bonus for Amateur clubs
            case 1:
            default: return 0; // No bonus for Beginner clubs
        }
    }

    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + ' M ';
        } else if (value >= 1000) {
            return '€' + Math.round(value / 1000) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }

    // Close modal
    document.getElementById('closeModal').addEventListener('click', function () {
        document.getElementById('clubModal').classList.add('hidden');
    });

    // Close modal when clicking outside
    document.getElementById('clubModal').addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });

</script>

<?php
// End content capture and render layout
endContent('Other Clubs', 'clubs');
?>