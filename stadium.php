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

if (!isset($_SESSION['user_id']) || !isset($_SESSION['club_name'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = getDbConnection();

    // Create stadium table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS stadiums (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT DEFAULT "Home Stadium",
        capacity INTEGER DEFAULT 10000,
        level INTEGER DEFAULT 1,
        facilities TEXT DEFAULT "{}",
        last_upgrade DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )');


    // Get user's current data including budget and fans
    $stmt = $db->prepare('SELECT budget, club_name, fans FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);
    $user_budget = $user_data['budget'] ?? 0;
    $club_name = $user_data['club_name'] ?? '';
    $user_fans = $user_data['fans'] ?? 5000;

    // Check if user has purchased stadium name change item
    try {
        $stmt = $db->prepare(
            'SELECT 1
             FROM user_inventory ui
             JOIN shop_items si ON ui.item_id = si.id
             WHERE ui.user_id = :user_id
               AND si.effect_type = "stadium_rename"
               AND ui.quantity > 0
             LIMIT 1'
        );

        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $db->lastErrorMsg());
        }

        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

        $result = $stmt->execute();
        if (!$result) {
            throw new Exception('Execute failed: ' . $db->lastErrorMsg());
        }

        $can_rename_stadium = (bool) $result->fetchArray();

    } catch (Exception $e) {
        error_log('[canRenameStadium] ' . $e->getMessage());

        $can_rename_stadium = false;
    }

    // Get or create stadium data
    $stmt = $db->prepare('SELECT * FROM stadiums WHERE user_id = :user_id');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $stadium_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$stadium_data) {
        // Create default stadium
        $stmt = $db->prepare('INSERT INTO stadiums (user_id, name) VALUES (:user_id, :name)');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':name', $club_name . ' Stadium', SQLITE3_TEXT);
        $stmt->execute();

        // Get the newly created stadium
        $stmt = $db->prepare('SELECT * FROM stadiums WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $stadium_data = $result->fetchArray(SQLITE3_ASSOC);
    }

    // Get current stadium rename item quantity for JavaScript
    $stmt = $db->prepare('SELECT SUM(ui.quantity) as total_quantity FROM user_inventory ui 
                         JOIN shop_items si ON ui.item_id = si.id 
                         WHERE ui.user_id = :user_id AND si.effect_type = "stadium_rename" 
                         AND ui.quantity > 0');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $current_rename_quantity = $result->fetchArray(SQLITE3_ASSOC)['total_quantity'] ?? 0;

    $db->close();

} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Stadium upgrade costs and benefits
$stadium_levels = [
    1 => [
        'name' => 'Basic Stadium',
        'capacity' => 10000,
        'upgrade_cost' => 5000000, // 5M to upgrade to level 2
        'revenue_multiplier' => 1.0,
        'description' => 'A modest stadium with basic facilities'
    ],
    2 => [
        'name' => 'Community Stadium',
        'capacity' => 20000,
        'upgrade_cost' => 15000000, // 15M to upgrade to level 3
        'revenue_multiplier' => 1.2,
        'description' => 'Improved facilities with better seating and amenities'
    ],
    3 => [
        'name' => 'Professional Stadium',
        'capacity' => 35000,
        'upgrade_cost' => 30000000, // 30M to upgrade to level 4
        'revenue_multiplier' => 1.5,
        'description' => 'Modern stadium with premium facilities and corporate boxes'
    ],
    4 => [
        'name' => 'Elite Stadium',
        'capacity' => 50000,
        'upgrade_cost' => 60000000, // 60M to upgrade to level 5
        'revenue_multiplier' => 1.8,
        'description' => 'State-of-the-art stadium with luxury amenities'
    ],
    5 => [
        'name' => 'Legendary Stadium',
        'capacity' => 75000,
        'upgrade_cost' => null, // Max level
        'revenue_multiplier' => 2.2,
        'description' => 'Iconic stadium that attracts fans from around the world'
    ]
];

