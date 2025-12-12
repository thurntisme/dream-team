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
requireClubName('transfer');

try {
    $db = getDbConnection();

    // Get current user's data
    $stmt = $db->prepare('SELECT budget, team FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Create transfer_bids table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS transfer_bids (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bidder_id INTEGER NOT NULL,
        owner_id INTEGER NOT NULL,
        player_uuid TEXT NOT NULL,
        player_data TEXT NOT NULL,
        player_index INTEGER NOT NULL,
        bid_amount INTEGER NOT NULL,
        status TEXT DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        response_time DATETIME NULL,
        FOREIGN KEY (bidder_id) REFERENCES users (id),
        FOREIGN KEY (owner_id) REFERENCES users (id)
    )');

    // Create player_inventory table for purchased players
    $db->exec('CREATE TABLE IF NOT EXISTS player_inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        player_uuid TEXT NOT NULL,
        player_data TEXT NOT NULL,
        purchase_price INTEGER NOT NULL,
        purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT "available",
        FOREIGN KEY (user_id) REFERENCES users (id)
    )');

    // Migration: Handle column changes and add missing columns
    try {
        // Check existing columns in player_inventory
        $result = $db->query("PRAGMA table_info(player_inventory)");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        
        // Add player_uuid column if it doesn't exist
        if (!in_array('player_uuid', $columns)) {
            $db->exec('ALTER TABLE player_inventory ADD COLUMN player_uuid TEXT DEFAULT ""');
        }
        
        // Add purchase_price column if it doesn't exist
        if (!in_array('purchase_price', $columns)) {
            $db->exec('ALTER TABLE player_inventory ADD COLUMN purchase_price INTEGER DEFAULT 0');
        }
        
        // Migrate data from player_name to player_uuid if needed
        if (in_array('player_name', $columns) && in_array('player_uuid', $columns)) {
            // Get all records with empty player_uuid but have player_name
            $stmt = $db->prepare('SELECT id, player_name, player_data FROM player_inventory WHERE player_uuid = "" AND player_name != ""');
            $result = $stmt->execute();
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $player_data = json_decode($row['player_data'], true);
                if ($player_data && isset($player_data['uuid'])) {
                    // Update with UUID from player_data
                    $update_stmt = $db->prepare('UPDATE player_inventory SET player_uuid = :uuid WHERE id = :id');
                    $update_stmt->bindValue(':uuid', $player_data['uuid'], SQLITE3_TEXT);
                    $update_stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $update_stmt->execute();
                }
            }
        }
        
        // Check transfer_bids table
        $result = $db->query("PRAGMA table_info(transfer_bids)");
        $bid_columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $bid_columns[] = $row['name'];
        }
        
        // Add player_uuid column to transfer_bids if it doesn't exist
        if (!in_array('player_uuid', $bid_columns)) {
            $db->exec('ALTER TABLE transfer_bids ADD COLUMN player_uuid TEXT DEFAULT ""');
        }
        
        // Migrate transfer_bids data from player_name to player_uuid if needed
        if (in_array('player_name', $bid_columns) && in_array('player_uuid', $bid_columns)) {
            $stmt = $db->prepare('SELECT id, player_name, player_data FROM transfer_bids WHERE player_uuid = "" AND player_name != ""');
            $result = $stmt->execute();
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $player_data = json_decode($row['player_data'], true);
                if ($player_data && isset($player_data['uuid'])) {
                    $update_stmt = $db->prepare('UPDATE transfer_bids SET player_uuid = :uuid WHERE id = :id');
                    $update_stmt->bindValue(':uuid', $player_data['uuid'], SQLITE3_TEXT);
                    $update_stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $update_stmt->execute();
                }
            }
        }
    } catch (Exception $e) {
        // Migration failed, but continue - table might be new
    }

    // Get all available players from players.json (excluding players already in user's team)
    $all_players = getDefaultPlayers();

    // Get current user's team to exclude owned players
    $stmt = $db->prepare('SELECT team, substitutes FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team_data = $result->fetchArray(SQLITE3_ASSOC);

    $user_team = json_decode($user_team_data['team'] ?? '[]', true) ?: [];
    $user_substitutes = json_decode($user_team_data['substitutes'] ?? '[]', true) ?: [];

    // Get all player names that user already owns
    $owned_player_names = [];
    foreach ($user_team as $player) {
        if ($player && isset($player['name']) && !($player['isCustom'] ?? false)) {
            $owned_player_names[] = strtolower($player['name']);
        }
    }
    foreach ($user_substitutes as $player) {
        if ($player && isset($player['name']) && !($player['isCustom'] ?? false)) {
            $owned_player_names[] = strtolower($player['name']);
        }
    }

    // Filter out players that user already owns
    $available_players = [];
    foreach ($all_players as $index => $player) {
        if (!in_array(strtolower($player['name']), $owned_player_names)) {
            $available_players[] = [
                'owner_id' => null, // No owner for market players
                'owner_name' => 'Transfer Market',
                'club_name' => 'Available',
                'player_index' => $index,
                'player' => $player
            ];
        }
    }



    // Get user's player inventory
    $stmt = $db->prepare('SELECT * FROM player_inventory WHERE user_id = :user_id AND status = "available" ORDER BY purchase_date DESC');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $player_inventory = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['player_data'] = json_decode($row['player_data'], true);
        $player_inventory[] = $row;
    }

    $db->close();

} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <div class="bg-white rounded-lg p-6 mb-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Transfer Market</h1>
            <p class="text-gray-600">Buy and sell players with other clubs</p>

            <!-- User Budget Display -->
            <div class="mt-4 inline-flex items-center gap-2 bg-green-100 text-green-800 px-6 py-3 rounded-full text-sm">
                <i data-lucide="wallet" class="w-4 h-4"></i>
                <span>Your Budget: <?php echo formatMarketValue($user_data['budget']); ?></span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex justify-center mb-6">
            <div class="bg-gray-100 p-1 rounded-lg flex flex-wrap gap-1 max-w-full overflow-x-auto">
                <button id="playersTab"
                    class="px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-colors bg-white text-blue-600 shadow-sm whitespace-nowrap">
                    Available Players
                </button>
                <button id="myPlayersTab"
                    class="px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-colors text-gray-600 hover:text-gray-900 whitespace-nowrap">
                    My Players (<?php echo count($player_inventory); ?>)
                </button>
            </div>
        </div>

        <!-- Available Players Tab -->
        <div id="playersContent" class="tab-content">
            <div class="mb-4 flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" id="playerSearch" placeholder="Search players..."
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex gap-2">
                    <select id="positionFilter"
                        class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Positions</option>
                        <option value="GK">Goalkeeper</option>
                        <option value="CB">Centre Back</option>
                        <option value="LB">Left Back</option>
                        <option value="RB">Right Back</option>
                        <option value="CDM">Defensive Midfielder</option>
                        <option value="CM">Central Midfielder</option>
                        <option value="CAM">Attacking Midfielder</option>
                        <option value="LW">Left Winger</option>
                        <option value="RW">Right Winger</option>
                        <option value="ST">Striker</option>
                    </select>
                    <select id="priceFilter"
                        class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Prices</option>
                        <option value="0-1000000">Under €1M</option>
                        <option value="1000000-5000000">€1M - €5M</option>
                        <option value="5000000-20000000">€5M - €20M</option>
                        <option value="20000000-50000000">€20M - €50M</option>
                        <option value="50000000-999999999">€50M+</option>
                    </select>
                </div>
            </div>

            <!-- Players Table -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Player</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Salary</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Position</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rating</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Value</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody id="playersTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Players will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="hidden text-center py-12">
                    <i data-lucide="search" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Players Found</h3>
                    <p class="text-gray-600">Try adjusting your search criteria.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div id="paginationContainer" class="mt-6 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span
                        id="totalPlayers">0</span> players
                </div>
                <div class="flex items-center gap-2">
                    <button id="prevPage"
                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    <div id="pageNumbers" class="flex gap-1">
                        <!-- Page numbers will be inserted here -->
                    </div>
                    <button id="nextPage"
                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- My Players Tab -->
        <div id="myPlayersContent" class="tab-content hidden">
            <div class="space-y-4">
                <?php if (empty($player_inventory)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="users" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Players in Inventory</h3>
                        <p class="text-gray-600">Purchase players from the transfer market to manage them here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($player_inventory as $inventory_item): ?>
                        <?php $player = $inventory_item['player_data']; ?>
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($player['position']); ?> •
                                            Rating: <?php echo $player['rating'] ?? 'N/A'; ?> •
                                            Value: <?php echo formatMarketValue($player['value'] ?? 0); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Purchased:
                                            <?php echo date('M j, Y', strtotime($inventory_item['purchase_date'])); ?> •
                                            Cost: <?php echo formatMarketValue($inventory_item['purchase_price']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="showPlayerInfo(<?php echo htmlspecialchars(json_encode($player)); ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-100 rounded transition-colors"
                                        title="Player Info">
                                        <i data-lucide="info" class="w-4 h-4"></i>
                                    </button>
                                    <div class="flex flex-col gap-1">
                                        <button
                                            onclick="assignToTeam(<?php echo $inventory_item['id']; ?>, <?php echo htmlspecialchars(json_encode($player)); ?>)"
                                            class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors">
                                            Assign to Team
                                        </button>
                                        <button
                                            onclick="sellPlayer(<?php echo $inventory_item['id']; ?>, <?php echo htmlspecialchars(json_encode($player)); ?>)"
                                            class="px-3 py-1 bg-yellow-600 text-white text-xs rounded hover:bg-yellow-700 transition-colors">
                                            Sell Player
                                        </button>
                                        <button
                                            onclick="deletePlayer(<?php echo $inventory_item['id']; ?>, '<?php echo htmlspecialchars($player['name']); ?>')"
                                            class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 transition-colors">
                                            Release
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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

<script>
    // Available players data from PHP
    const availablePlayers = <?php echo json_encode($available_players); ?>;
    const userBudget = <?php echo $user_data['budget']; ?>;

    let filteredPlayers = [...availablePlayers];
    let currentPage = 1;
    const playersPerPage = 10;

    // Tab switching
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active class from all tabs
        document.querySelectorAll('[id$="Tab"]').forEach(tab => {
            tab.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
            tab.classList.add('text-gray-600', 'hover:text-gray-900');
        });

        // Show selected tab content
        document.getElementById(tabName + 'Content').classList.remove('hidden');

        // Add active class to selected tab
        const activeTab = document.getElementById(tabName + 'Tab');
        activeTab.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        activeTab.classList.remove('text-gray-600', 'hover:text-gray-900');
    }

    // Event listeners for tabs
    document.getElementById('playersTab').addEventListener('click', () => {
        switchTab('players');
        currentPage = 1;
        renderPlayers();
    });
    document.getElementById('myPlayersTab').addEventListener('click', () => switchTab('myPlayers'));

    // Filter and search functionality
    function filterPlayers() {
        const search = document.getElementById('playerSearch').value.toLowerCase();
        const position = document.getElementById('positionFilter').value;
        const priceRange = document.getElementById('priceFilter').value;

        filteredPlayers = availablePlayers.filter(item => {
            const player = item.player;

            // Search filter
            const matchesSearch = !search ||
                player.name.toLowerCase().includes(search) ||
                item.club_name.toLowerCase().includes(search) ||
                item.owner_name.toLowerCase().includes(search);

            // Position filter
            const matchesPosition = !position || player.position === position;

            // Price filter
            let matchesPrice = true;
            if (priceRange) {
                const [min, max] = priceRange.split('-').map(Number);
                const playerValue = player.value || 0;
                matchesPrice = playerValue >= min && playerValue <= max;
            }

            return matchesSearch && matchesPosition && matchesPrice;
        });

        currentPage = 1; // Reset to first page when filtering
        renderPlayers();
    }

    // Render players table with pagination
    function renderPlayers() {
        const tableBody = document.getElementById('playersTableBody');
        const emptyState = document.getElementById('emptyState');
        const paginationContainer = document.getElementById('paginationContainer');

        if (filteredPlayers.length === 0) {
            tableBody.innerHTML = '';
            emptyState.classList.remove('hidden');
            paginationContainer.classList.add('hidden');
            lucide.createIcons();
            return;
        }

        emptyState.classList.add('hidden');
        paginationContainer.classList.remove('hidden');

        // Calculate pagination
        const totalPages = Math.ceil(filteredPlayers.length / playersPerPage);
        const startIndex = (currentPage - 1) * playersPerPage;
        const endIndex = Math.min(startIndex + playersPerPage, filteredPlayers.length);
        const currentPlayers = filteredPlayers.slice(startIndex, endIndex);

        // Render table rows
        tableBody.innerHTML = currentPlayers.map(item => {
            const player = item.player;
            const canAfford = userBudget >= (player.value || 0);

            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-3">
                                <i data-lucide="user" class="w-5 h-5 text-white"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900">${player.name}</div>
                            </div>
                            <button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')})" class="ml-2 p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Player Info">
                                <i data-lucide="info" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${formatMarketValue(calculateSalary(player))}</div>
                        <div class="text-sm text-gray-500">per week</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${player.position || 'N/A'}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${player.rating || 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                        ${formatMarketValue(player.value || 0)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="makeBid(null, ${item.player_index})" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md ${canAfford ? 'text-white bg-green-600 hover:bg-green-700' : 'text-gray-500 bg-gray-300 cursor-not-allowed'} transition-colors"
                                ${!canAfford ? 'disabled' : ''}>
                            <i data-lucide="shopping-cart" class="w-4 h-4 mr-1"></i>
                            ${canAfford ? 'Buy' : 'Cannot Afford'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Update pagination info
        document.getElementById('showingStart').textContent = startIndex + 1;
        document.getElementById('showingEnd').textContent = endIndex;
        document.getElementById('totalPlayers').textContent = filteredPlayers.length;

        // Render pagination controls
        renderPagination(totalPages);

        lucide.createIcons();
    }

    // Render pagination controls
    function renderPagination(totalPages) {
        const pageNumbers = document.getElementById('pageNumbers');
        const prevButton = document.getElementById('prevPage');
        const nextButton = document.getElementById('nextPage');

        // Update prev/next buttons
        prevButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages;

        // Generate page numbers
        let pages = [];
        const maxVisiblePages = 5;

        if (totalPages <= maxVisiblePages) {
            // Show all pages if total is small
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
        } else {
            // Show smart pagination
            if (currentPage <= 3) {
                pages = [1, 2, 3, 4, 5];
            } else if (currentPage >= totalPages - 2) {
                pages = [totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
            } else {
                pages = [currentPage - 2, currentPage - 1, currentPage, currentPage + 1, currentPage + 2];
            }
        }

        pageNumbers.innerHTML = pages.map(page => `
            <button onclick="goToPage(${page})" 
                    class="px-3 py-2 text-sm font-medium ${page === currentPage ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'} border rounded-md">
                ${page}
            </button>
        `).join('');
    }

    // Go to specific page
    function goToPage(page) {
        currentPage = page;
        renderPlayers();
    }

    // Previous page
    function previousPage() {
        if (currentPage > 1) {
            currentPage--;
            renderPlayers();
        }
    }

    // Next page
    function nextPage() {
        const totalPages = Math.ceil(filteredPlayers.length / playersPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderPlayers();
        }
    }

    // Format market value
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + (value / 1000).toFixed(0) + 'K';
        } else {
            return '€' + value;
        }
    }

    // Calculate weekly salary based on player value and rating
    function calculateSalary(player) {
        const baseValue = player.value || 1000000;
        const rating = player.rating || 70;

        // Calculate weekly salary as a percentage of market value
        // Higher rated players get higher percentage
        let salaryPercentage = 0.001; // Base 0.1% of market value per week

        // Bonus based on rating
        if (rating >= 90) salaryPercentage = 0.002; // 0.2% for 90+ rated
        else if (rating >= 85) salaryPercentage = 0.0018; // 0.18% for 85+ rated
        else if (rating >= 80) salaryPercentage = 0.0015; // 0.15% for 80+ rated
        else if (rating >= 75) salaryPercentage = 0.0012; // 0.12% for 75+ rated

        const weeklySalary = Math.round(baseValue * salaryPercentage);

        // Minimum salary of €10K per week
        return Math.max(weeklySalary, 10000);
    }

    // Make bid function for market players
    function makeBid(ownerId, playerIndex) {
        // Find the player data from availablePlayers array
        const playerItem = availablePlayers.find(item =>
            item.player_index === playerIndex
        );

        if (!playerItem) {
            Swal.fire({
                icon: 'error',
                title: 'Player Not Found',
                text: 'The selected player could not be found.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        const player = playerItem.player;
        const playerName = player.name;
        const playerData = player; // Pass the raw object, not JSON string
        const playerValue = player.value || 0;

        const suggestedBid = playerValue; // Direct purchase at market value

        Swal.fire({
            title: `Purchase ${playerName}?`,
            html: `
                <div class="text-left space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Player Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Market Price:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(playerValue)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Your Budget:</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(userBudget)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Remaining Budget:</span>
                                <span class="font-medium ${userBudget - playerValue >= 0 ? 'text-green-600' : 'text-red-600'}">${formatMarketValue(userBudget - playerValue)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            This player will be purchased directly from the transfer market at the listed price.
                        </p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="shopping-cart" class="w-4 h-4 inline mr-1"></i> Purchase Player',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                purchasePlayer(playerIndex, playerName, playerData, playerValue);
            }
        });
    }

    // Purchase player directly from market
    function purchasePlayer(playerIndex, playerName, playerData, purchaseAmount) {
        Swal.fire({
            title: 'Processing Purchase...',
            text: 'Please wait while we process your purchase',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const requestData = {
            player_index: parseInt(playerIndex),
            player_uuid: playerData.uuid,
            player_data: playerData,
            purchase_amount: parseInt(purchaseAmount)
        };

        fetch('purchase_player.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json().then(data => {
                    if (!response.ok) {
                        // Server returned an error status, but we have the error message
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }
                    return data;
                });
            })
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Player Purchased!',
                        text: `${playerName} has been added to your team for ${formatMarketValue(purchaseAmount)}.`,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // Refresh page to update available players
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Purchase Failed',
                        text: data.message || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error purchasing player:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Purchase Failed',
                    text: error.message || 'Failed to purchase player. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }



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
                        <div class="mt-3 p-3 rounded-lg border bg-orange-50 border-orange-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-orange-600">
                                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                                        Contract ${contractRemaining <= 3 ? 'Expiring Soon' : 'Renewal Needed'}
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">Contract renewal recommended</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
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
                                ${(player.playablePositions || [player.position]).map(pos =>
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
                    <p class="text-gray-700 leading-relaxed">${player.description || 'Professional football player with great potential and skills.'}</p>
                </div>
            </div>
        `;

        document.getElementById('playerInfoContent').innerHTML = playerInfoHtml;
        document.getElementById('playerInfoModal').classList.remove('hidden');
        lucide.createIcons();
    }

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

    // Player Management Functions
    function assignToTeam(inventoryId, playerData) {
        Swal.fire({
            title: `Assign ${playerData.name} to Team?`,
            text: 'This will move the player from your inventory to your team substitutes.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Assign to Team',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                manageInventoryPlayer('assign', inventoryId, playerData);
            }
        });
    }

    function sellPlayer(inventoryId, playerData) {
        const sellPrice = Math.round((playerData.value || 0) * 0.7); // 70% of market value

        Swal.fire({
            title: `Sell ${playerData.name}?`,
            html: `
                <div class="text-left space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Market Value:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(playerData.value || 0)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Sell Price (70%):</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(sellPrice)}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600">The player will be removed from your inventory and you'll receive the sell price.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sell Player',
            cancelButtonText: 'Keep Player'
        }).then((result) => {
            if (result.isConfirmed) {
                manageInventoryPlayer('sell', inventoryId, playerData, sellPrice);
            }
        });
    }

    function deletePlayer(inventoryId, playerName) {
        Swal.fire({
            title: `Release ${playerName}?`,
            text: 'This will permanently remove the player from your inventory without compensation.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Release Player',
            cancelButtonText: 'Keep Player'
        }).then((result) => {
            if (result.isConfirmed) {
                manageInventoryPlayer('delete', inventoryId, { name: playerName });
            }
        });
    }

    function manageInventoryPlayer(action, inventoryId, playerData, sellPrice = 0) {
        Swal.fire({
            title: 'Processing...',
            text: 'Please wait while we process your request',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const requestData = {
            action: action,
            inventory_id: inventoryId,
            player_data: playerData,
            sell_price: sellPrice
        };

        fetch('manage_inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
            .then(response => {
                return response.json().then(data => {
                    if (!response.ok) {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }
                    return data;
                });
            })
            .then(data => {
                if (data.success) {
                    let title = 'Success!';
                    let message = '';

                    switch (action) {
                        case 'assign':
                            title = 'Player Assigned!';
                            message = `${playerData.name} has been added to your team substitutes.`;
                            break;
                        case 'sell':
                            title = 'Player Sold!';
                            message = `${playerData.name} has been sold for ${formatMarketValue(sellPrice)}.`;
                            break;
                        case 'delete':
                            title = 'Player Released!';
                            message = `${playerData.name} has been released from your inventory.`;
                            break;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: title,
                        text: message,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Action Failed',
                        text: data.message || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error managing player:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Action Failed',
                    text: error.message || 'Failed to process request. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Close player info modal
    document.getElementById('closePlayerInfoModal').addEventListener('click', function () {
        document.getElementById('playerInfoModal').classList.add('hidden');
    });

    document.getElementById('playerInfoModal').addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });

    // Event listeners for filters
    document.getElementById('playerSearch').addEventListener('input', filterPlayers);
    document.getElementById('positionFilter').addEventListener('change', filterPlayers);
    document.getElementById('priceFilter').addEventListener('change', filterPlayers);

    // Event listeners for pagination
    document.getElementById('prevPage').addEventListener('click', previousPage);
    document.getElementById('nextPage').addEventListener('click', nextPage);

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        renderPlayers();
        lucide.createIcons();
    });
</script>

<style>
    #playerInfoModal .max-w-2xl {
        max-width: 48rem;
    }

    .player-info-stat-bar {
        transition: width 0.3s ease;
    }

    .player-in fo-header {
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    }
</style>
<?php
// End content capture and render layout
endContent('Transfer Market - Dream Team', 'transfer');
?>