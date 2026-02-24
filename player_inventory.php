<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

try {
    if (!isDatabaseAvailable()) {
        throw new Exception('Database not available');
    }
    $db = getDbConnection();

    $user_uuid = $_SESSION['user_uuid'] ?? null;
    if (!$user_uuid) {
        throw new Exception('User not authenticated');
    }

    // Resolve club_uuid
    $stmtClub = $db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :user_uuid');
    $stmtClub->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $resClub = $stmtClub->execute();
    $rowClub = $resClub ? $resClub->fetchArray(SQLITE3_ASSOC) : null;
    $club_uuid = $rowClub['club_uuid'] ?? null;

    // Fetch inventory rows
    $inv = [];
    if ($club_uuid) {
        $stmt = $db->prepare('SELECT id, player_uuid, player_data, status FROM player_inventory WHERE club_uuid = :club_uuid ORDER BY id DESC');
        if ($stmt !== false) {
            $stmt->bindValue(':club_uuid', $club_uuid, SQLITE3_TEXT);
            $res = $stmt->execute();
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $inv[] = $row;
            }
        }
    }

    $db->close();
} catch (Exception $e) {
    startContent();
    ?>
    <div class="container mx-auto p-6">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
            <?php echo htmlspecialchars($e->getMessage()); ?>
        </div>
    </div>
    <?php
    endContent('Player Inventory', 'player_inventory', true, false, true, true);
    exit;
}

// Build normalized list
$players = [];
foreach ($inv as $row) {
    $pdata = json_decode($row['player_data'] ?? '[]', true) ?: [];
    $players[] = [
        'id' => (int)($row['id'] ?? 0),
        'uuid' => ($row['player_uuid'] ?? ($pdata['uuid'] ?? null)),
        'name' => $pdata['name'] ?? 'Unknown',
        'position' => $pdata['position'] ?? 'CM',
        'rating' => (int)($pdata['rating'] ?? 0),
        'value' => (int)($pdata['value'] ?? 0),
        'status' => $row['status'] ?? 'available',
    ];
}

startContent();
?>
<div class="container mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Player Inventory</h1>
        <p class="text-gray-600">All players you own but not in your active squad.</p>
    </div>

    <div class="bg-white border rounded-lg overflow-hidden">
        <div class="p-4 border-b flex items-center gap-3">
            <div class="flex-1">
                <input id="searchInput" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search players by name or position...">
            </div>
            <select id="statusFilter" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="sold">Sold</option>
            </select>
        </div>
        <div id="listContainer" class="divide-y">
            <?php if (empty($players)): ?>
                <div class="p-6 text-center text-gray-600">No players in inventory.</div>
            <?php else: ?>
                <?php foreach ($players as $p): ?>
                    <div class="p-4 flex items-center justify-between hover:bg-gray-50">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold">
                                <?php echo htmlspecialchars(substr($p['position'], 0, 2)); ?>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 flex items-center gap-2">
                                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                                    <button onclick="showPlayerInfo(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)" class="p-1 text-blue-600 hover:bg-blue-100 rounded" title="Player Info">
                                        <i data-lucide="info" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars($p['position']); ?> • Rating <?php echo (int)$p['rating']; ?> • Value <?php echo formatMarketValue($p['value']); ?>
                                </div>
                                <div class="text-xs text-gray-500">Status: <?php echo htmlspecialchars($p['status']); ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($p['status'] === 'available'): ?>
                                <button class="assign-btn px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm" data-id="<?php echo (int)$p['id']; ?>" data-uuid="<?php echo htmlspecialchars($p['uuid'] ?? '', ENT_QUOTES); ?>">
                                    Add to Squad
                                </button>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs"><?php echo htmlspecialchars(ucfirst($p['status'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="playerInfoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-end mb-6">
            <button id="closePlayerInfoModal" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="playerInfoContent"></div>
    </div>
    </div>
<link rel="stylesheet" href="assets/css/player-modal.css">

