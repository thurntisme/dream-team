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
requireClubName('staff');

try {
    $db = getDbConnection();

    // Get user data
    $stmt = $db->prepare('SELECT name, email, club_name, budget FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $user_budget = $user['budget'] ?? DEFAULT_BUDGET;

    // Create staff table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS club_staff (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        staff_type TEXT NOT NULL,
        name TEXT NOT NULL,
        level INTEGER DEFAULT 1,
        salary INTEGER NOT NULL,
        contract_weeks INTEGER DEFAULT 52,
        contract_weeks_remaining INTEGER DEFAULT 52,
        hired_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        bonus_applied_this_week BOOLEAN DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // Handle staff actions
    $message = '';
    $message_type = 'info';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['hire_staff'])) {
            $staff_type = $_POST['staff_type'];
            $staff_name = $_POST['staff_name'];
            $staff_level = (int) $_POST['staff_level'];

            $staff_costs = getStaffCosts();
            $cost = $staff_costs[$staff_type]['levels'][$staff_level]['cost'];
            $salary = $staff_costs[$staff_type]['levels'][$staff_level]['salary'];

            if ($user_budget >= $cost) {
                // Check if user already has this type of staff
                $stmt = $db->prepare('SELECT COUNT(*) as count FROM club_staff WHERE user_id = :user_id AND staff_type = :staff_type');
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':staff_type', $staff_type, SQLITE3_TEXT);
                $result = $stmt->execute();
                $existing = $result->fetchArray(SQLITE3_ASSOC);

                if ($existing['count'] > 0) {
                    $message = 'You already have a ' . ucfirst(str_replace('_', ' ', $staff_type)) . '. Fire them first to hire a new one.';
                    $message_type = 'error';
                } else {
                    // Hire staff
                    $stmt = $db->prepare('INSERT INTO club_staff (user_id, staff_type, name, level, salary) VALUES (:user_id, :staff_type, :name, :level, :salary)');
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $stmt->bindValue(':staff_type', $staff_type, SQLITE3_TEXT);
                    $stmt->bindValue(':name', $staff_name, SQLITE3_TEXT);
                    $stmt->bindValue(':level', $staff_level, SQLITE3_INTEGER);
                    $stmt->bindValue(':salary', $salary, SQLITE3_INTEGER);
                    $stmt->execute();

                    // Deduct cost from budget
                    $new_budget = $user_budget - $cost;
                    $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $stmt->execute();

                    $user_budget = $new_budget;
                    $message = ucfirst(str_replace('_', ' ', $staff_type)) . ' ' . $staff_name . ' hired successfully!';
                    $message_type = 'success';
                }
            } else {
                $message = 'Insufficient funds to hire this staff member.';
                $message_type = 'error';
            }
        } elseif (isset($_POST['fire_staff'])) {
            $staff_id = (int) $_POST['staff_id'];

            // Get staff details for severance pay
            $stmt = $db->prepare('SELECT * FROM club_staff WHERE id = :id AND user_id = :user_id');
            $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $staff = $result->fetchArray(SQLITE3_ASSOC);

            if ($staff) {
                // Calculate severance pay (2 weeks salary)
                $severance = $staff['salary'] * 2;

                if ($user_budget >= $severance) {
                    // Fire staff
                    $stmt = $db->prepare('DELETE FROM club_staff WHERE id = :id AND user_id = :user_id');
                    $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $stmt->execute();

                    // Deduct severance from budget
                    $new_budget = $user_budget - $severance;
                    $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $stmt->execute();

                    $user_budget = $new_budget;
                    $message = $staff['name'] . ' has been fired. Severance pay: ' . formatMarketValue($severance);
                    $message_type = 'success';
                } else {
                    $message = 'Insufficient funds for severance pay (' . formatMarketValue($severance) . ')';
                    $message_type = 'error';
                }
            }
        } elseif (isset($_POST['renew_contract'])) {
            $staff_id = (int) $_POST['staff_id'];

            $stmt = $db->prepare('SELECT * FROM club_staff WHERE id = :id AND user_id = :user_id');
            $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $staff = $result->fetchArray(SQLITE3_ASSOC);

            if ($staff) {
                // Contract renewal cost (4 weeks salary)
                $renewal_cost = $staff['salary'] * 4;

                if ($user_budget >= $renewal_cost) {
                    // Renew contract
                    $stmt = $db->prepare('UPDATE club_staff SET contract_weeks_remaining = 52 WHERE id = :id AND user_id = :user_id');
                    $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $stmt->execute();

                    // Deduct cost from budget
                    $new_budget = $user_budget - $renewal_cost;
                    $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                    $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $stmt->execute();

                    $user_budget = $new_budget;
                    $message = $staff['name'] . '\'s contract renewed for 1 year!';
                    $message_type = 'success';
                } else {
                    $message = 'Insufficient funds for contract renewal (' . formatMarketValue($renewal_cost) . ')';
                    $message_type = 'error';
                }
            }
        } elseif (isset($_POST['upgrade_staff'])) {
            $staff_id = (int) $_POST['staff_id'];
            $new_level = (int) $_POST['new_level'];

            // Debug logging
            error_log("=== UPGRADE STAFF REQUEST ===");
            error_log("Staff ID: $staff_id, New Level: $new_level, User ID: " . $_SESSION['user_id']);
            error_log("POST data: " . print_r($_POST, true));

            $stmt = $db->prepare('SELECT * FROM club_staff WHERE id = :id AND user_id = :user_id');
            $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $staff = $result->fetchArray(SQLITE3_ASSOC);

            if (!$staff) {
                $message = 'Staff member not found or access denied.';
                $message_type = 'error';
                error_log("Staff not found for upgrade - Staff ID: $staff_id, User ID: " . $_SESSION['user_id']);
            } elseif ($new_level <= $staff['level']) {
                $message = 'Invalid upgrade level. New level must be higher than current level ' . $staff['level'];
                $message_type = 'error';
                error_log("Invalid upgrade level - Current: {$staff['level']}, New: $new_level");
            } elseif ($new_level > 5) {
                $message = 'Maximum staff level is 5.';
                $message_type = 'error';
            } else {
                $staff_costs = getStaffCosts();
                $current_level_config = $staff_costs[$staff['staff_type']]['levels'][$staff['level']];
                $new_level_config = $staff_costs[$staff['staff_type']]['levels'][$new_level];

                // Calculate upgrade cost (difference between levels)
                $upgrade_cost = $new_level_config['cost'] - $current_level_config['cost'];

                error_log("Upgrade cost calculation - Current cost: {$current_level_config['cost']}, New cost: {$new_level_config['cost']}, Upgrade cost: $upgrade_cost, User budget: $user_budget");

                if ($user_budget >= $upgrade_cost) {
                    // Upgrade staff
                    $stmt = $db->prepare('UPDATE club_staff SET level = :level, salary = :salary WHERE id = :id AND user_id = :user_id');
                    $stmt->bindValue(':level', $new_level, SQLITE3_INTEGER);
                    $stmt->bindValue(':salary', $new_level_config['salary'], SQLITE3_INTEGER);
                    $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                    $upgrade_result = $stmt->execute();

                    if ($upgrade_result) {
                        // Deduct upgrade cost from budget
                        $new_budget = $user_budget - $upgrade_cost;
                        $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                        $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                        $budget_result = $stmt->execute();

                        if ($budget_result) {
                            $user_budget = $new_budget;
                            $message = $staff['name'] . ' upgraded to Level ' . $new_level . '! Cost: ' . formatMarketValue($upgrade_cost);
                            $message_type = 'success';
                            error_log("Staff upgrade successful - Staff: {$staff['name']}, Level: $new_level, Cost: $upgrade_cost");
                        } else {
                            $message = 'Failed to update budget. Please try again.';
                            $message_type = 'error';
                            error_log("Budget update failed during staff upgrade");
                        }
                    } else {
                        $message = 'Failed to upgrade staff. Please try again.';
                        $message_type = 'error';
                        error_log("Staff upgrade query failed");
                    }
                } else {
                    $message = 'Insufficient funds for upgrade (' . formatMarketValue($upgrade_cost) . ')';
                    $message_type = 'error';
                    error_log("Insufficient funds for upgrade - Need: $upgrade_cost, Have: $user_budget");
                }
            }
        } elseif (isset($_POST['downgrade_staff'])) {
            $staff_id = (int) $_POST['staff_id'];
            $new_level = (int) $_POST['new_level'];

            // Debug logging
            error_log("=== DOWNGRADE STAFF REQUEST ===");
            error_log("Staff ID: $staff_id, New Level: $new_level, User ID: " . $_SESSION['user_id']);
            error_log("POST data: " . print_r($_POST, true));

            $stmt = $db->prepare('SELECT * FROM club_staff WHERE id = :id AND user_id = :user_id');
            $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $staff = $result->fetchArray(SQLITE3_ASSOC);

            if ($staff && $new_level < $staff['level'] && $new_level >= 1) {
                $staff_costs = getStaffCosts();
                $current_level_config = $staff_costs[$staff['staff_type']]['levels'][$staff['level']];
                $new_level_config = $staff_costs[$staff['staff_type']]['levels'][$new_level];

                // Calculate refund (50% of the difference)
                $level_difference = $current_level_config['cost'] - $new_level_config['cost'];
                $refund = (int) ($level_difference * 0.5);

                // Downgrade staff
                $stmt = $db->prepare('UPDATE club_staff SET level = :level, salary = :salary WHERE id = :id AND user_id = :user_id');
                $stmt->bindValue(':level', $new_level, SQLITE3_INTEGER);
                $stmt->bindValue(':salary', $new_level_config['salary'], SQLITE3_INTEGER);
                $stmt->bindValue(':id', $staff_id, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                // Add refund to budget
                $new_budget = $user_budget + $refund;
                $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                $user_budget = $new_budget;
                $message = $staff['name'] . ' downgraded to Level ' . $new_level . '. Refund: ' . formatMarketValue($refund) . ' (50% of difference)';
                $message_type = 'success';
            }
        }
    }

    // Get current staff
    $stmt = $db->prepare('SELECT * FROM club_staff WHERE user_id = :user_id ORDER BY staff_type, level DESC');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $current_staff = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $current_staff[] = $row;
    }

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Staff configuration with available candidates
function getStaffCosts()
{
    return [
        'head_coach' => [
            'name' => 'Head Coach',
            'description' => 'Improves team performance and player development',
            'icon' => 'user-check',
            'candidates' => [
                ['name' => 'Marcus Rodriguez', 'specialty' => 'Defensive tactics', 'experience' => 'Elite'],
                ['name' => 'Alessandro Fontana', 'specialty' => 'Possession football', 'experience' => 'Elite'],
                ['name' => 'Viktor Petrov', 'specialty' => 'High-intensity pressing', 'experience' => 'Elite'],
                ['name' => 'Giovanni Rossi', 'specialty' => 'Man management', 'experience' => 'Elite'],
                ['name' => 'Diego Martinez', 'specialty' => 'Tactical discipline', 'experience' => 'Elite'],
                ['name' => 'Carlos Mendoza', 'specialty' => 'Counter-attacking', 'experience' => 'Elite'],
                ['name' => 'Stefan Mueller', 'specialty' => 'Flexible formations', 'experience' => 'Elite'],
                ['name' => 'Laurent Dubois', 'specialty' => 'Player motivation', 'experience' => 'Elite']
            ],
            'levels' => [
                1 => ['cost' => 5000000, 'salary' => 100000, 'bonus' => 'Team gets +2 overall rating'],
                2 => ['cost' => 15000000, 'salary' => 250000, 'bonus' => 'Team gets +4 overall rating'],
                3 => ['cost' => 35000000, 'salary' => 500000, 'bonus' => 'Team gets +6 overall rating'],
                4 => ['cost' => 75000000, 'salary' => 1000000, 'bonus' => 'Team gets +8 overall rating'],
                5 => ['cost' => 150000000, 'salary' => 2000000, 'bonus' => 'Team gets +10 overall rating']
            ]
        ],
        'fitness_coach' => [
            'name' => 'Fitness Coach',
            'description' => 'Reduces player fatigue and injury risk',
            'icon' => 'dumbbell',
            'candidates' => [
                ['name' => 'Roberto Silva', 'specialty' => 'Injury prevention', 'experience' => 'Elite'],
                ['name' => 'Michael Thompson', 'specialty' => 'High-intensity training', 'experience' => 'Elite'],
                ['name' => 'Hans Bergmann', 'specialty' => 'Periodization', 'experience' => 'Elite'],
                ['name' => 'Paulo Santos', 'specialty' => 'Recovery methods', 'experience' => 'Elite'],
                ['name' => 'Andrea Bianchi', 'specialty' => 'Strength conditioning', 'experience' => 'Elite'],
                ['name' => 'Fernando Lopez', 'specialty' => 'Endurance training', 'experience' => 'Elite']
            ],
            'levels' => [
                1 => ['cost' => 3000000, 'salary' => 75000, 'bonus' => 'Players lose 20% less fitness'],
                2 => ['cost' => 8000000, 'salary' => 150000, 'bonus' => 'Players lose 35% less fitness'],
                3 => ['cost' => 20000000, 'salary' => 300000, 'bonus' => 'Players lose 50% less fitness'],
                4 => ['cost' => 45000000, 'salary' => 600000, 'bonus' => 'Players lose 65% less fitness'],
                5 => ['cost' => 100000000, 'salary' => 1200000, 'bonus' => 'Players lose 80% less fitness']
            ]
        ],
        'scout' => [
            'name' => 'Scout',
            'description' => 'Discovers better players and provides detailed reports',
            'icon' => 'search',
            'candidates' => [
                ['name' => 'Eduardo Campos', 'specialty' => 'Young talent identification', 'experience' => 'Elite'],
                ['name' => 'James Mitchell', 'specialty' => 'Data-driven scouting', 'experience' => 'Elite'],
                ['name' => 'Antonio Benitez', 'specialty' => 'Technical players', 'experience' => 'Elite'],
                ['name' => 'Ricardo Montes', 'specialty' => 'Value signings', 'experience' => 'Elite'],
                ['name' => 'David Edwards', 'specialty' => 'Market analysis', 'experience' => 'Elite'],
                ['name' => 'Marco Bertoli', 'specialty' => 'South American talent', 'experience' => 'Elite']
            ],
            'levels' => [
                1 => ['cost' => 2000000, 'salary' => 50000, 'bonus' => 'Reveals basic player stats'],
                2 => ['cost' => 6000000, 'salary' => 100000, 'bonus' => 'Reveals detailed player stats'],
                3 => ['cost' => 15000000, 'salary' => 200000, 'bonus' => 'Finds hidden gem players'],
                4 => ['cost' => 35000000, 'salary' => 400000, 'bonus' => 'Predicts player potential'],
                5 => ['cost' => 80000000, 'salary' => 800000, 'bonus' => 'Discovers world-class talents']
            ]
        ],
        'youth_coach' => [
            'name' => 'Youth Coach',
            'description' => 'Develops young players and academy prospects',
            'icon' => 'graduation-cap',
            'candidates' => [
                ['name' => 'Xavier Hernandez', 'specialty' => 'Technical development', 'experience' => 'Elite'],
                ['name' => 'Frank Lambert', 'specialty' => 'Youth integration', 'experience' => 'Elite'],
                ['name' => 'Miguel Arteta', 'specialty' => 'Tactical education', 'experience' => 'Elite'],
                ['name' => 'Patrick Vieira', 'specialty' => 'Leadership training', 'experience' => 'Elite'],
                ['name' => 'Thierry Henri', 'specialty' => 'Attacking play', 'experience' => 'Elite'],
                ['name' => 'Andrea Pirelli', 'specialty' => 'Vision and creativity', 'experience' => 'Elite']
            ],
            'levels' => [
                1 => ['cost' => 4000000, 'salary' => 80000, 'bonus' => 'Young players develop 25% faster'],
                2 => ['cost' => 10000000, 'salary' => 175000, 'bonus' => 'Young players develop 50% faster'],
                3 => ['cost' => 25000000, 'salary' => 350000, 'bonus' => 'Young players develop 75% faster'],
                4 => ['cost' => 55000000, 'salary' => 700000, 'bonus' => 'Young players develop 100% faster'],
                5 => ['cost' => 120000000, 'salary' => 1400000, 'bonus' => 'Generates academy prospects']
            ]
        ],
        'medical_staff' => [
            'name' => 'Medical Staff',
            'description' => 'Treats injuries and maintains player health',
            'icon' => 'heart',
            'candidates' => [
                ['name' => 'Dr. Ricardo Pruna', 'specialty' => 'Injury prevention', 'experience' => 'Elite'],
                ['name' => 'Dr. Hans Mueller', 'specialty' => 'Sports medicine', 'experience' => 'Elite'],
                ['name' => 'Dr. Eva Carneiro', 'specialty' => 'Match-day medicine', 'experience' => 'Elite'],
                ['name' => 'Dr. Francesco Escola', 'specialty' => 'Rehabilitation', 'experience' => 'Elite'],
                ['name' => 'Dr. Bruno Mazzini', 'specialty' => 'Muscle injuries', 'experience' => 'Elite'],
                ['name' => 'Dr. Jorge Ardevol', 'specialty' => 'Recovery protocols', 'experience' => 'Elite']
            ],
            'levels' => [
                1 => ['cost' => 3500000, 'salary' => 70000, 'bonus' => 'Reduces injury duration by 25%'],
                2 => ['cost' => 9000000, 'salary' => 140000, 'bonus' => 'Reduces injury duration by 40%'],
                3 => ['cost' => 22000000, 'salary' => 280000, 'bonus' => 'Reduces injury duration by 55%'],
                4 => ['cost' => 50000000, 'salary' => 560000, 'bonus' => 'Reduces injury duration by 70%'],
                5 => ['cost' => 110000000, 'salary' => 1120000, 'bonus' => 'Prevents most injuries']
            ]
        ]
    ];
}

