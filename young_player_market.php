<?php
session_start();
require_once 'config.php';
require_once 'constants.php';
require_once 'helpers.php';
require_once 'layout.php';

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

    if ($action === 'place_bid') {
        $playerId = $_POST['player_id'] ?? 0;
        $bidAmount = $_POST['bid_amount'] ?? 0;

        if (createYoungPlayerBid($playerId, $userId, $bidAmount)) {
            $message = 'Bid placed successfully! The club owner has 48 hours to respond.';
            $messageType = 'success';
        } else {
            $message = 'Failed to place bid. Check your budget and try again.';
            $messageType = 'error';
        }
    }
}

// Get available young players from other clubs
$availablePlayers = getAvailableYoungPlayers($userId);

// Get user budget
$stmt = $db->prepare('SELECT budget FROM users WHERE id = :id');
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$userData = $result->fetchArray(SQLITE3_ASSOC);
$userBudget = $userData['budget'] ?? 0;

// Get user's pending bids
$stmt = $db->prepare('
    SELECT b.*, yp.name as player_name, yp.position, yp.age, yp.potential_rating, 
           u.club_name as owner_club_name
    FROM young_player_bids b
    JOIN young_players yp ON b.young_player_id = yp.id
    JOIN users u ON b.owner_club_id = u.id
    WHERE b.bidder_club_id = :club_id AND b.status = "pending" AND b.expires_at > datetime("now")
    ORDER BY b.created_at DESC
');
$stmt->bindValue(':club_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$myBids = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $myBids[] = $row;
}

$db->close();

startContent();
?>

<div class="container mx-auto px-4 max-w-6xl py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <i data-lucide="shopping-cart" class="w-8 h-8 text-blue-600"></i>
                    <h1 class="text-3xl font-bold text-gray-900">Young Player Market</h1>
                </div>
                <p class="text-gray-600">Discover and bid on promising young talents from other clubs</p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Available Budget</div>
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

    <!-- Navigation -->
    <div class="mb-6">
        <div class="flex gap-4">
            <a href="academy.php"
                class="px-4 py-2 text-gray-600 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-600 transition-colors">
                <div class="flex items-center gap-2">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                    <span>My Academy</span>
                </div>
            </a>
            <div class="px-4 py-2 text-blue-600 border-b-2 border-blue-600">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                    <span>Market</span>
                </div>
            </div>
        </div>
    </div>

    <!-- My Pending Bids -->
    <?php if (!empty($myBids)): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <i data-lucide="clock" class="w-6 h-6 text-orange-600"></i>
                    <h2 class="text-xl font-semibold text-gray-900">My Pending Bids</h2>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($myBids as $bid): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($bid['player_name']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($bid['position']); ?> • Age
                                        <?php echo $bid['age']; ?> • Potential <?php echo $bid['potential_rating']; ?></p>
                                    <p class="text-sm text-gray-500">From
                                        <?php echo htmlspecialchars($bid['owner_club_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-blue-600">
                                        <?php echo formatMarketValue($bid['bid_amount']); ?></div>
                                    <div class="text-xs text-gray-500">
                                        Expires: <?php echo date('M j, H:i', strtotime($bid['expires_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Available Players -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                <h2 class="text-xl font-semibold text-gray-900">Available Young Players</h2>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($availablePlayers)): ?>
                <div class="text-center py-8">
                    <i data-lucide="search" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Players Available</h3>
                    <p class="text-gray-600">There are currently no young players available for bidding from other clubs.
                    </p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($availablePlayers as $player): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($player['name']); ?></h4>
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
                                    <span class="font-medium text-green-600"><?php echo $player['potential_rating']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Market Value:</span>
                                    <span class="font-medium"><?php echo formatMarketValue($player['value']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Owner:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($player['owner_club_name']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Training:</span>
                                    <span
                                        class="font-medium capitalize"><?php echo htmlspecialchars($player['training_focus']); ?></span>
                                </div>
                            </div>

                            <!-- Potential Rating Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between text-xs text-gray-600 mb-1">
                                    <span>Potential</span>
                                    <span><?php echo $player['potential_rating']; ?>/100</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full"
                                        style="width: <?php echo $player['potential_rating']; ?>%"></div>
                                </div>
                            </div>

                            <!-- Bid Button -->
                            <button
                                onclick="showBidModal(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['name']); ?>', <?php echo $player['value']; ?>, '<?php echo htmlspecialchars($player['owner_club_name']); ?>')"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <div class="flex items-center justify-center gap-2">
                                    <i data-lucide="hand-coins" class="w-4 h-4"></i>
                                    <span>Place Bid</span>
                                </div>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bid Modal -->
<div id="bidModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Place Bid</h3>
        <form method="POST" id="bidForm">
            <input type="hidden" name="action" value="place_bid">
            <input type="hidden" name="player_id" id="bidPlayerId">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Player</label>
                <div id="bidPlayerName" class="text-lg font-semibold text-gray-900"></div>
                <div id="bidPlayerOwner" class="text-sm text-gray-600"></div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Market Value</label>
                <div id="bidPlayerValue" class="text-lg font-medium text-green-600"></div>
            </div>

            <div class="mb-6">
                <label for="bid_amount" class="block text-sm font-medium text-gray-700 mb-2">Your Bid Amount</label>
                <input type="number" name="bid_amount" id="bidAmount"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required>
                <p class="text-xs text-gray-500 mt-1">Your budget: <?php echo formatMarketValue($userBudget); ?></p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="hideBidModal()"
                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Place
                    Bid</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();

    function showBidModal(playerId, playerName, playerValue, ownerClub) {
        document.getElementById('bidPlayerId').value = playerId;
        document.getElementById('bidPlayerName').textContent = playerName;
        document.getElementById('bidPlayerOwner').textContent = 'Owned by ' + ownerClub;
        document.getElementById('bidPlayerValue').textContent = '€' + playerValue.toLocaleString();
        document.getElementById('bidAmount').value = Math.round(playerValue * 1.1); // Suggest 10% above market value
        document.getElementById('bidModal').classList.remove('hidden');
    }

    function hideBidModal() {
        document.getElementById('bidModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('bidModal').addEventListener('click', function (e) {
        if (e.target === this) {
            hideBidModal();
        }
    });

    // Validate bid amount
    document.getElementById('bidForm').addEventListener('submit', function (e) {
        const bidAmount = parseInt(document.getElementById('bidAmount').value);
        const userBudget = <?php echo $userBudget; ?>;

        if (bidAmount > userBudget) {
            e.preventDefault();
            alert('Bid amount exceeds your available budget!');
        }
    });
</script>

<?php
endContent('Young Player Market', 'young_player_market');
?>