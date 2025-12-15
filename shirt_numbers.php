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

// Require user to be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

try {
    $db = getDbConnection();

    // Get current user
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        header('Location: index.php');
        exit;
    }

    // Handle shirt number assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_number'])) {
        $player_uuid = $_POST['player_uuid'];
        $shirt_number = (int) $_POST['shirt_number'];
        $position = $_POST['position']; // 'team' or 'substitute'

        // Validate shirt number (1-99)
        if ($shirt_number < 1 || $shirt_number > 99) {
            $_SESSION['error'] = 'Shirt number must be between 1 and 99';
        } else {
            // Get current team and substitutes
            $team = json_decode($user['team'], true) ?: [];
            $substitutes = json_decode($user['substitutes'], true) ?: [];

            // Check if shirt number is already taken
            $number_taken = false;
            $taken_by = '';

            // Check in team
            foreach ($team as $player) {
                if ($player && isset($player['shirt_number']) && $player['shirt_number'] == $shirt_number) {
                    $number_taken = true;
                    $taken_by = $player['name'];
                    break;
                }
            }

            // Check in substitutes if not found in team
            if (!$number_taken) {
                foreach ($substitutes as $player) {
                    if ($player && isset($player['shirt_number']) && $player['shirt_number'] == $shirt_number) {
                        $number_taken = true;
                        $taken_by = $player['name'];
                        break;
                    }
                }
            }

            if ($number_taken) {
                $_SESSION['error'] = "Shirt number $shirt_number is already taken by $taken_by";
            } else {
                // Assign the shirt number
                $updated = false;

                if ($position === 'team') {
                    for ($i = 0; $i < count($team); $i++) {
                        if ($team[$i] && $team[$i]['uuid'] === $player_uuid) {
                            $team[$i]['shirt_number'] = $shirt_number;
                            $updated = true;
                            break;
                        }
                    }
                } else {
                    for ($i = 0; $i < count($substitutes); $i++) {
                        if ($substitutes[$i] && $substitutes[$i]['uuid'] === $player_uuid) {
                            $substitutes[$i]['shirt_number'] = $shirt_number;
                            $updated = true;
                            break;
                        }
                    }
                }

                if ($updated) {
                    // Update database
                    $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
                    $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
                    $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
                    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $stmt->execute();

                    $_SESSION['success'] = 'Shirt number assigned successfully!';
                } else {
                    $_SESSION['error'] = 'Player not found in your squad';
                }
            }
        }

        header('Location: shirt_numbers.php');
        exit;
    }

    // Handle shirt number removal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_number'])) {
        $player_uuid = $_POST['player_uuid'];
        $position = $_POST['position'];

        $team = json_decode($user['team'], true) ?: [];
        $substitutes = json_decode($user['substitutes'], true) ?: [];

        $updated = false;

        if ($position === 'team') {
            for ($i = 0; $i < count($team); $i++) {
                if ($team[$i] && $team[$i]['uuid'] === $player_uuid) {
                    unset($team[$i]['shirt_number']);
                    $updated = true;
                    break;
                }
            }
        } else {
            for ($i = 0; $i < count($substitutes); $i++) {
                if ($substitutes[$i] && $substitutes[$i]['uuid'] === $player_uuid) {
                    unset($substitutes[$i]['shirt_number']);
                    $updated = true;
                    break;
                }
            }
        }

        if ($updated) {
            $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
            $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
            $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();

            $_SESSION['success'] = 'Shirt number removed successfully!';
        }

        header('Location: shirt_numbers.php');
        exit;
    }

    // Get current team and substitutes
    $team = json_decode($user['team'], true) ?: [];
    $substitutes = json_decode($user['substitutes'], true) ?: [];

    // Get all used shirt numbers
    $used_numbers = [];
    foreach (array_merge($team, $substitutes) as $player) {
        if ($player && isset($player['shirt_number'])) {
            $used_numbers[] = $player['shirt_number'];
        }
    }

    $db->close();
} catch (Exception $e) {
    error_log("Shirt numbers page error: " . $e->getMessage());
    header('Location: welcome.php?error=page_unavailable');
    exit;
}

