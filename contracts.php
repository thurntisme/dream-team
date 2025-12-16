<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

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

    // Handle contract renewal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_contract'])) {
        $player_uuid = $_POST['player_uuid'];
        $position = $_POST['position']; // 'team' or 'substitute'
        $contract_length = (int) $_POST['contract_length'];
        $salary_increase = (float) $_POST['salary_increase'];

        // Validate inputs
        if ($contract_length < 1 || $contract_length > 5) {
            $_SESSION['error'] = 'Contract length must be between 1 and 5 years';
        } elseif ($salary_increase < 0 || $salary_increase > 100) {
            $_SESSION['error'] = 'Salary increase must be between 0% and 100%';
        } else {
            // Get current team and substitutes
            $team = json_decode($user['team'], true) ?: [];
            $substitutes = json_decode($user['substitutes'], true) ?: [];

            $updated = false;
            $player_found = null;

            if ($position === 'team') {
                for ($i = 0; $i < count($team); $i++) {
                    if ($team[$i] && $team[$i]['uuid'] === $player_uuid) {
                        $player_found = &$team[$i];
                        break;
                    }
                }
            } else {
                for ($i = 0; $i < count($substitutes); $i++) {
                    if ($substitutes[$i] && $substitutes[$i]['uuid'] === $player_uuid) {
                        $player_found = &$substitutes[$i];
                        break;
                    }
                }
            }

            if ($player_found) {
                // Calculate new salary
                $current_salary = $player_found['salary'] ?? ($player_found['value'] * 0.1); // 10% of value if no salary set
                $new_salary = $current_salary * (1 + $salary_increase / 100);

                // Calculate total cost
                $total_cost = $new_salary * $contract_length;

                // Check if user has enough budget
                if ($user['budget'] < $total_cost) {
                    $_SESSION['error'] = 'Insufficient budget. Need ' . formatMarketValue($total_cost) . ' but only have ' . formatMarketValue($user['budget']);
                } else {
                    // Update player contract
                    $player_found['contract_matches'] = $contract_length * 38; // Assuming 38 matches per season
                    $player_found['contract_matches_remaining'] = $player_found['contract_matches'];
                    $player_found['salary'] = $new_salary;
                    $player_found['contract_renewed_date'] = date('Y-m-d');
                    $player_found['contract_years'] = $contract_length;

                    // Deduct cost from budget
                    $new_budget = $user['budget'] - $total_cost;

                    // Update database
                    $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes, budget = :budget WHERE id = :user_id');
                    $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
                    $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
                    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $stmt->execute();

                    $_SESSION['success'] = 'Contract renewed successfully! Cost: ' . formatMarketValue($total_cost);
                    $updated = true;
                }
            } else {
                $_SESSION['error'] = 'Player not found in your squad';
            }
        }

        header('Location: contracts.php');
        exit;
    }

    // Handle contract termination
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminate_contract'])) {
        $player_uuid = $_POST['player_uuid'];
        $position = $_POST['position'];

        $team = json_decode($user['team'], true) ?: [];
        $substitutes = json_decode($user['substitutes'], true) ?: [];

        $updated = false;
        $player_found = null;

        if ($position === 'team') {
            for ($i = 0; $i < count($team); $i++) {
                if ($team[$i] && $team[$i]['uuid'] === $player_uuid) {
                    // Remove player from team
                    $team[$i] = null;
                    $updated = true;
                    break;
                }
            }
        } else {
            for ($i = 0; $i < count($substitutes); $i++) {
                if ($substitutes[$i] && $substitutes[$i]['uuid'] === $player_uuid) {
                    // Remove player from substitutes
                    $substitutes[$i] = null;
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

            $_SESSION['success'] = 'Contract terminated successfully!';
        }

        header('Location: contracts.php');
        exit;
    }

    // Refresh user data after potential updates
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Get current team and substitutes
    $team = json_decode($user['team'], true) ?: [];
    $substitutes = json_decode($user['substitutes'], true) ?: [];

    // Calculate contract statistics
    $total_players = count(array_filter($team)) + count(array_filter($substitutes));
    $expiring_soon = 0;
    $total_salary_cost = 0;

    foreach (array_merge($team, $substitutes) as $player) {
        if ($player) {
            $matches_remaining = $player['contract_matches_remaining'] ?? 0;
            if ($matches_remaining <= 10 && $matches_remaining > 0) {
                $expiring_soon++;
            }
            $total_salary_cost += $player['salary'] ?? ($player['value'] * 0.1);
        }
    }

    $db->close();
} catch (Exception $e) {
    error_log("Contracts page error: " . $e->getMessage());
    header('Location: welcome.php?error=page_unavailable');
    exit;
}

