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

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Other Clubs</h1>
        <p class="text-gray-600">Explore teams created by other managers</p>
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
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-bold text-gray-900">
                                        <?php echo htmlspecialchars($club['club_name']); ?>
                                    </h3>
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Club Information</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Manager:</span>
                                <span class="font-semibold">${club.name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Formation:</span>
                                <span class="font-semibold">${club.formation}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Budget:</span>
                                <span class="font-semibold">${formatMarketValue(club.budget)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Team Value:</span>
                                <span class="font-semibold text-green-600">${formatMarketValue(calculateTeamValue(team))}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Remaining Budget:</span>
                                <span class="font-semibold text-blue-600">${formatMarketValue(club.budget - calculateTeamValue(team))}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Team Lineup</h3>
                        <div class="space-y-2">
            `;

        if (formationData && formationData.roles) {
            formationData.roles.forEach((position, index) => {
                const player = team[index];
                if (player) {
                    const isCustom = player.isCustom || false;
                    const playerClass = isCustom ? 'text-purple-600' : 'text-gray-900';
                    const badge = isCustom ? '<span class="text-xs bg-purple-100 text-purple-600 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                    content += `
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                <div>
                                    <span class="text-sm font-medium text-gray-600">${position}:</span>
                                    <span class="ml-2 ${playerClass}">${player.name}${badge}</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-green-600 font-semibold">${formatMarketValue(player.value || 0)}</div>
                                    <div class="text-xs text-gray-500">★${player.rating || 'N/A'}</div>
                                </div>
                            </div>
                        `;
                } else {
                    content += `
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded opacity-50">
                                <span class="text-sm text-gray-500">${position}: <em>Empty</em></span>
                            </div>
                        `;
                }
            });
        }

        content += `
                        </div>
                    </div>
                </div>
            `;

        document.getElementById('modalContent').innerHTML = content;
        document.getElementById('clubModal').classList.remove('hidden');
        lucide.createIcons();
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

    function calculateTeamValue(team) {
        if (!Array.isArray(team)) return 0;
        return team.reduce((total, player) => {
            return total + (player && player.value ? player.value : 0);
        }, 0);
    }

    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
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