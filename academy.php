<?php
session_start();
require_once 'config.php';
require_once 'constants.php';
require_once 'helpers.php';
require_once 'layout.php';
require_once 'ads.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if database is available
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_player') {
        // Check if user can add more academy players
        $currentAcademyCount = count(getClubYoungPlayers($userId, 'academy'));
        $maxAcademyPlayers = getUserFeatureLimit($userId, 'max_academy_players');

        if ($currentAcademyCount >= $maxAcademyPlayers) {
            $message = 'You have reached the maximum number of academy players for your plan. Upgrade to add more players.';
            $messageType = 'error';
        } else {
            // Generate a new young player for the academy
            $youngPlayer = generateYoungPlayer($userId);

            $stmt = $db->prepare('INSERT INTO young_players (club_id, name, age, position, potential_rating, current_rating, development_stage, contract_years, value, training_focus) VALUES (:club_id, :name, :age, :position, :potential_rating, :current_rating, :development_stage, :contract_years, :value, :training_focus)');
            $stmt->bindValue(':club_id', $youngPlayer['club_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':name', $youngPlayer['name'], SQLITE3_TEXT);
            $stmt->bindValue(':age', $youngPlayer['age'], SQLITE3_INTEGER);
            $stmt->bindValue(':position', $youngPlayer['position'], SQLITE3_TEXT);
            $stmt->bindValue(':potential_rating', $youngPlayer['potential_rating'], SQLITE3_INTEGER);
            $stmt->bindValue(':current_rating', $youngPlayer['current_rating'], SQLITE3_INTEGER);
            $stmt->bindValue(':development_stage', $youngPlayer['development_stage'], SQLITE3_TEXT);
            $stmt->bindValue(':contract_years', $youngPlayer['contract_years'], SQLITE3_INTEGER);
            $stmt->bindValue(':value', $youngPlayer['value'], SQLITE3_INTEGER);
            $stmt->bindValue(':training_focus', $youngPlayer['training_focus'], SQLITE3_TEXT);

            if ($stmt->execute()) {
                $message = 'New young player added to academy!';
                $messageType = 'success';
            } else {
                $message = 'Failed to add young player to academy.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'promote_player') {
        $playerId = $_POST['player_id'] ?? 0;
        if (promoteYoungPlayer($playerId)) {
            $message = 'Player promoted to main team successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to promote player.';
            $messageType = 'error';
        }
    } elseif ($action === 'sell_player') {
        $playerId = $_POST['player_id'] ?? 0;
        $sellPrice = $_POST['sell_price'] ?? 0;

        // Get player data
        $stmt = $db->prepare('SELECT * FROM young_players WHERE id = :id AND club_id = :club_id');
        $stmt->bindValue(':id', $playerId, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $player = $result->fetchArray(SQLITE3_ASSOC);

        if ($player && $sellPrice > 0) {
            // Remove player from academy
            $stmt = $db->prepare('DELETE FROM young_players WHERE id = :id');
            $stmt->bindValue(':id', $playerId, SQLITE3_INTEGER);
            $stmt->execute();

            // Add money to budget
            $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :id');
            $stmt->bindValue(':amount', $sellPrice, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            $stmt->execute();

            $message = 'Young player sold for ' . formatMarketValue($sellPrice) . '!';
            $messageType = 'success';
        } else {
            $message = 'Failed to sell player.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_training') {
        $playerId = $_POST['player_id'] ?? 0;
        $trainingFocus = $_POST['training_focus'] ?? 'balanced';

        $stmt = $db->prepare('UPDATE young_players SET training_focus = :training_focus WHERE id = :id AND club_id = :club_id');
        $stmt->bindValue(':training_focus', $trainingFocus, SQLITE3_TEXT);
        $stmt->bindValue(':id', $playerId, SQLITE3_INTEGER);
        $stmt->bindValue(':club_id', $userId, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            $message = 'Training focus updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to update training focus.';
            $messageType = 'error';
        }
    } elseif ($action === 'accept_bid') {
        $bidId = $_POST['bid_id'] ?? 0;
        if (processYoungPlayerBid($bidId, 'accept', $userId)) {
            $message = 'Bid accepted! Player transferred successfully.';
            $messageType = 'success';
        } else {
            $message = 'Failed to accept bid.';
            $messageType = 'error';
        }
    } elseif ($action === 'reject_bid') {
        $bidId = $_POST['bid_id'] ?? 0;
        if (processYoungPlayerBid($bidId, 'reject', $userId)) {
            $message = 'Bid rejected.';
            $messageType = 'success';
        } else {
            $message = 'Failed to reject bid.';
            $messageType = 'error';
        }
    }
}

// Get club's young players
$academyPlayers = getClubYoungPlayers($userId, 'academy');
$promotedPlayers = getClubYoungPlayers($userId, 'promoted');

// Get pending bids
$pendingBids = getClubYoungPlayerBids($userId);

// Get user budget
$stmt = $db->prepare('SELECT budget FROM users WHERE id = :id');
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$userData = $result->fetchArray(SQLITE3_ASSOC);
$userBudget = $userData['budget'] ?? 0;

$db->close();

startContent();
?>

<div class="container mx-auto px-4 max-w-6xl py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <i data-lucide="graduation-cap" class="w-8 h-8 text-blue-600"></i>
                    <h1 class="text-3xl font-bold text-gray-900">Young Player Academy</h1>
                </div>
                <p class="text-gray-600">Develop the next generation of football stars</p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Club Budget</div>
                <div class="text-2xl font-bold text-green-600"><?php echo formatMarketValue($userBudget); ?></div>
            </div>
        </div>
    </div>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ads for free users -->
    <?php if (shouldShowAds($userId)): ?>
        <?php renderBannerAd('content', $userId); ?>
    <?php endif; ?>

    <!-- Plan upgrade prompt if user hit limits -->
    <?php
    $currentAcademyCount = count($academyPlayers);
    $maxAcademyPlayers = getUserFeatureLimit($userId, 'max_academy_players');
    if ($currentAcademyCount >= $maxAcademyPlayers && shouldShowAds($userId)):
        ?>
        <?php renderUpgradePrompt($userId, 'max_academy_players'); ?>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <style>
        .tab-button {
            transition: all 0.2s ease-in-out;
        }

        .tab-button:hover {
            border-color: #d1d5db !important;
            color: #374151 !important;
        }

        .tab-content {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-button.active {
            border-color: #3b82f6 !important;
            color: #2563eb !important;
        }
    </style>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="border-b border-gray-200">
            <nav class="flex space-x-4 md:space-x-8 px-4 md:px-6 overflow-x-auto" aria-label="Tabs">
                <button onclick="switchTab('overview')" id="tab-overview"
                    class="tab-button border-b-2 border-blue-500 text-blue-600 py-4 px-1 text-sm font-medium whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <i data-lucide="home" class="w-4 h-4"></i>
                        <span>Overview</span>
                    </div>
                </button>
                <button onclick="switchTab('academy')" id="tab-academy"
                    class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                        <span>Academy Players</span>
                        <?php if (count($academyPlayers) > 0): ?>
                            <span
                                class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo count($academyPlayers); ?></span>
                        <?php endif; ?>
                    </div>
                </button>
                <button onclick="switchTab('bids')" id="tab-bids"
                    class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                        <span>Pending Bids</span>
                        <?php if (count($pendingBids) > 0): ?>
                            <span
                                class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full"><?php echo count($pendingBids); ?></span>
                        <?php endif; ?>
                    </div>
                </button>
                <button onclick="switchTab('promoted')" id="tab-promoted"
                    class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <i data-lucide="trophy" class="w-4 h-4"></i>
                        <span>Promoted Players</span>
                        <?php if (count($promotedPlayers) > 0): ?>
                            <span
                                class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full"><?php echo count($promotedPlayers); ?></span>
                        <?php endif; ?>
                    </div>
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Overview Tab -->
            <div id="content-overview" class="tab-content">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <i data-lucide="user-plus" class="w-6 h-6 text-blue-600"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Scout New Talent</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Add a promising young player to your academy</p>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="generate_player">
                            <button type="submit"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <div class="flex items-center justify-center gap-2">
                                    <i data-lucide="search" class="w-4 h-4"></i>
                                    <span>Scout Player</span>
                                </div>
                            </button>
                        </form>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <i data-lucide="users" class="w-6 h-6 text-green-600"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Academy Players</h3>
                        </div>
                        <div class="text-3xl font-bold text-green-600 mb-2"><?php echo count($academyPlayers); ?></div>
                        <p class="text-gray-600 mb-3">Players in development</p>
                        <?php if (count($academyPlayers) > 0): ?>
                            <button onclick="switchTab('academy')"
                                class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                View Players →
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <i data-lucide="mail" class="w-6 h-6 text-orange-600"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Pending Bids</h3>
                        </div>
                        <div class="text-3xl font-bold text-orange-600 mb-2"><?php echo count($pendingBids); ?></div>
                        <p class="text-gray-600 mb-3">Offers from other clubs</p>
                        <?php if (count($pendingBids) > 0): ?>
                            <button onclick="switchTab('bids')"
                                class="text-sm text-orange-600 hover:text-orange-800 font-medium">
                                Review Bids →
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="young_player_market.php"
                        class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                        <i data-lucide="shopping-cart" class="w-6 h-6 text-blue-600"></i>
                        <div>
                            <h4 class="font-semibold text-blue-900">Young Player Market</h4>
                            <p class="text-sm text-blue-700">Browse and bid on players from other clubs</p>
                        </div>
                    </a>
                    <button onclick="switchTab('academy')"
                        class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors text-left">
                        <i data-lucide="graduation-cap" class="w-6 h-6 text-green-600"></i>
                        <div>
                            <h4 class="font-semibold text-green-900">Manage Academy</h4>
                            <p class="text-sm text-green-700">Train and develop your young players</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Academy Players Tab -->
            <div id="content-academy" class="tab-content hidden">
                <?php if (empty($academyPlayers)): ?>
                    <div class="text-center py-8">
                        <i data-lucide="users" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Academy Players</h3>
                        <p class="text-gray-600 mb-4">Start building your academy by scouting young talent</p>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="generate_player">
                            <button type="submit"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="search" class="w-4 h-4"></i>
                                    <span>Scout First Player</span>
                                </div>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($academyPlayers as $player): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                    </h4>
                                    <span
                                        class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"><?php echo htmlspecialchars($player['position']); ?></span>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Age:</span>
                                        <span class="font-medium"><?php echo $player['age']; ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Current Rating:</span>
                                        <span class="font-medium"><?php echo $player['current_rating']; ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Potential:</span>
                                        <span
                                            class="font-medium text-green-600"><?php echo $player['potential_rating']; ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Value:</span>
                                        <span class="font-medium"><?php echo formatMarketValue($player['value']); ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Contract:</span>
                                        <span class="font-medium"><?php echo $player['contract_years']; ?> years</span>
                                    </div>
                                </div>

                                <!-- Training Focus -->
                                <div class="mb-4">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Training Focus</label>
                                    <form method="POST" class="inline w-full">
                                        <input type="hidden" name="action" value="update_training">
                                        <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                        <select name="training_focus" onchange="this.form.submit()"
                                            class="w-full text-xs px-2 py-1 border border-gray-300 rounded">
                                            <option value="balanced" <?php echo $player['training_focus'] === 'balanced' ? 'selected' : ''; ?>>Balanced</option>
                                            <option value="technical" <?php echo $player['training_focus'] === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                            <option value="physical" <?php echo $player['training_focus'] === 'physical' ? 'selected' : ''; ?>>Physical</option>
                                            <option value="mental" <?php echo $player['training_focus'] === 'mental' ? 'selected' : ''; ?>>Mental</option>
                                        </select>
                                    </form>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="promote_player">
                                        <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                        <button type="submit"
                                            class="w-full px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                                            <div class="flex items-center justify-center gap-1">
                                                <i data-lucide="arrow-up" class="w-3 h-3"></i>
                                                <span>Promote</span>
                                            </div>
                                        </button>
                                    </form>
                                    <button
                                        onclick="showSellModal(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['name']); ?>', <?php echo $player['value']; ?>)"
                                        class="px-3 py-2 bg-orange-600 text-white text-sm rounded hover:bg-orange-700 transition-colors">
                                        <i data-lucide="dollar-sign" class="w-3 h-3"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Bids Tab -->
            <div id="content-bids" class="tab-content hidden">
                <?php if (empty($pendingBids)): ?>
                    <div class="text-center py-8">
                        <i data-lucide="mail" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Bids</h3>
                        <p class="text-gray-600">You don't have any pending bids from other clubs at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pendingBids as $bid): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($bid['player_name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($bid['position']); ?> • Age
                                            <?php echo $bid['age']; ?> • Potential <?php echo $bid['potential_rating']; ?>
                                        </p>
                                        <p class="text-sm text-gray-500">Bid from
                                            <?php echo htmlspecialchars($bid['bidder_club_name']); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-green-600">
                                            <?php echo formatMarketValue($bid['bid_amount']); ?>
                                        </div>
                                        <div class="flex gap-2 mt-2">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="accept_bid">
                                                <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                                <button type="submit"
                                                    class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">Accept</button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="reject_bid">
                                                <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                                <button type="submit"
                                                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Promoted Players Tab -->
            <div id="content-promoted" class="tab-content hidden">
                <?php if (empty($promotedPlayers)): ?>
                    <div class="text-center py-8">
                        <i data-lucide="trophy" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Promoted Players</h3>
                        <p class="text-gray-600">You haven't promoted any academy players to the main team yet.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($promotedPlayers as $player): ?>
                            <div class="border border-gray-200 rounded-lg p-4 bg-green-50">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?>
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"><?php echo htmlspecialchars($player['position']); ?></span>
                                        <i data-lucide="trophy" class="w-4 h-4 text-green-600"
                                            title="Promoted to main team"></i>
                                    </div>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Age:</span>
                                        <span class="font-medium"><?php echo $player['age']; ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Final Rating:</span>
                                        <span class="font-medium"><?php echo $player['current_rating']; ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Potential:</span>
                                        <span
                                            class="font-medium text-green-600"><?php echo $player['potential_rating']; ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Promoted:</span>
                                        <span
                                            class="font-medium"><?php echo date('M j, Y', strtotime($player['promoted_at'])); ?></span>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <span
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                                        <i data-lucide="check" class="w-3 h-3"></i>
                                        <span>In Main Team</span>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sell Player Modal -->
<div id="sellModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Sell Young Player</h3>
        <form method="POST" id="sellForm">
            <input type="hidden" name="action" value="sell_player">
            <input type="hidden" name="player_id" id="sellPlayerId">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Player</label>
                <div id="sellPlayerName" class="text-lg font-semibold text-gray-900"></div>
            </div>

            <div class="mb-6">
                <label for="sell_price" class="block text-sm font-medium text-gray-700 mb-2">Sell Price</label>
                <input type="number" name="sell_price" id="sellPrice"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required>
                <p class="text-xs text-gray-500 mt-1">Suggested price: <span id="suggestedPrice"></span></p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="hideSellModal()"
                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Sell
                    Player</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Tab switching functionality
    function switchTab(tabName) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active state from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => {
            button.classList.remove('border-blue-500', 'text-blue-600');
            button.classList.add('border-transparent', 'text-gray-500');
        });

        // Show selected tab content
        document.getElementById('content-' + tabName).classList.remove('hidden');

        // Add active state to selected tab button
        const activeButton = document.getElementById('tab-' + tabName);
        activeButton.classList.remove('border-transparent', 'text-gray-500');
        activeButton.classList.add('border-blue-500', 'text-blue-600');

        // Store active tab in localStorage
        localStorage.setItem('academyActiveTab', tabName);

        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }

    // Initialize tabs on page load
    document.addEventListener('DOMContentLoaded', function () {
        // Get saved tab or default to overview
        const savedTab = localStorage.getItem('academyActiveTab') || 'overview';
        switchTab(savedTab);

        // Re-initialize Lucide icons after tab switch
        lucide.createIcons();
    });

    function showSellModal(playerId, playerName, playerValue) {
        document.getElementById('sellPlayerId').value = playerId;
        document.getElementById('sellPlayerName').textContent = playerName;
        document.getElementById('sellPrice').value = playerValue;
        document.getElementById('suggestedPrice').textContent = '€' + playerValue.toLocaleString();
        document.getElementById('sellModal').classList.remove('hidden');
    }

    function hideSellModal() {
        document.getElementById('sellModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('sellModal').addEventListener('click', function (e) {
        if (e.target === this) {
            hideSellModal();
        }
    });

    // Handle URL parameters for direct tab access
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && ['overview', 'academy', 'bids', 'promoted'].includes(tabParam)) {
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function () {
            switchTab(tabParam);
        });
    }
</script>

<!-- Floating ad for free users -->
<?php if (shouldShowAds($userId)): ?>
    <?php renderFloatingAd($userId); ?>
<?php endif; ?>

<?php
endContent('Young Player Academy', 'academy');
?>