<script>
document.addEventListener('DOMContentLoaded', () => {
    function filterList() {
        const q = document.getElementById('searchInput').value.trim().toLowerCase();
        const s = document.getElementById('statusFilter').value;
        document.querySelectorAll('#listContainer > div.p-4').forEach(row => {
            const text = row.textContent.toLowerCase();
            const statusMatch = !s || text.includes(`status: ${s}`);
            const queryMatch = !q || text.includes(q);
            row.style.display = (statusMatch && queryMatch) ? '' : 'none';
        });
    }
    document.getElementById('searchInput').addEventListener('input', filterList);
    document.getElementById('statusFilter').addEventListener('change', filterList);

    document.querySelectorAll('.assign-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = parseInt(btn.dataset.id);
            const result = await Swal.fire({
                title: 'Add to Squad?',
                text: 'This will move the player from inventory to your squad if space is available.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Add to Squad',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280'
            });
            if (!result.isConfirmed) {
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Adding...';
            const uuid = String(btn.dataset.uuid || '').trim();
            try {
                Swal.fire({
                    title: 'Adding Player...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
                const res = await fetch('api/manage_inventory_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'assign', inventory_id: id, player_uuid: uuid})
                });
                const data = await res.json();
                Swal.close();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to add player to squad');
                }
                await Swal.fire({
                    icon: 'success',
                    title: 'Player Added',
                    text: 'The player has been added to your squad.',
                    confirmButtonColor: '#10b981'
                });
                location.reload();
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: e.message || 'Failed to add player to squad',
                    confirmButtonColor: '#ef4444'
                });
                btn.disabled = false;
                btn.textContent = 'Add to Squad';
            }
        });
    });
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + (value / 1000).toFixed(0) + 'K';
        } else {
            return '€' + value;
        }
    }
    function calculateSalary(player) {
        if (player && typeof player.salary === 'number' && player.salary > 0) {
            return player.salary;
        }
        const baseValue = player.value || 1000000;
        const rating = player.rating || 70;
        let salaryPercentage = 0.001;
        if (rating >= 90) salaryPercentage = 0.002;
        else if (rating >= 85) salaryPercentage = 0.0018;
        else if (rating >= 80) salaryPercentage = 0.0015;
        else if (rating >= 75) salaryPercentage = 0.0012;
        const weeklySalary = Math.round(baseValue * salaryPercentage);
        return Math.max(weeklySalary, 10000);
    }
    function generatePlayerStats(position, rating) {
        const base = {
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
        const stats = {};
        const list = base[position] || base['CM'];
        list.forEach(stat => {
            const variation = Math.floor(Math.random() * 10) - 5;
            const val = Math.max(30, Math.min(99, (rating || 70) + variation));
            stats[stat] = val;
        });
        return stats;
    }
    function showPlayerInfo(player) {
        const contractMatches = player.contract_matches || Math.floor(Math.random() * 36) + 15;
        const contractRemaining = player.contract_matches_remaining || contractMatches;
        let stats = player.attributes || generatePlayerStats(player.position, player.rating);
        const normalizedStats = {};
        Object.entries(stats).forEach(([key, value]) => {
            const k = key.charAt(0).toUpperCase() + key.slice(1);
            normalizedStats[k] = value;
        });
        const imageBaseUrl = '<?php echo PLAYER_IMAGES_BASE_PATH; ?>';
        const avatarHtml = player.avatar
            ? `<img src="${player.avatar.startsWith('http') ? player.avatar : imageBaseUrl + player.avatar}" alt="${player.name}" class="w-full h-full object-cover" onerror="this.onerror=null; this.parentElement.innerHTML='<i data-lucide=\\'user\\' class=\\'w-12 h-12\\'></i>';">`
            : `<i data-lucide="user" class="w-12 h-12"></i>`;
        const playable = (player.playablePositions || [player.position]).map(pos =>
            `<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium">${pos}</span>`
        ).join('');
        const attrs = Object.entries(normalizedStats).map(([stat, value]) => `
            <div class="flex items-center gap-3">
                <span class="text-sm w-28">${stat}</span>
                <div class="flex-1 bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: ${value}%"></div>
                </div>
                <span class="text-sm font-medium w-8 text-right">${value}</span>
            </div>
        `).join('');
        const html = `
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="lg:col-span-2 bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg p-6">
                    <div class="flex items-center gap-6">
                        <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center overflow-hidden">
                            ${avatarHtml}
                        </div>
                        <div class="flex-1">
                            <h2 class="text-3xl font-bold mb-2">${player.name}</h2>
                            <div class="flex items-center gap-4 text-blue-100">
                                <span class="bg-blue-500 px-2 py-1 rounded text-sm font-semibold">${player.position}</span>
                                ${player.nationality ? `<span class="flex items-center gap-1"><i data-lucide="flag" class="w-4 h-4"></i>${player.nationality}</span>` : ''}
                                ${player.age ? `<span class="flex items-center gap-1"><i data-lucide="calendar" class="w-4 h-4"></i>${player.age} years</span>` : ''}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold">★${player.rating}</div>
                            <div class="text-blue-200 text-sm">Overall Rating</div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="briefcase" class="w-5 h-5 text-green-600"></i>
                        Career Information
                    </h3>
                    <div class="space-y-3">
                        ${player.nationality ? `<div class="flex justify-between"><span class="text-gray-600">Nationality:</span><span class="font-medium flex items-center gap-1"><i data-lucide="flag" class="w-4 h-4"></i>${player.nationality}</span></div>` : ''}
                        ${player.age ? `<div class="flex justify-between"><span class="text-gray-600">Age:</span><span class="font-medium">${player.age} years old</span></div>` : ''}
                        <div class="flex justify-between"><span class="text-gray-600">Market Value:</span><span class="font-medium text-green-600">${formatMarketValue(player.value || 0)}</span></div>
                        <div class="flex justify-between"><span class="text-gray-600">Salary:</span><span class="font-medium text-blue-600">${formatMarketValue(calculateSalary(player))}</span></div>
                        <div class="flex justify-between"><span class="text-gray-600">Contract:</span><span class="font-medium">${contractRemaining} match${contractRemaining !== 1 ? 'es' : ''} remaining</span></div>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="target" class="w-5 h-5 text-purple-600"></i>
                        Positions & Skills
                    </h3>
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                            <div class="flex flex-col gap-2">
                                <span class="text-gray-600 text-sm">Main Position:</span>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium w-max">${player.position || 'N/A'}</span>
                            </div>
                            <div class="flex-1 flex flex-col gap-2">
                                <span class="text-gray-600 text-sm">Playable Positions:</span>
                                <div class="flex flex-wrap gap-2">${playable}</div>
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-600 text-sm">Key Attributes:</span>
                            <div class="mt-2 space-y-2">${attrs}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('playerInfoContent').innerHTML = html;
        document.getElementById('playerInfoModal').classList.remove('hidden');
        lucide.createIcons();
    }
    // Expose for inline onclick usage
    window.showPlayerInfo = showPlayerInfo;
    document.getElementById('closePlayerInfoModal').addEventListener('click', function () {
        document.getElementById('playerInfoModal').classList.add('hidden');
    });
    document.getElementById('playerInfoModal').addEventListener('click', function (e) {
        if (e.target === this) this.classList.add('hidden');
    });
});
</script>
<?php
endContent('Player Inventory', 'player_inventory', true, false, true, true);