startContent();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="file-text" class="w-8 h-8 text-green-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Player Contracts</h1>
                <p class="text-gray-600">Manage your squad's contracts and salaries</p>
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

    <!-- Contract Overview -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Players</p>
                    <p class="text-2xl font-bold"><?php echo $total_players; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="clock" class="w-6 h-6 text-red-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Expiring Soon</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $expiring_soon; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="coins" class="w-6 h-6 text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Salary Cost</p>
                    <p class="text-lg font-bold"><?php echo formatMarketValue($total_salary_cost); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="wallet" class="w-6 h-6 text-purple-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Available Budget</p>
                    <p class="text-lg font-bold"><?php echo formatMarketValue($user['budget']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Team Contracts -->
    <div class="mb-8 bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-blue-50 px-6 py-4 border-b border-blue-200">
            <h3 class="text-lg font-semibold text-blue-900 flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5"></i>
                Main Team Contracts
            </h3>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty(array_filter($team))): ?>
                <div class="p-8 text-center">
                    <i data-lucide="users-x" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <p class="text-gray-600">No players in your main team</p>
                    <a href="team.php" class="text-blue-600 hover:text-blue-800">Set up your team first</a>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Player</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Position</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Salary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contract</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($team as $index => $player): ?>
                            <?php if ($player): ?>
                                <?php
                                $matches_remaining = $player['contract_matches_remaining'] ?? rand(10, 50);
                                $salary = $player['salary'] ?? ($player['value'] * 0.1);
                                $contract_years = $player['contract_years'] ?? 2;
                                $status = 'Active';
                                $status_color = 'text-green-600';

                                if ($matches_remaining <= 10 && $matches_remaining > 0) {
                                    $status = 'Expiring Soon';
                                    $status_color = 'text-red-600';
                                } elseif ($matches_remaining <= 0) {
                                    $status = 'Expired';
                                    $status_color = 'text-red-600';
                                }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-bold text-sm"><?php echo $player['rating']; ?></span>
                                            </div>
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                                                <div class="text-sm text-gray-500">Rating: <?php echo $player['rating']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-1 bg-gray-100 rounded text-sm"><?php echo htmlspecialchars($player['position']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo formatMarketValue($salary); ?></div>
                                        <div class="text-sm text-gray-500">per year</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo $contract_years; ?> years</div>
                                        <div class="text-sm text-gray-500"><?php echo $matches_remaining; ?> matches left</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-medium <?php echo $status_color; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <button
                                                onclick="showRenewModal('<?php echo htmlspecialchars($player['uuid']); ?>', '<?php echo htmlspecialchars($player['name']); ?>', 'team', <?php echo $salary; ?>)"
                                                class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                                                Renew
                                            </button>
                                            <button
                                                onclick="confirmTerminate('<?php echo htmlspecialchars($player['uuid']); ?>', '<?php echo htmlspecialchars($player['name']); ?>', 'team')"
                                                class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                Terminate
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Substitute Contracts -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-green-50 px-6 py-4 border-b border-green-200">
            <h3 class="text-lg font-semibold text-green-900 flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                Substitute Contracts
            </h3>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty(array_filter($substitutes))): ?>
                <div class="p-8 text-center">
                    <i data-lucide="user-x" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <p class="text-gray-600">No substitute players</p>
                    <a href="team.php" class="text-blue-600 hover:text-blue-800">Add substitutes</a>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Player</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Position</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Salary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contract</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($substitutes as $index => $player): ?>
                            <?php if ($player): ?>
                                <?php
                                $matches_remaining = $player['contract_matches_remaining'] ?? rand(10, 50);
                                $salary = $player['salary'] ?? ($player['value'] * 0.1);
                                $contract_years = $player['contract_years'] ?? 2;
                                $status = 'Active';
                                $status_color = 'text-green-600';

                                if ($matches_remaining <= 10 && $matches_remaining > 0) {
                                    $status = 'Expiring Soon';
                                    $status_color = 'text-red-600';
                                } elseif ($matches_remaining <= 0) {
                                    $status = 'Expired';
                                    $status_color = 'text-red-600';
                                }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-bold text-sm"><?php echo $player['rating']; ?></span>
                                            </div>
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                                                <div class="text-sm text-gray-500">Rating: <?php echo $player['rating']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-1 bg-gray-100 rounded text-sm"><?php echo htmlspecialchars($player['position']); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo formatMarketValue($salary); ?></div>
                                        <div class="text-sm text-gray-500">per year</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo $contract_years; ?> years</div>
                                        <div class="text-sm text-gray-500"><?php echo $matches_remaining; ?> matches left</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-medium <?php echo $status_color; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <button
                                                onclick="showRenewModal('<?php echo htmlspecialchars($player['uuid']); ?>', '<?php echo htmlspecialchars($player['name']); ?>', 'substitute', <?php echo $salary; ?>)"
                                                class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                                                Renew
                                            </button>
                                            <button
                                                onclick="confirmTerminate('<?php echo htmlspecialchars($player['uuid']); ?>', '<?php echo htmlspecialchars($player['name']); ?>', 'substitute')"
                                                class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                Terminate
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Renew Contract Modal -->
<div id="renewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Renew Contract</h3>
                <button onclick="closeRenewModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" id="renewForm">
                <input type="hidden" name="player_uuid" id="renewPlayerUuid">
                <input type="hidden" name="position" id="renewPosition">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Player</label>
                    <p id="renewPlayerName" class="text-gray-900 font-medium"></p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Salary</label>
                    <p id="currentSalary" class="text-gray-900"></p>
                </div>

                <div class="mb-4">
                    <label for="contract_length" class="block text-sm font-medium text-gray-700 mb-2">Contract Length
                        (years)</label>
                    <select name="contract_length" id="contract_length" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="1">1 Year</option>
                        <option value="2" selected>2 Years</option>
                        <option value="3">3 Years</option>
                        <option value="4">4 Years</option>
                        <option value="5">5 Years</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="salary_increase" class="block text-sm font-medium text-gray-700 mb-2">Salary Increase
                        (%)</label>
                    <input type="number" name="salary_increase" id="salary_increase" min="0" max="100" value="10"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-1">0% = same salary, 10% = 10% increase</p>
                </div>

                <div class="mb-6 p-3 bg-gray-50 rounded-lg">
                    <div class="flex justify-between text-sm">
                        <span>New Salary:</span>
                        <span id="newSalary" class="font-medium"></span>
                    </div>
                    <div class="flex justify-between text-sm mt-1">
                        <span>Total Cost:</span>
                        <span id="totalCost" class="font-medium"></span>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeRenewModal()"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button type="submit" name="renew_contract"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Renew Contract
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentSalaryValue = 0;

    function showRenewModal(playerUuid, playerName, position, salary) {
        currentSalaryValue = salary;
        document.getElementById('renewPlayerUuid').value = playerUuid;
        document.getElementById('renewPlayerName').textContent = playerName;
        document.getElementById('renewPosition').value = position;
        document.getElementById('currentSalary').textContent = formatMarketValue(salary);
        document.getElementById('renewModal').classList.remove('hidden');
        updateCostCalculation();
    }

    function closeRenewModal() {
        document.getElementById('renewModal').classList.add('hidden');
    }

    function updateCostCalculation() {
        const contractLength = parseInt(document.getElementById('contract_length').value);
        const salaryIncrease = parseFloat(document.getElementById('salary_increase').value);

        const newSalary = currentSalaryValue * (1 + salaryIncrease / 100);
        const totalCost = newSalary * contractLength;

        document.getElementById('newSalary').textContent = formatMarketValue(newSalary);
        document.getElementById('totalCost').textContent = formatMarketValue(totalCost);
    }

    function confirmTerminate(playerUuid, playerName, position) {
        Swal.fire({
            title: 'Terminate Contract?',
            html: `Are you sure you want to terminate <strong>${playerName}</strong>'s contract?<br><small>This action cannot be undone.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, terminate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="player_uuid" value="${playerUuid}">
                    <input type="hidden" name="position" value="${position}">
                    <input type="hidden" name="terminate_contract" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + (value / 1000).toFixed(0) + 'K';
        } else {
            return '€' + value;
        }
    }

    // Event listeners for cost calculation
    document.getElementById('contract_length').addEventListener('change', updateCostCalculation);
    document.getElementById('salary_increase').addEventListener('input', updateCostCalculation);

    // Close modal when clicking outside
    document.getElementById('renewModal').addEventListener('click', function (e) {
        if (e.target.id === 'renewModal') {
            closeRenewModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeRenewModal();
        }
    });

    lucide.createIcons();
</script>

<?php
endContent('Player Contracts - Dream Team', 'contracts');
?>