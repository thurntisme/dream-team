<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

// Require user to be logged in and have a club name
requireClubName('shop');

try {
    $db = getDbConnection();

    // Get current user's data
    $stmt = $db->prepare('SELECT budget, team, max_players FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Ensure max_players is set for existing users
    if (!isset($user_data['max_players']) || $user_data['max_players'] === null) {
        $user_data['max_players'] = DEFAULT_MAX_PLAYERS;
        $stmt = $db->prepare('UPDATE users SET max_players = :max_players WHERE id = :user_id');
        $stmt->bindValue(':max_players', DEFAULT_MAX_PLAYERS, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    // Create shop_items table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS shop_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL,
        price INTEGER NOT NULL,
        effect_type TEXT NOT NULL,
        effect_value TEXT NOT NULL,
        category TEXT NOT NULL,
        icon TEXT DEFAULT "package",
        duration INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // Create user_inventory table to track purchased items
    $db->exec('CREATE TABLE IF NOT EXISTS user_inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        item_id INTEGER NOT NULL,
        purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        quantity INTEGER DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users (id),
        FOREIGN KEY (item_id) REFERENCES shop_items (id)
    )');

    // Insert default shop items if table is empty
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM shop_items');
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];

    if ($count == 0) {
        $default_items = [
            // Training Items
            ['Training Camp', 'Boost all players rating by +2 for 7 days', 5000000, 'player_boost', '{"rating": 2}', 'training', 'dumbbell', 7],
            ['Fitness Coach', 'Reduce injury risk by 50% for 14 days', 3000000, 'injury_protection', '{"reduction": 0.5}', 'training', 'heart-pulse', 14],
            ['Skill Academy', 'Boost specific position players by +3 rating for 5 days', 4000000, 'position_boost', '{"rating": 3}', 'training', 'graduation-cap', 5],

            // Financial Items
            ['Sponsorship Deal', 'Increase budget by €10M instantly', 8000000, 'budget_boost', '{"amount": 10000000}', 'financial', 'handshake', 0],
            ['Stadium Upgrade', 'Generate €500K daily for 30 days', 15000000, 'daily_income', '{"amount": 500000}', 'financial', 'building', 30],
            ['Merchandise Boost', 'Increase transfer sale prices by 20% for 14 days', 6000000, 'sale_boost', '{"multiplier": 1.2}', 'financial', 'shopping-bag', 14],

            // Special Items
            ['Lucky Charm', 'Increase chance of successful transfers by 25%', 2500000, 'transfer_luck', '{"boost": 0.25}', 'special', 'clover', 10],
            ['Scout Network', 'Reveal hidden player stats for 7 days', 3500000, 'player_insight', '{"enabled": true}', 'special', 'search', 7],
            ['Energy Drink', 'Boost team performance by 15% for next 3 matches', 1500000, 'match_boost', '{"performance": 0.15, "matches": 3}', 'special', 'zap', 0],

            // Premium Items
            ['Golden Boot', 'Permanently increase striker ratings by +1', 20000000, 'permanent_boost', '{"position": "ST", "rating": 1}', 'premium', 'award', 0],
            ['Tactical Genius', 'Unlock advanced formations for 30 days', 12000000, 'formation_unlock', '{"advanced": true}', 'premium', 'brain', 30],
            ['Club Legend', 'Attract better players in transfers for 21 days', 18000000, 'player_attraction', '{"quality_boost": 0.3}', 'premium', 'star', 21],

            // Squad Expansion Items
            ['Youth Academy', 'Permanently increase squad size by +2 players', 25000000, 'squad_expansion', '{"players": 2}', 'premium', 'users', 0],
            ['Training Facilities', 'Permanently increase squad size by +3 players', 35000000, 'squad_expansion', '{"players": 3}', 'premium', 'building-2', 0],
            ['Elite Academy', 'Permanently increase squad size by +5 players', 50000000, 'squad_expansion', '{"players": 5}', 'premium', 'graduation-cap', 0],

            // Stadium Items
            ['Stadium Name Change', 'Allows you to change your stadium name', 2000000, 'stadium_rename', '{"enabled": true}', 'special', 'edit-3', 0]
        ];

        $stmt = $db->prepare('INSERT INTO shop_items (name, description, price, effect_type, effect_value, category, icon, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($default_items as $item) {
            $stmt->bindValue(1, $item[0], SQLITE3_TEXT);
            $stmt->bindValue(2, $item[1], SQLITE3_TEXT);
            $stmt->bindValue(3, $item[2], SQLITE3_INTEGER);
            $stmt->bindValue(4, $item[3], SQLITE3_TEXT);
            $stmt->bindValue(5, $item[4], SQLITE3_TEXT);
            $stmt->bindValue(6, $item[5], SQLITE3_TEXT);
            $stmt->bindValue(7, $item[6], SQLITE3_TEXT);
            $stmt->bindValue(8, $item[7], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // Get all shop items
    $stmt = $db->prepare('SELECT * FROM shop_items ORDER BY category, price ASC');
    $result = $stmt->execute();

    $shop_items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $shop_items[] = $row;
    }

    // Get user's active items
    $stmt = $db->prepare('SELECT ui.*, si.name, si.description, si.effect_type, si.effect_value, si.icon 
                         FROM user_inventory ui 
                         JOIN shop_items si ON ui.item_id = si.id 
                         WHERE ui.user_id = :user_id AND ui.quantity > 0 
                         AND (ui.expires_at IS NULL OR ui.expires_at > datetime("now"))
                         ORDER BY ui.purchased_at DESC');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $user_items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $user_items[] = $row;
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Club Shop</h1>
            <p class="text-gray-600">Purchase items to boost your club and players</p>

            <!-- User Budget and Squad Info Display -->
            <div class="mt-4 flex flex-wrap justify-center gap-4">
                <div class="inline-flex items-center gap-2 bg-green-100 text-green-800 px-6 py-3 rounded-full text-sm">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                    <span>Budget: <?php echo formatMarketValue($user_data['budget']); ?></span>
                </div>
                <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-800 px-6 py-3 rounded-full text-sm">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Squad Limit:
                        <?php echo $user_data['max_players']; ?> players
                    </span>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex justify-center mb-6">
            <div class="bg-gray-100 p-1 rounded-lg flex flex-wrap gap-1">
                <button id="allItemsTab"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors bg-white text-blue-600 shadow-sm">
                    All Items
                </button>
                <button id="trainingTab"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-900">
                    Training
                </button>
                <button id="financialTab"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-900">
                    Financial
                </button>
                <button id="specialTab"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-900">
                    Special
                </button>
                <button id="premiumTab"
                    class="px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-gray-900">
                    Premium
                </button>
                <button id="myItemsTab" class=" px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-600
                    hover:text-gray-900">
                    My Items (
                    <?php echo count($user_items); ?>)
                </button>
            </div>
        </div>

        <!-- Shop Items Content -->
        <div id="shopContent" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="itemsGrid">
                <!-- Items will be loaded here -->
            </div>
        </div>

        <!-- My Items Content -->
        <div id="myItemsContent" class="tab-content hidden">
            <div class="space-y-4">
                <?php if (empty($user_items)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="shopping-cart" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Items Purchased</h3>
                        <p class="text-gray-600">You haven't purchased any items yet. Browse the shop to get
                            started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($user_items as $item): ?>
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class=" w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="<?php echo $item['icon']; ?>" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['name']); ?>
                                        </h4>
                                        <p class=" text-sm text-gray-600">
                                            <?php echo htmlspecialchars($item['description']); ?>
                                        </p>
                                        <p class="text-xs text-blue-600 mt-1">
                                            Purchased:
                                            <?php echo date('M j, Y g:i A', strtotime($item['purchased_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-blue-600 mb-1">
                                        Qty: <?php echo $item['quantity']; ?>
                                    </div>
                                    <?php if ($item['expires_at']): ?>
                                        <div class="text-sm font-medium text-orange-600">
                                            Expires: <?php echo date('M j, Y', strtotime($item['expires_at'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm font-medium text-green-600">Permanent</div>
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

<script>
    // Shop items data from PHP
    const shopItems = <?php echo json_encode($shop_items); ?>;
    const userBudget = <?php echo $user_data['budget']; ?>;
    let currentMaxPlayers = <?php echo $user_data['max_players']; ?>;

    let currentCategory = 'all';

    // Category colors
    const categoryColors = {
        'training': 'from-green-500 to-emerald-600',
        'financial': 'from-yellow-500 to-amber-600',
        'special': 'from-purple-500 to-violet-600',
        'premium': 'from-pink-500 to-rose-600'
    };

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
        if (tabName === 'myItems') {
            document.getElementById('myItemsContent').classList.remove('hidden');
        } else {
            document.getElementById('shopContent').classList.remove('hidden');
            currentCategory = tabName === 'allItems' ? 'all' : tabName;
            renderItems();
        }

        // Add active class to selected tab
        const activeTab = document.getElementById(tabName + 'Tab');
        activeTab.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        activeTab.classList.remove('text-gray-600', 'hover:text-gray-900');
    }

    // Render shop items
    function renderItems() {
        const container = document.getElementById('itemsGrid');

        const filteredItems = currentCategory === 'all'
            ? shopItems
            : shopItems.filter(item => item.category === currentCategory);

        if (filteredItems.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i data-lucide="package" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Items Found</h3>
                    <p class="text-gray-600">No items available in this category.</p>
                </div>
            `;
            lucide.createIcons();
            return;
        }

        container.innerHTML = filteredItems.map(item => {
            const canAfford = userBudget >= item.price;
            const gradientColor = categoryColors[item.category] || 'from-gray-500 to-gray-600';

            return `
                <div class="bg-white border rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="bg-gradient-to-r ${gradientColor} p-4">
                        <div class="flex items-center justify-between text-white">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i data-lucide="${item.icon}" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold">${item.name}</h3>
                                    <p class="text-xs opacity-90 capitalize">${item.category}</p>
                                </div>
                            </div>
                            ${item.duration > 0 ? `<div class="text-xs bg-white bg-opacity-20 px-2 py-1 rounded">${item.duration}d</div>` : ''}
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <p class="text-sm text-gray-600 mb-4">${item.description}</p>
                        
                        <div class="flex items-center justify-between">
                            <div class="text-lg font-bold text-green-600">
                                ${formatMarketValue(item.price)}
                            </div>
                            <button onclick="purchaseItem(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price})" 
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors ${canAfford ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed'}"
                                    ${!canAfford ? 'disabled' : ''}>
                                <i data-lucide="shopping-cart" class="w-4 h-4 inline mr-1"></i>
                                ${canAfford ? 'Purchase' : 'Cannot Afford'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        lucide.createIcons();
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

    // Purchase item function
    function purchaseItem(itemId, itemName, itemPrice) {
        if (userBudget < itemPrice) {
            Swal.fire({
                icon: 'error',
                title: 'Insufficient Budget',
                text: 'You do not have enough budget to purchase this item.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.fire({
            title: `Purchase ${itemName}?`,
            text: `This will cost ${formatMarketValue(itemPrice)}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Purchase',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                submitPurchase(itemId, itemName, itemPrice);
            }
        });
    }

    // Submit purchase to server
    function submitPurchase(itemId, itemName, itemPrice) {
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

        fetch('api/purchase_item_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: parseInt(itemId),
                item_name: itemName,
                item_price: parseInt(itemPrice)
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
                    let successMessage = `You have successfully purchased ${itemName}!`;

                    // Update squad limit display if it was a squad expansion item
                    if (data.new_max_players && data.new_max_players > currentMaxPlayers) {
                        currentMaxPlayers = data.new_max_players;
                        successMessage += ` Your squad limit has been increased to ${currentMaxPlayers} players!`;

                        // Update the display immediately
                        const squadLimitElement = document.querySelector('.bg-blue-100.text-blue-800 span');
                        if (squadLimitElement) {
                            squadLimitElement.textContent = `Squad Limit: ${currentMaxPlayers} players`;
                        }
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Purchase Successful!',
                        text: successMessage,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        // Refresh page to update budget and items
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Purchase Failed',
                        text: data.message || 'Failed to purchase item',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error purchasing item:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Purchase Failed',
                    text: error.message || 'Failed to purchase item. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Event listeners for tabs
    document.getElementById('allItemsTab').addEventListener('click ', () => switchTab('allItems '));
    document.getElementById('trainingTab').addEventListener('click ', () => switchTab('training '));
    document.getElementById('financialTab').addEventListener('click ', () => switchTab('financial '));
    document.getElementById('specialTab').addEventListener('click', () => switchTab('special'));
    document.getElementById('premiumTab').addEventListener('click', () => switchTab('premium'));
    document.getElementById('myItemsTab').addEventListener('click', () => switchTab('myItems'));

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        renderItems();
        lucide.createIcons();
    });
</script>

<?php
// End content capture and render layout
endContent('Club Shop - Dream Team', 'shop');
?>