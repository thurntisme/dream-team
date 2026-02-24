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
                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($p['name']); ?></div>
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
});
</script>
<?php
endContent('Player Inventory', 'player_inventory', true, false, true, true);
