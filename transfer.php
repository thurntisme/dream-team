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
        player_name TEXT NOT NULL,
        player_data TEXT NOT NULL,
        player_index INTEGER NOT NULL,
        bid_amount INTEGER NOT NULL,
        status TEXT DEFAULT "pending",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        response_time DATETIME NULL,
        FOREIGN KEY (bidder_id) REFERENCES users (id),
        FOREIGN KEY (owner_id) REFERENCES users (id)
    )');

    // Get all clubs with their players (excluding current user)
    $stmt = $db->prepare('SELECT id, name, club_name, team FROM users WHERE id != :current_user_id');
    $stmt->bindValue(':current_user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $available_players = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $team = json_decode($row['team'] ?? '[]', true);
        if (is_array($team)) {
            foreach ($team as $index => $player) {
                if ($player && isset($player['name'])) {
                    $available_players[] = [
                        'owner_id' => $row['id'],
                        'owner_name' => $row['name'],
                        'club_name' => $row['club_name'],
                        'player_index' => $index,
                        'player' => $player
                    ];
                }
            }
        }
    }

    // Get pending bids made by current user
    $stmt = $db->prepare('SELECT tb.*, u.club_name as owner_club FROM transfer_bids tb 
                         JOIN users u ON tb.owner_id = u.id 
                         WHERE tb.bidder_id = :user_id AND tb.status = "pending"
                         ORDER BY tb.created_at DESC');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $my_bids = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $my_bids[] = $row;
    }

    // Get bids received for current user's players
    $stmt = $db->prepare('SELECT tb.*, u.club_name as bidder_club, u.name as bidder_name FROM transfer_bids tb 
                         JOIN users u ON tb.bidder_id = u.id 
                         WHERE tb.owner_id = :user_id AND tb.status = "pending"
                         ORDER BY tb.created_at DESC');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $received_bids = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $received_bids[] = $row;
    }

    // Get completed bids made by current user (accepted, rejected, cancelled)
    $stmt = $db->prepare('SELECT tb.*, u.club_name as owner_club FROM transfer_bids tb 
                         JOIN users u ON tb.owner_id = u.id 
                         WHERE tb.bidder_id = :user_id AND tb.status != "pending"
                         ORDER BY tb.response_time DESC LIMIT 20');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $my_completed_bids = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $my_completed_bids[] = $row;
    }

    // Get completed bids received for current user's players
    $stmt = $db->prepare('SELECT tb.*, u.club_name as bidder_club, u.name as bidder_name FROM transfer_bids tb 
                         JOIN users u ON tb.bidder_id = u.id 
                         WHERE tb.owner_id = :user_id AND tb.status != "pending"
                         ORDER BY tb.response_time DESC LIMIT 20');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $received_completed_bids = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $received_completed_bids[] = $row;
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
                <button id="myBidsTab"
                    class="px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-colors text-gray-600 hover:text-gray-900 whitespace-nowrap">
                    My Bids (<?php echo count($my_bids); ?>)
                </button>
                <button id="receivedBidsTab"
                    class="px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-colors text-gray-600 hover:text-gray-900 whitespace-nowrap">
                    Received (<?php echo count($received_bids); ?>)
                </button>
                <button id="myHistoryTab"
                    class="px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-colors text-gray-600 hover:text-gray-900 whitespace-nowrap">
                    My History (<?php echo count($my_completed_bids); ?>)
                </button>
                <button id="receivedHistoryTab"
                    class="px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-colors text-gray-600 hover:text-gray-900 whitespace-nowrap">
                    Received History (<?php echo count($received_completed_bids); ?>)
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

        <!-- My Bids Tab -->
        <div id="myBidsContent" class="tab-content hidden">
            <div class="space-y-4">
                <?php if (empty($my_bids)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="clipboard-list" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Bids</h3>
                        <p class="text-gray-600">You haven't made any bids yet. Browse available players to start bidding!
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($my_bids as $bid): ?>
                        <?php $player = json_decode($bid['player_data'], true); ?>
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($bid['owner_club']); ?> •
                                            Rating: <?php echo $player['rating'] ?? 'N/A'; ?> •
                                            Value: <?php echo formatMarketValue($player['value'] ?? 0); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-green-600">
                                        <?php echo formatMarketValue($bid['bid_amount']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Bid: <?php echo date('M j, Y g:i A', strtotime($bid['created_at'])); ?>
                                    </div>
                                    <button onclick="cancelBid(<?php echo $bid['id']; ?>)"
                                        class="mt-2 text-xs text-red-600 hover:text-red-800 underline">
                                        Cancel Bid
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Received Bids Tab -->
        <div id="receivedBidsContent" class="tab-content hidden">
            <div class="space-y-4">
                <?php if (empty($received_bids)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="inbox" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Bids Received</h3>
                        <p class="text-gray-600">No one has made bids for your players yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($received_bids as $bid): ?>
                        <?php $player = json_decode($bid['player_data'], true); ?>
                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-yellow-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            Bid from <?php echo htmlspecialchars($bid['bidder_club']); ?>
                                            (<?php echo htmlspecialchars($bid['bidder_name']); ?>) •
                                            Rating: <?php echo $player['rating'] ?? 'N/A'; ?> •
                                            Value: <?php echo formatMarketValue($player['value'] ?? 0); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-green-600">
                                        <?php echo formatMarketValue($bid['bid_amount']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Bid: <?php echo date('M j, Y g:i A', strtotime($bid['created_at'])); ?>
                                    </div>
                                    <div class="mt-2 flex gap-2">
                                        <button onclick="acceptBid(<?php echo $bid['id']; ?>)"
                                            class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                            Accept
                                        </button>
                                        <button onclick="rejectBid(<?php echo $bid['id']; ?>)"
                                            class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Bid History Tab -->
        <div id="myHistoryContent" class="tab-content hidden">
            <div class="space-y-4">
                <?php if (empty($my_completed_bids)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="history" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Bid History</h3>
                        <p class="text-gray-600">You haven't completed any bids yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($my_completed_bids as $bid): ?>
                        <?php
                        $player = json_decode($bid['player_data'], true);
                        $status_colors = [
                            'accepted' => 'bg-green-100 text-green-800 border-green-200',
                            'rejected' => 'bg-red-100 text-red-800 border-red-200',
                            'cancelled' => 'bg-gray-100 text-gray-800 border-gray-200'
                        ];
                        $status_color = $status_colors[$bid['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                        ?>
                        <div class="<?php echo $status_color; ?> rounded-lg p-4 border">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($bid['owner_club']); ?> •
                                            Rating: <?php echo $player['rating'] ?? 'N/A'; ?> •
                                            Value: <?php echo formatMarketValue($player['value'] ?? 0); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Status: <span class="font-medium capitalize"><?php echo $bid['status']; ?></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-green-600">
                                        <?php echo formatMarketValue($bid['bid_amount']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Bid: <?php echo date('M j, Y g:i A', strtotime($bid['created_at'])); ?>
                                    </div>
                                    <?php if ($bid['response_time']): ?>
                                        <div class="text-xs text-gray-500">
                                            Response: <?php echo date('M j, Y g:i A', strtotime($bid['response_time'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Received Bid History Tab -->
        <div id="receivedHistoryContent" class="tab-content hidden">
            <div class="space-y-4">
                <?php if (empty($received_completed_bids)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="history" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Response History</h3>
                        <p class="text-gray-600">You haven't responded to any bids yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($received_completed_bids as $bid): ?>
                        <?php
                        $player = json_decode($bid['player_data'], true);
                        $status_colors = [
                            'accepted' => 'bg-green-100 text-green-800 border-green-200',
                            'rejected' => 'bg-red-100 text-red-800 border-red-200',
                            'cancelled' => 'bg-gray-100 text-gray-800 border-gray-200'
                        ];
                        $status_color = $status_colors[$bid['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                        ?>
                        <div class="<?php echo $status_color; ?> rounded-lg p-4 border">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-yellow-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            Bid from <?php echo htmlspecialchars($bid['bidder_club']); ?>
                                            (<?php echo htmlspecialchars($bid['bidder_name']); ?>) •
                                            Rating: <?php echo $player['rating'] ?? 'N/A'; ?> •
                                            Value: <?php echo formatMarketValue($player['value'] ?? 0); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Status: <span class="font-medium capitalize"><?php echo $bid['status']; ?></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-green-600">
                                        <?php echo formatMarketValue($bid['bid_amount']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Bid: <?php echo date('M j, Y g:i A', strtotime($bid['created_at'])); ?>
                                    </div>
                                    <?php if ($bid['response_time']): ?>
                                        <div class="text-xs text-gray-500">
                                            Response: <?php echo date('M j, Y g:i A', strtotime($bid['response_time'])); ?>
                                        </div>
                                    <?php endif; ?>
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
    document.getElementById('myBidsTab').addEventListener('click', () => switchTab('myBids'));
    document.getElementById('receivedBidsTab').addEventListener('click', () => switchTab('receivedBids'));
    document.getElementById('myHistoryTab').addEventListener('click', () => switchTab('myHistory'));
    document.getElementById('receivedHistoryTab').addEventListener('click', () => switchTab('receivedHistory'));

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
                        <button onclick="makeBid(${item.owner_id}, ${item.player_index})" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md ${canAfford ? 'text-white bg-blue-600 hover:bg-blue-700' : 'text-gray-500 bg-gray-300 cursor-not-allowed'} transition-colors"
                                ${!canAfford ? 'disabled' : ''}>
                            <i data-lucide="hand-coins" class="w-4 h-4 mr-1"></i>
                            ${canAfford ? 'Bid' : 'Cannot Afford'}
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

    // Make bid function
    function makeBid(ownerId, playerIndex) {
        // Find the player data from availablePlayers array
        const playerItem = availablePlayers.find(item =>
            item.owner_id === ownerId && item.player_index === playerIndex
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

        const suggestedBid = Math.round(playerValue * 1.1); // 10% above value

        Swal.fire({
            title: `Make Bid for ${playerName}?`,
            html: `
                <div class="text-left space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Player Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Value:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(playerValue)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Your Budget:</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(userBudget)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bid Amount:</label>
                        <input type="number" id="bidAmount" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               value="${suggestedBid}" min="${Math.round(playerValue * 0.8)}" max="${userBudget}" step="100000">
                        <p class="text-xs text-gray-500 mt-1">Minimum: ${formatMarketValue(Math.round(playerValue * 0.8))} (80% of value)</p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Submit Bid',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            },
            preConfirm: () => {
                const bidAmount = parseInt(document.getElementById('bidAmount').value);
                const minBid = Math.round(playerValue * 0.8);

                if (!bidAmount || bidAmount < minBid) {
                    Swal.showValidationMessage(`Bid must be at least ${formatMarketValue(minBid)}`);
                    return false;
                }

                if (bidAmount > userBudget) {
                    Swal.showValidationMessage('Bid exceeds your budget');
                    return false;
                }

                return bidAmount;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                submitBid(ownerId, playerIndex, playerName, playerData, result.value);
            }
        });
    }

    // Submit bid to server
    function submitBid(ownerId, playerIndex, playerName, playerData, bidAmount) {
        Swal.fire({
            title: 'Submitting Bid...',
            text: 'Please wait while we process your bid',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const requestData = {
            owner_id: parseInt(ownerId),
            player_index: parseInt(playerIndex),
            player_name: playerName,
            player_data: playerData,
            bid_amount: parseInt(bidAmount)
        };

        fetch('submit_bid.php', {
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
                        title: 'Bid Submitted!',
                        text: `Your bid of ${formatMarketValue(bidAmount)} for ${playerName} has been submitted.`,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // Refresh page to update bids
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Bid Failed',
                        text: data.message || 'Unknown error occurred',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error submitting bid:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Bid Failed',
                    text: error.message || 'Failed to submit bid. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Cancel bid function
    function cancelBid(bidId) {
        Swal.fire({
            title: 'Cancel Bid?',
            text: 'Are you sure you want to cancel this bid?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Cancel Bid',
            cancelButtonText: 'Keep Bid'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit cancel request
                fetch('manage_bid.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'cancel',
                        bid_id: bidId
                    })
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
                            Swal.fire({
                                icon: 'success',
                                title: 'Bid Cancelled',
                                text: 'Your bid has been cancelled successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to cancel bid',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error cancelling bid:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Failed to cancel bid. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    }

    // Accept bid function
    function acceptBid(bidId) {
        Swal.fire({
            title: 'Accept Bid?',
            text: 'This will transfer the player and cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Accept Bid',
            cancelButtonText: 'Decline'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit accept request
                fetch('manage_bid.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'accept',
                        bid_id: bidId
                    })
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
                            Swal.fire({
                                icon: 'success',
                                title: 'Bid Accepted!',
                                text: 'The player has been transferred successfully.',
                                confirmButtonColor: '#10b981'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to accept bid',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error accepting bid:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Failed to accept bid. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    }

    // Reject bid function
    function rejectBid(bidId) {
        Swal.fire({
            title: 'Reject Bid?',
            text: 'Are you sure you want to reject this bid?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Reject Bid',
            cancelButtonText: 'Keep Bid'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit reject request
                fetch('manage_bid.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        bid_id: bidId
                    })
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
                            Swal.fire({
                                icon: 'success',
                                title: 'Bid Rejected',
                                text: 'The bid has been rejected.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to reject bid',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error rejecting bid:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Failed to reject bid. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    }

    // Player Info Modal Functions
    function showPlayerInfo(playerData) {
        const player = playerData;

        // Calculate contract years (random for demo)
        const contractYears = Math.floor(Math.random() * 4) + 1;

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
                            <span class="font-medium">${contractYears} year${contractYears > 1 ? 's' : ''}</span>
                        </div>
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

    .player-info-header {
              background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    }

    </style>

 <?php
 // End content capture and render layout
 endContent('Transfer Market - Dream Team', 'transfer');
 ?>