$current_level = $stadium_data['level'];
$current_stadium = $stadium_levels[$current_level];
$next_level = $current_level < 5 ? $stadium_levels[$current_level + 1] : null;

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <!-- Stadium Header -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-gray-600 to-gray-800 rounded-full flex items-center justify-center shadow-lg">
                        <i data-lucide="building" class="w-8 h-8 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($stadium_data['name']); ?>
                        </h1>
                        <p class="text-gray-600">Home of <?php echo htmlspecialchars($club_name); ?></p>
                        <div class="flex items-center gap-2 mt-2">
                            <span
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                <i data-lucide="star" class="w-4 h-4"></i>
                                Level <?php echo $current_level; ?> - <?php echo $current_stadium['name']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-600">Your Budget</div>
                    <div class="text-2xl font-bold text-green-600"><?php echo formatMarketValue($user_budget); ?></div>
                </div>
            </div>

            <!-- Stadium Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo number_format($stadium_data['capacity']); ?>
                    </div>
                    <div class="text-sm text-gray-600">Capacity</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $current_level; ?>/5</div>
                    <div class="text-sm text-gray-600">Stadium Level</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-blue-600">
                        <?php echo number_format($user_fans); ?>
                    </div>
                    <div class="text-sm text-gray-600">Club Fans</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">
                        +<?php echo number_format(($current_stadium['revenue_multiplier'] - 1) * 100); ?>%</div>
                    <div class="text-sm text-gray-600">Revenue Bonus</div>
                </div>
                <div class="bg-white rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo date('M j, Y', strtotime($stadium_data['last_upgrade'])); ?>
                    </div>
                    <div class="text-sm text-gray-600">Last Upgrade</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Current Stadium Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i data-lucide="info" class="w-5 h-5"></i>
                Current Stadium
            </h2>

            <div class="space-y-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2"><?php echo $current_stadium['name']; ?></h3>
                    <p class="text-gray-600 text-sm mb-3"><?php echo $current_stadium['description']; ?></p>

                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Capacity:</span>
                            <span class="font-medium"><?php echo number_format($current_stadium['capacity']); ?>
                                seats</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Revenue Multiplier:</span>
                            <span class="font-medium"><?php echo $current_stadium['revenue_multiplier']; ?>x</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Level:</span>
                            <span class="font-medium"><?php echo $current_level; ?> / 5</span>
                        </div>
                    </div>
                </div>

                <!-- Stadium Features -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-900 mb-2">Stadium Features</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <?php
                        $features = [
                            1 => ['Basic Seating', 'Concession Stands'],
                            2 => ['Improved Seating', 'Food Courts', 'Parking'],
                            3 => ['Premium Seating', 'Corporate Boxes', 'VIP Lounges'],
                            4 => ['Luxury Suites', 'Media Center', 'Player Facilities'],
                            5 => ['World-Class Amenities', 'Museum', 'Training Complex']
                        ];

                        for ($i = 1; $i <= $current_level; $i++) {
                            foreach ($features[$i] as $feature) {
                                echo '<div class="flex items-center gap-2 text-gray-600">';
                                echo '<i data-lucide="check" class="w-3 h-3 text-green-600"></i>';
                                echo '<span>' . $feature . '</span>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stadium Upgrade -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i data-lucide="arrow-up" class="w-5 h-5"></i>
                Stadium Upgrade
            </h2>

            <?php if ($next_level): ?>
                <div class="space-y-4">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h3 class="font-semibold text-blue-900 mb-2">Next Level: <?php echo $next_level['name']; ?></h3>
                        <p class="text-blue-700 text-sm mb-3"><?php echo $next_level['description']; ?></p>

                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-blue-700">New Capacity:</span>
                                <span
                                    class="font-medium text-blue-900"><?php echo number_format($next_level['capacity']); ?>
                                    seats</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-blue-700">Revenue Multiplier:</span>
                                <span
                                    class="font-medium text-blue-900"><?php echo $next_level['revenue_multiplier']; ?>x</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-blue-700">Upgrade Cost:</span>
                                <span
                                    class="font-medium text-blue-900"><?php echo formatMarketValue($current_stadium['upgrade_cost']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- New Features Preview -->
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <h4 class="font-semibold text-green-900 mb-2">New Features</h4>
                        <div class="space-y-1">
                            <?php
                            $new_features = $features[$current_level + 1] ?? [];
                            foreach ($new_features as $feature) {
                                echo '<div class="flex items-center gap-2 text-green-700">';
                                echo '<i data-lucide="plus" class="w-3 h-3"></i>';
                                echo '<span>' . $feature . '</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Fan System Info -->
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Fan System</h4>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p>• Fans attend home matches and generate €10 revenue per fan</p>
                            <p>• Win matches to gain 50-200 new fans</p>
                            <p>• Lose matches and lose 25-100 fans</p>
                            <p>• Goal difference affects fan changes (+/- 10 fans per goal)</p>
                            <p>• Larger stadiums can accommodate more fans</p>
                        </div>
                    </div>

                    <!-- Upgrade Button -->
                    <div class="pt-4">
                        <?php
                        $can_afford = $user_budget >= $current_stadium['upgrade_cost'];
                        $button_class = $can_afford ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed';
                        ?>
                        <button id="upgradeStadium"
                            class="w-full px-6 py-3 rounded-lg font-medium transition-colors <?php echo $button_class; ?>"
                            <?php echo !$can_afford ? 'disabled' : ''; ?>
                            data-cost="<?php echo $current_stadium['upgrade_cost']; ?>"
                            data-level="<?php echo $current_level + 1; ?>">
                            <i data-lucide="arrow-up" class="w-4 h-4 inline mr-2"></i>
                            <?php echo $can_afford ? 'Upgrade Stadium' : 'Insufficient Budget'; ?>
                        </button>

                        <?php if (!$can_afford): ?>
                            <p class="text-sm text-gray-500 mt-2 text-center">
                                Need <?php echo formatMarketValue($current_stadium['upgrade_cost'] - $user_budget); ?> more
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Max Level Reached -->
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="trophy" class="w-8 h-8 text-yellow-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Maximum Level Reached!</h3>
                    <p class="text-gray-600">Your stadium has reached the highest level possible. You have the most
                        prestigious stadium in the league!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stadium Management -->
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <i data-lucide="settings" class="w-5 h-5"></i>
            Stadium Management
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Rename Stadium -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">Stadium Name</h3>
                <div class="flex gap-2">
                    <input type="text" id="stadiumName" value="<?php echo htmlspecialchars($stadium_data['name']); ?>"
                        class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo !$can_rename_stadium ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                        <?php echo !$can_rename_stadium ? 'readonly' : ''; ?>>
                    <button id="renameStadium"
                        class="px-4 py-2 rounded-lg transition-colors <?php echo $can_rename_stadium ? 'bg-gray-600 text-white hover:bg-gray-700' : 'bg-gray-300 text-gray-500 cursor-not-allowed'; ?>"
                        <?php echo !$can_rename_stadium ? 'disabled' : ''; ?>>
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </button>
                </div>
                <?php if (!$can_rename_stadium): ?>
                    <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <i data-lucide="lock" class="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0"></i>
                            <div class="text-sm">
                                <p class="text-yellow-800 font-medium">Stadium name cannot be changed</p>
                                <p class="text-yellow-700 mt-1">Purchase the "Stadium Name Change" item from the <a
                                        href="shop.php" class="underline hover:text-yellow-900">Club Shop</a> to unlock this
                                    feature.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stadium Revenue Info -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">Revenue Information</h3>
                <div class="space-y-1 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>Base Match Revenue:</span>
                        <span class="font-medium">€50,000</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Stadium Multiplier:</span>
                        <span class="font-medium"><?php echo $current_stadium['revenue_multiplier']; ?>x</span>
                    </div>
                    <?php
                    // Calculate expected attendance (fans can't exceed stadium capacity)
                    $expected_attendance = min($user_fans, $stadium_data['capacity']);
                    $attendance_percentage = ($expected_attendance / $stadium_data['capacity']) * 100;
                    $fan_bonus = $expected_attendance * 10; // €10 per fan
                    $total_revenue = (50000 * $current_stadium['revenue_multiplier']) + $fan_bonus;
                    ?>
                    <div class="flex justify-between">
                        <span>Expected Attendance:</span>
                        <span class="font-medium text-blue-600"><?php echo number_format($expected_attendance); ?>
                            (<?php echo number_format($attendance_percentage, 1); ?>%)</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Fan Revenue Bonus:</span>
                        <span class="font-medium text-blue-600"><?php echo formatMarketValue($fan_bonus); ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-1">
                        <span>Total per Match:</span>
                        <span class="font-medium text-green-600">
                            <?php echo formatMarketValue($total_revenue); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Stadium rename permission
    const canRename = <?php echo $can_rename_stadium ? 'true' : 'false'; ?>;

    // Get current stadium rename item quantity for display
    const renameItemsCount = <?php echo $current_rename_quantity; ?>;

    // Stadium upgrade functionality
    document.getElementById('upgradeStadium')?.addEventListener('click', function () {
        const cost = parseInt(this.dataset.cost);
        const level = parseInt(this.dataset.level);

        Swal.fire({
            title: 'Upgrade Stadium?',
            html: `
                <div class="text-left space-y-4">
                    <p>This will upgrade your stadium to level ${level}.</p>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Upgrade Cost:</span>
                            <span class="font-medium text-red-600">${formatMarketValue(cost)}</span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-gray-600">Your Budget:</span>
                            <span class="font-medium text-blue-600">${formatMarketValue(<?php echo $user_budget; ?>)
                }</span >
                        </div >
        <div class="flex justify-between items-center mt-1">
            <span class="text-gray-600">Remaining:</span>
            <span class="font-medium text-green-600">${formatMarketValue(<?php echo $user_budget; ?> - cost)}</span>
        </div>
                    </div >
                </div >
        `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Upgrade Stadium',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                upgradeStadium(cost, level);
            }
        });
    });

    // Stadium rename functionality
    document.getElementById('renameStadium').addEventListener('click', function () {
        // Check if rename is allowed
        if (this.disabled) {
            Swal.fire({
                icon: 'warning',
                title: 'Feature Locked',
                html: 'You need to purchase the "Stadium Name Change" item from the <a href="shop.php" class="text-blue-600 underline">Club Shop</a> to change your stadium name.',
                confirmButtonColor: '#f59e0b',
                confirmButtonText: 'Go to Shop'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'shop.php';
                }
            });
            return;
        }

        const newName = document.getElementById('stadiumName').value.trim();
        const currentName = '<?php echo addslashes($stadium_data['name']); ?>';

        if (!newName) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Name',
                text: 'Please enter a valid stadium name.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        if (newName.length > 100) {
            Swal.fire({
                icon: 'error',
                title: 'Name Too Long',
                text: 'Stadium name must be 100 characters or less.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        if (newName === currentName) {
            Swal.fire({
                icon: 'info',
                title: 'No Change',
                text: 'The new name is the same as the current name.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Show confirmation popup
        Swal.fire({
            title: 'Confirm Stadium Rename',
            html: `
        <div style = "text-align: left; padding: 10px;" >
                    <p style="margin-bottom: 15px; color: #374151;">Are you sure you want to rename your stadium?</p>
                    <div style="background-color: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="color: #6b7280;">Current Name:</span>
                            <span style="font-weight: 500; color: #111827;">${currentName}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #6b7280;">New Name:</span>
                            <span style="font-weight: 500; color: #2563eb;">${newName}</span>
                        </div>
                    </div>
                    <div style="background-color: #fefce8; padding: 12px; border-radius: 8px; border: 1px solid #fde047;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: #d97706;"></i>
                            <span style="font-size: 14px; color: #92400e;">This will consume 1 Stadium Name Change item.</span>
                        </div>
                        <div style="font-size: 12px; color: #78716c;">
                            You currently have ${renameItemsCount} Stadium Name Change item${renameItemsCount !== 1 ? 's' : ''}.
                        </div>
                    </div>
                </div >
        `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Rename Stadium',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                // Initialize Lucide icons in the popup
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                renameStadium(newName);
            }
        });
    });

    // Handle clicks on disabled stadium name input
    document.getElementById('stadiumName').addEventListener('click', function () {
        if (!canRename) {
            Swal.fire({
                icon: 'info',
                title: 'Stadium Name Locked',
                html: 'Purchase the "Stadium Name Change" item from the <a href="shop.php" class="text-blue-600 underline">Club Shop</a> to unlock stadium renaming.',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'Go to Shop'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'shop.php';
                }
            });
        }
    });

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

    // Upgrade stadium function
    function upgradeStadium(cost, level) {
        Swal.fire({
            title: 'Processing Upgrade...',
            text: 'Please wait while we upgrade your stadium',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('upgrade_stadium.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'upgrade',
                cost: cost,
                level: level
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Stadium Upgraded!',
                        text: `Your stadium has been upgraded to level ${level}!`,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upgrade Failed',
                        text: data.message || 'Failed to upgrade stadium. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to upgrade stadium. Please check your connection and try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Rename stadium function
    function renameStadium(newName) {
        fetch('upgrade_stadium.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'rename',
                name: newName
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Stadium Renamed!',
                        text: data.message || `Stadium renamed to "${newName}"`,
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    }).then(() => {
                        // Always reload the page to reflect changes
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Rename Failed',
                        text: data.message || 'Failed to rename stadium. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to rename stadium. Please check your connection and try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
    }

    // Initialize Lucide icons
    lucide.createIcons();
</script>

<?php
// End content capture and render layout
endContent('Stadium - Dream Team', 'stadium');
?>