function getStaffLevelColor($level)
{
    switch ($level) {
        case 5:
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 4:
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 3:
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 2:
            return 'bg-green-100 text-green-800 border-green-200';
        case 1:
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getContractStatusColor($weeks_remaining)
{
    if ($weeks_remaining <= 4)
        return 'text-red-600 bg-red-50';
    if ($weeks_remaining <= 12)
        return 'text-yellow-600 bg-yellow-50';
    return 'text-green-600 bg-green-50';
}

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <!-- Messages -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg border <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Club Staff</h1>
                <p class="text-gray-600">Hire professional staff to improve your club's performance</p>
            </div>
            <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg">
                <div class="text-sm text-blue-600">Available Budget</div>
                <div class="text-lg font-bold"><?php echo formatMarketValue($user_budget); ?></div>
            </div>
        </div>
    </div>

    <!-- Staff Management by Category -->
    <div class="space-y-8">
        <?php
        $staff_costs = getStaffCosts();
        $current_staff_by_type = [];
        foreach ($current_staff as $staff) {
            $current_staff_by_type[$staff['staff_type']] = $staff;
        }
        ?>

        <?php foreach ($staff_costs as $staff_type => $config): ?>
            <div class="bg-white rounded-lg shadow">
                <!-- Staff Category Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                <i data-lucide="<?php echo $config['icon']; ?>" class="w-8 h-8 text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900"><?php echo $config['name']; ?></h2>
                                <p class="text-gray-600"><?php echo $config['description']; ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <?php if (isset($current_staff_by_type[$staff_type])): ?>
                                <span
                                    class="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                                    Hired
                                </span>
                            <?php else: ?>
                                <span
                                    class="inline-flex items-center gap-2 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">
                                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                                    Available
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <?php if (isset($current_staff_by_type[$staff_type])): ?>
                        <!-- Current Staff Member -->
                        <?php
                        $staff = $current_staff_by_type[$staff_type];
                        $level_config = $config['levels'][$staff['level']];
                        ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-green-900"><?php echo htmlspecialchars($staff['name']); ?>
                                    </h3>
                                    <p class="text-green-700">Current <?php echo $config['name']; ?></p>
                                </div>
                                <span
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border <?php echo getStaffLevelColor($staff['level']); ?>">
                                    <i data-lucide="star" class="w-4 h-4"></i>
                                    Level <?php echo $staff['level']; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="text-center">
                                    <div class="text-lg font-bold text-green-700">
                                        <?php echo formatMarketValue($staff['salary']); ?>
                                    </div>
                                    <div class="text-sm text-green-600">Weekly Salary</div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="text-lg font-bold <?php echo $staff['contract_weeks_remaining'] <= 12 ? 'text-red-600' : 'text-green-700'; ?>">
                                        <?php echo $staff['contract_weeks_remaining']; ?> weeks
                                    </div>
                                    <div class="text-sm text-green-600">Contract Remaining</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-lg font-bold text-green-700"><?php echo $level_config['bonus']; ?></div>
                                    <div class="text-sm text-green-600">Active Bonus</div>
                                </div>
                            </div>

                            <!-- Level Management -->
                            <div class="mb-4">
                                <h4 class="text-sm font-semibold text-green-900 mb-3">Level Management</h4>
                                <div class="grid grid-cols-5 gap-2">
                                    <?php for ($level = 1; $level <= 5; $level++): ?>
                                        <?php
                                        $level_config = $config['levels'][$level];
                                        $is_current = $level == $staff['level'];
                                        $can_upgrade = $level > $staff['level'];
                                        $can_downgrade = $level < $staff['level'];
                                        $upgrade_cost = $can_upgrade ? $level_config['cost'] - $config['levels'][$staff['level']]['cost'] : 0;
                                        $downgrade_refund = $can_downgrade ? (int) (($config['levels'][$staff['level']]['cost'] - $level_config['cost']) * 0.5) : 0;
                                        ?>
                                        <div class="text-center">
                                            <div
                                                class="p-2 border rounded-lg h-full <?php echo $is_current ? 'border-green-500 bg-green-100' : 'border-gray-200'; ?>">
                                                <div class="text-xs font-semibold">Level <?php echo $level; ?></div>
                                                <div class="text-xs text-gray-600">
                                                    <?php echo formatMarketValue($level_config['salary']); ?>/week
                                                </div>

                                                <?php if ($is_current): ?>
                                                    <div class="text-xs text-green-600 font-semibold mt-1">Current</div>
                                                <?php elseif ($can_upgrade && $user_budget >= $upgrade_cost): ?>
                                                    <form method="POST" class="mt-1">
                                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                        <input type="hidden" name="new_level" value="<?php echo $level; ?>">
                                                        <button type="submit" name="upgrade_staff"
                                                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600"
                                                            title="Upgrade cost: <?php echo formatMarketValue($upgrade_cost); ?>">
                                                            ↑ <?php echo formatMarketValue($upgrade_cost); ?>
                                                        </button>
                                                    </form>
                                                <?php elseif ($can_upgrade): ?>
                                                    <div class="text-xs text-gray-400 mt-1" title="Insufficient funds">
                                                        ↑ <?php echo formatMarketValue($upgrade_cost); ?>
                                                    </div>
                                                <?php elseif ($can_downgrade): ?>
                                                    <form method="POST" class="mt-1">
                                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                        <input type="hidden" name="new_level" value="<?php echo $level; ?>">
                                                        <button type="submit" name="downgrade_staff"
                                                            class="text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600"
                                                            title="Downgrade refund: <?php echo formatMarketValue($downgrade_refund); ?> (50%)">
                                                            ↓ +<?php echo formatMarketValue($downgrade_refund); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-2 text-center">
                                    Click ↑ to upgrade (pay difference) or ↓ to downgrade (get 50% refund)
                                </div>
                            </div>

                            <div class="flex gap-3 justify-center">
                                <?php if ($staff['contract_weeks_remaining'] <= 12): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" name="renew_contract"
                                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                            Renew Contract (<?php echo formatMarketValue($staff['salary'] * 4); ?>)
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <button type="button" name="fire_staff"
                                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 fire-staff-btn"
                                        data-staff-id="<?php echo $staff['id']; ?>"
                                        data-staff-name="<?php echo htmlspecialchars($staff['name']); ?>"
                                        data-severance="<?php echo $staff['salary'] * 2; ?>">
                                        <i data-lucide="user-x" class="w-4 h-4"></i>
                                        Fire Staff
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Available Staff Candidates -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Available Candidates</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                                <?php foreach ($config['candidates'] as $index => $candidate): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors cursor-pointer candidate-card"
                                        data-staff-type="<?php echo $staff_type; ?>"
                                        data-candidate-name="<?php echo htmlspecialchars($candidate['name']); ?>">
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                                <i data-lucide="user" class="w-5 h-5 text-gray-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($candidate['name']); ?>
                                                </h4>
                                                <p class="text-xs text-gray-500"><?php echo $candidate['experience']; ?></p>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <strong>Specialty:</strong> <?php echo $candidate['specialty']; ?>
                                        </p>
                                        <button
                                            class="w-full bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 select-candidate-btn">
                                            Select Candidate
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Hiring Form -->
                        <div id="hiring-form-<?php echo $staff_type; ?>" class="bg-gray-50 rounded-lg p-6 hidden">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Hire <span
                                    id="selected-name-<?php echo $staff_type; ?>"></span></h3>

                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="staff_type" value="<?php echo $staff_type; ?>">
                                <input type="hidden" name="staff_name" id="staff-name-<?php echo $staff_type; ?>" value="">

                                <div>
                                    <label class="block text-sm font-medium mb-2">Select Level & Package</label>
                                    <div class="grid grid-cols-1 gap-3">
                                        <?php foreach ($config['levels'] as $level => $level_config): ?>
                                            <label
                                                class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer">
                                                <input type="radio" name="staff_level" value="<?php echo $level; ?>" class="mr-4"
                                                    <?php echo $level === 1 ? 'checked' : ''; ?>>
                                                <div class="flex-1">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="font-semibold">Level <?php echo $level; ?></span>
                                                        <span
                                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border <?php echo getStaffLevelColor($level); ?>">
                                                            <i data-lucide="star" class="w-3 h-3"></i>
                                                            <?php echo ['', 'Basic', 'Good', 'Great', 'Elite', 'Legendary'][$level]; ?>
                                                        </span>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                                        <div>
                                                            <span class="text-gray-600">Hiring Cost:</span>
                                                            <span
                                                                class="font-semibold text-red-600"><?php echo formatMarketValue($level_config['cost']); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-600">Weekly Salary:</span>
                                                            <span
                                                                class="font-semibold text-blue-600"><?php echo formatMarketValue($level_config['salary']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 text-xs text-gray-600">
                                                        <strong>Bonus:</strong> <?php echo $level_config['bonus']; ?>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <button type="button" name="hire_staff"
                                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 font-semibold flex items-center justify-center gap-2 hire-staff-btn"
                                        data-staff-type="<?php echo $staff_type; ?>">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                                        Hire Staff Member
                                    </button>
                                    <button type="button"
                                        class="cancel-hiring-btn bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700"
                                        data-staff-type="<?php echo $staff_type; ?>">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    lucide.createIcons();

    // Format market value for display
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + Math.round(value / 1000) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }

    // Handle candidate selection
    document.addEventListener('DOMContentLoaded', function () {
        // Candidate selection
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.addEventListener('click', function () {
                const staffType = this.dataset.staffType;
                const candidateName = this.dataset.candidateName;

                // Hide all candidate cards for this staff type
                document.querySelectorAll(`[data-staff-type="${staffType}"]`).forEach(c => {
                    c.style.display = 'none';
                });

                // Show hiring form
                const hiringForm = document.getElementById(`hiring-form-${staffType}`);
                hiringForm.classList.remove('hidden');

                // Set selected candidate name
                document.getElementById(`selected-name-${staffType}`).textContent = candidateName;
                document.getElementById(`staff-name-${staffType}`).value = candidateName;
            });
        });

        // Cancel hiring
        document.querySelectorAll('.cancel-hiring-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const staffType = this.dataset.staffType;

                // Hide hiring form
                document.getElementById(`hiring-form-${staffType}`).classList.add('hidden');

                // Show candidate cards again
                document.querySelectorAll(`[data-staff-type="${staffType}"]`).forEach(c => {
                    c.style.display = 'block';
                });
            });
        });

        // Select candidate buttons with popup
        document.querySelectorAll('.select-candidate-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                const candidateCard = this.closest('.candidate-card');
                const candidateName = candidateCard.querySelector('h4').textContent.trim();
                const candidateExperience = candidateCard.querySelector('.text-xs.text-gray-500').textContent.trim();
                const candidateSpecialty = candidateCard.querySelector('p.text-sm.text-gray-600').textContent.replace('Specialty: ', '').trim();

                // Get staff type from the candidate card data attribute
                const staffType = candidateCard.dataset.staffType;
                const staffTypeDisplay = staffType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

                console.log('Opening popup for:', candidateName, 'Type:', staffType);

                // Get staff configuration for this type
                const staffConfig = <?php echo json_encode(getStaffCosts()); ?>;
                const config = staffConfig[staffType];

                if (!config) {
                    console.error('Staff configuration not found for type:', staffType);
                    Swal.fire({
                        icon: 'error',
                        title: 'Configuration Error',
                        text: 'Staff configuration not found. Please refresh the page and try again.',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }

                // Build level options HTML
                let levelOptionsHtml = '';
                Object.keys(config.levels).forEach(level => {
                    const levelConfig = config.levels[level];
                    const isRecommended = level == 2; // Level 2 is usually recommended

                    levelOptionsHtml += `
                        <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-blue-300 cursor-pointer ${isRecommended ? 'border-blue-300 bg-blue-50' : ''}">
                            <input type="radio" name="selected_level" value="${level}" class="mr-4" ${level == 1 ? 'checked' : ''}>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-semibold text-gray-900">Level ${level} ${isRecommended ? '(Recommended)' : ''}</span>
                                    <span class="text-lg font-bold text-green-600">${formatMarketValue(levelConfig.cost)}</span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <div>Weekly Salary: ${formatMarketValue(levelConfig.salary)}</div>
                                    <div>Bonus: ${levelConfig.bonus}</div>
                                </div>
                            </div>
                        </label>
                    `;
                });

                Swal.fire({
                    title: `🤝 Hire ${candidateName}`,
                    html: `
                        <div class="text-left">
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg mb-4 border border-blue-200">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-lg">${candidateName}</h4>
                                        <p class="text-blue-600 font-medium">${staffTypeDisplay}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-600">Experience:</span>
                                        <p class="font-semibold text-gray-900">${candidateExperience}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Specialty:</span>
                                        <p class="font-semibold text-gray-900">${candidateSpecialty}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    💼 Select Contract Package:
                                </h5>
                                <div class="space-y-3 max-h-[360px] overflow-y-auto">
                                    ${levelOptionsHtml}
                                </div>
                            </div>
                            
                            <div class="bg-green-50 p-4 rounded-lg text-sm border border-green-200">
                                <p class="text-green-800 font-semibold mb-2">✅ What You Get:</p>
                                <div class="grid grid-cols-1 gap-1 text-green-700">
                                    <p>• Immediate performance bonuses for your team</p>
                                    <p>• Professional expertise in ${staffTypeDisplay.toLowerCase()}</p>
                                    <p>• Weekly salary automatically deducted</p>
                                    <p>• Upgrade to higher levels anytime</p>
                                    <p>• 52-week contract with renewal options</p>
                                </div>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '🤝 Hire Now',
                    cancelButtonText: 'Maybe Later',
                    width: '650px',
                    // showClass: {
                    //     popup: 'animate__animated animate__fadeInDown'
                    // },
                    // hideClass: {
                    //     popup: 'animate__animated animate__fadeOutUp'
                    // },
                    preConfirm: () => {
                        const selectedLevel = document.querySelector('input[name="selected_level"]:checked');
                        if (!selectedLevel) {
                            Swal.showValidationMessage('Please select a level package');
                            return false;
                        }
                        return {
                            level: selectedLevel.value,
                            cost: config.levels[selectedLevel.value].cost,
                            salary: config.levels[selectedLevel.value].salary
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const { level, cost, salary } = result.value;

                        // Create and submit hiring form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="hire_staff" value="1">
                            <input type="hidden" name="staff_type" value="${staffType}">
                            <input type="hidden" name="staff_name" value="${candidateName}">
                            <input type="hidden" name="staff_level" value="${level}">
                        `;

                        document.body.appendChild(form);
                        form.submit();
                    }
                });

                // Initialize Lucide icons in the popup
                setTimeout(() => {
                    lucide.createIcons();
                }, 100);
            });
        });

        // Enhanced upgrade confirmations with SweetAlert2
        document.querySelectorAll('button[name="upgrade_staff"]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const form = this.closest('form');
                const staffId = form.querySelector('input[name="staff_id"]').value;
                const level = form.querySelector('input[name="new_level"]').value;
                const costMatch = this.textContent.match(/€[\d.,]+[KM]?/);
                const cost = costMatch ? costMatch[0] : 'Unknown';

                // Get staff name from the page
                const staffSection = this.closest('.bg-green-50');
                let staffName = 'Staff Member';
                if (staffSection) {
                    const nameElement = staffSection.querySelector('h3');
                    if (nameElement) {
                        staffName = nameElement.textContent.trim();
                    }
                }

                Swal.fire({
                    title: 'Upgrade Staff Level?',
                    html: `
                        <div class="text-left">
                            <p><strong>Staff:</strong> ${staffName}</p>
                            <p><strong>Upgrade to:</strong> Level ${level}</p>
                            <p><strong>Cost:</strong> <span class="text-red-600 font-semibold">${cost}</span></p>
                            <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                                <p class="text-sm text-blue-800">✓ Higher bonuses and better performance</p>
                                <p class="text-sm text-blue-800">✓ Increased weekly salary</p>
                                <p class="text-sm text-blue-800">✓ Immediate effect</p>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, Upgrade Staff',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create a new form element to ensure proper submission
                        const newForm = document.createElement('form');
                        newForm.method = 'POST';
                        newForm.style.display = 'none';

                        // Copy all form data
                        const formData = new FormData(form);
                        for (let [key, value] of formData.entries()) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            newForm.appendChild(input);
                        }

                        // Add the upgrade_staff button
                        const submitInput = document.createElement('input');
                        submitInput.type = 'hidden';
                        submitInput.name = 'upgrade_staff';
                        submitInput.value = '1';
                        newForm.appendChild(submitInput);

                        document.body.appendChild(newForm);
                        newForm.submit();
                    }
                });
            });
        });

        // Enhanced downgrade confirmations with SweetAlert2
        document.querySelectorAll('button[name="downgrade_staff"]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const form = this.closest('form');
                const staffId = form.querySelector('input[name="staff_id"]').value;
                const level = form.querySelector('input[name="new_level"]').value;
                const refundMatch = this.textContent.match(/€[\d.,]+[KM]?/);
                const refund = refundMatch ? refundMatch[0] : 'Unknown';

                // Get staff name from the page
                const staffSection = this.closest('.bg-green-50');
                let staffName = 'Staff Member';
                if (staffSection) {
                    const nameElement = staffSection.querySelector('h3');
                    if (nameElement) {
                        staffName = nameElement.textContent.trim();
                    }
                }

                Swal.fire({
                    title: 'Downgrade Staff Level?',
                    html: `
                        <div class="text-left">
                            <p><strong>Staff:</strong> ${staffName}</p>
                            <p><strong>Downgrade to:</strong> Level ${level}</p>
                            <p><strong>Refund:</strong> <span class="text-green-600 font-semibold">${refund}</span> (50% of difference)</p>
                            <div class="mt-3 p-3 bg-orange-50 rounded-lg">
                                <p class="text-sm text-orange-800">⚠ Lower bonuses and reduced performance</p>
                                <p class="text-sm text-orange-800">⚠ Decreased weekly salary</p>
                                <p class="text-sm text-green-800">✓ Frees up budget for other needs</p>
                            </div>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, Downgrade Staff',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create a new form element to ensure proper submission
                        const newForm = document.createElement('form');
                        newForm.method = 'POST';
                        newForm.style.display = 'none';

                        // Copy all form data
                        const formData = new FormData(form);
                        for (let [key, value] of formData.entries()) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            newForm.appendChild(input);
                        }

                        // Add the downgrade_staff button
                        const submitInput = document.createElement('input');
                        submitInput.type = 'hidden';
                        submitInput.name = 'downgrade_staff';
                        submitInput.value = '1';
                        newForm.appendChild(submitInput);

                        document.body.appendChild(newForm);
                        newForm.submit();
                    }
                });
            });
        });
    });

    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + (value / 1000).toFixed(0) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }

    // Enhanced hire staff confirmations with SweetAlert2
    document.querySelectorAll('.hire-staff-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            const staffType = this.dataset.staffType;

            // Get selected staff name and level
            const staffNameInput = form.querySelector(`#staff-name-${staffType}`);
            const selectedLevelRadio = form.querySelector('input[name="staff_level"]:checked');

            if (!staffNameInput.value || !selectedLevelRadio) {
                Swal.fire({
                    icon: 'error',
                    title: 'Selection Required',
                    text: 'Please select a candidate and level before hiring.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const staffName = staffNameInput.value;
            const selectedLevel = selectedLevelRadio.value;

            // Get level details from the selected radio button
            const levelLabel = selectedLevelRadio.closest('label');
            const costElement = levelLabel.querySelector('.text-red-600');
            const salaryElement = levelLabel.querySelector('.text-blue-600');
            const bonusElement = levelLabel.querySelector('strong');

            const cost = costElement ? costElement.textContent : 'Unknown';
            const salary = salaryElement ? salaryElement.textContent : 'Unknown';
            const bonus = bonusElement ? bonusElement.parentNode.textContent.replace('Bonus: ', '') : 'Unknown';

            // Get staff type display name
            const staffTypeDisplay = staffType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());

            Swal.fire({
                title: 'Hire Staff Member?',
                html: `
                        <div class="text-left">
                            <p><strong>Position:</strong> ${staffTypeDisplay}</p>
                            <p><strong>Candidate:</strong> ${staffName}</p>
                            <p><strong>Level:</strong> ${selectedLevel}</p>
                            <p><strong>Hiring Cost:</strong> <span class="text-red-600 font-semibold">${cost}</span></p>
                            <p><strong>Weekly Salary:</strong> <span class="text-blue-600 font-semibold">${salary}</span></p>
                            <div class="mt-3 p-3 bg-green-50 rounded-lg">
                                <p class="text-sm text-green-800"><strong>Bonus:</strong> ${bonus}</p>
                                <p class="text-sm text-green-800">✓ 52-week contract</p>
                                <p class="text-sm text-green-800">✓ Immediate performance boost</p>
                                <p class="text-sm text-green-800">✓ Can upgrade/downgrade level later</p>
                            </div>
                        </div>
                    `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Hire Staff',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a new form element to ensure proper submission
                    const newForm = document.createElement('form');
                    newForm.method = 'POST';
                    newForm.style.display = 'none';

                    // Copy all form data
                    const formData = new FormData(form);
                    for (let [key, value] of formData.entries()) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        newForm.appendChild(input);
                    }

                    // Add the hire_staff button
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'hire_staff';
                    submitInput.value = '1';
                    newForm.appendChild(submitInput);

                    document.body.appendChild(newForm);
                    console.log('Submitting hire form for:', staffName, 'Level:', selectedLevel);
                    newForm.submit();
                }
            });
        });
    });

    // Enhanced fire staff confirmations with SweetAlert2
    document.querySelectorAll('.fire-staff-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            const staffId = this.dataset.staffId;
            const staffName = this.dataset.staffName;
            const severance = parseInt(this.dataset.severance);

            Swal.fire({
                title: 'Fire Staff Member?',
                html: `
                        <div class="text-left">
                            <p><strong>Staff Member:</strong> ${staffName}</p>
                            <p><strong>Severance Pay:</strong> <span class="text-red-600 font-semibold">${formatMarketValue(severance)}</span></p>
                            <div class="mt-3 p-3 bg-red-50 rounded-lg">
                                <p class="text-sm text-red-800">⚠ This action cannot be undone</p>
                                <p class="text-sm text-red-800">⚠ You will lose all staff bonuses immediately</p>
                                <p class="text-sm text-red-800">⚠ Severance pay is required (2 weeks salary)</p>
                                <p class="text-sm text-orange-800">ℹ You can hire a replacement immediately</p>
                            </div>
                        </div>
                    `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Fire Staff',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a new form element to ensure proper submission
                    const newForm = document.createElement('form');
                    newForm.method = 'POST';
                    newForm.style.display = 'none';

                    // Add staff ID
                    const staffIdInput = document.createElement('input');
                    staffIdInput.type = 'hidden';
                    staffIdInput.name = 'staff_id';
                    staffIdInput.value = staffId;
                    newForm.appendChild(staffIdInput);

                    // Add the fire_staff button
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'fire_staff';
                    submitInput.value = '1';
                    newForm.appendChild(submitInput);

                    document.body.appendChild(newForm);
                    console.log('Submitting fire form for staff ID:', staffId);
                    newForm.submit();
                }
            });
        });
    });
</script>

<?php
// End content capture and render layout
endContent('Club Staff - ' . APP_NAME);
?>