startContent();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="shirt" class="w-8 h-8 text-blue-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Shirt Numbers</h1>
                <p class="text-gray-600">Manage your squad's shirt numbers</p>
            </div>
        </div>
        <a href="team.php"
            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to Team
        </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                <span class="text-green-800"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center gap-2">
                <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                <span class="text-red-800"><?php echo htmlspecialchars($_SESSION['error']); ?></span>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Number Usage Overview -->
    <div class="mb-6 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Number Usage Overview</h3>
        <div class="grid grid-cols-10 gap-2">
            <?php for ($i = 1; $i <= 99; $i++): ?>
                <div class="w-8 h-8 rounded flex items-center justify-center text-xs font-medium <?php echo in_array($i, $used_numbers) ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>"
                    title="<?php echo in_array($i, $used_numbers) ? 'Taken' : 'Available'; ?>">
                    <?php echo $i; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="flex items-center gap-4 mt-4 text-sm">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-100 rounded"></div>
                <span class="text-gray-600">Available</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-100 rounded"></div>
                <span class="text-gray-600">Taken</span>
            </div>
        </div>
    </div>

    <!-- Main Team -->
    <div class="mb-8 bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-blue-200">
            <h3 class="text-lg font-semibold text-blue-900 flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5"></i>
                Main Team
            </h3>
        </div>
        <div class="p-6">
            <?php if (empty(array_filter($team))): ?>
                <div class="text-center py-8">
                    <i data-lucide="users-x" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <p class="text-gray-600">No players in your main team</p>
                    <a href="team.php" class="text-blue-600 hover:text-blue-800">Set up your team first</a>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($team as $index => $player): ?>
                        <?php if ($player): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold">
                                            <?php echo isset($player['shirt_number']) ? $player['shirt_number'] : '?'; ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold"><?php echo htmlspecialchars($player['name']); ?></h4>
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <span
                                                class="px-2 py-1 bg-gray-100 rounded"><?php echo htmlspecialchars($player['position']); ?></span>
                                            <span>Rating: <?php echo $player['rating']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if (isset($player['shirt_number'])): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="player_uuid"
                                                value="<?php echo htmlspecialchars($player['uuid']); ?>">
                                            <input type="hidden" name="position" value="team">
                                            <button type="submit" name="remove_number" class="text-red-600 hover:text-red-800 p-2"
                                                title="Remove shirt number">
                                                <i data-lucide="x" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button
                                            onclick="showAssignModal('<?php echo htmlspecialchars($player['uuid']); ?>', '<?php echo htmlspecialchars($player['name']); ?>', 'team')"
                                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                            Assign Number
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Substitutes -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-green-50 px-6 py-4 border-b border-green-200">
            <h3 class="text-lg font-semibold text-green-900 flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                Substitutes
            </h3>
        </div>
        <div class="p-6">
            <?php if (empty(array_filter($substitutes))): ?>
                <div class="text-center py-8">
                    <i data-lucide="user-x" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <p class="text-gray-600">No substitute players</p>
                    <a href="team.php" class="text-blue-600 hover:text-blue-800">Add substitutes</a>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($substitutes as $index => $player): ?>
                        <?php if ($player): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold">
                                            <?php echo isset($player['shirt_number']) ? $player['shirt_number'] : '?'; ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold"><?php echo htmlspecialchars($player['name']); ?></h4>
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <span
                                                class="px-2 py-1 bg-gray-100 rounded"><?php echo htmlspecialchars($player['position']); ?></span>
                                            <span>Rating: <?php echo $player['rating']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if (isset($player['shirt_number'])): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="player_uuid"
                                                value="<?php echo htmlspecialchars($player['uuid']); ?>">
                                            <input type="hidden" name="position" value="substitute">
                                            <button type="submit" name="remove_number" class="text-red-600 hover:text-red-800 p-2"
                                                title="Remove shirt number">
                                                <i data-lucide="x" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button
                                            onclick="showAssignModal('<?php echo htmlspecialchars($player['uuid']); ?>', '<?php echo htmlspecialchars($player['name']); ?>', 'substitute')"
                                            class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                                            Assign Number
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assign Number Modal -->
<div id="assignModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Assign Shirt Number</h3>
                <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" id="assignForm">
                <input type="hidden" name="player_uuid" id="modalPlayerUuid">
                <input type="hidden" name="position" id="modalPosition">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Player</label>
                    <p id="modalPlayerName" class="text-gray-900 font-medium"></p>
                </div>

                <div class="mb-6">
                    <label for="shirt_number" class="block text-sm font-medium text-gray-700 mb-2">Shirt Number
                        (1-99)</label>
                    <input type="number" name="shirt_number" id="shirt_number" min="1" max="99" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Choose an available number from the overview above</p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeAssignModal()"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" name="assign_number"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Assign Number
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showAssignModal(playerUuid, playerName, position) {
        document.getElementById('modalPlayerUuid').value = playerUuid;
        document.getElementById('modalPlayerName').textContent = playerName;
        document.getElementById('modalPosition').value = position;
        document.getElementById('shirt_number').value = '';
        document.getElementById('assignModal').classList.remove('hidden');
        document.getElementById('shirt_number').focus();
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('assignModal').addEventListener('click', function (e) {
        if (e.target.id === 'assignModal') {
            closeAssignModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAssignModal();
        }
    });

    lucide.createIcons();
</script>

<?php
endContent('Shirt Numbers - Dream Team', 'shirt_numbers');
?>