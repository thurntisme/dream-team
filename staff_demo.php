<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'layout.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

// Require user to be logged in and have a club name
requireClubName('staff_demo');

try {
    $db = getDbConnection();

    // Get user data
    $stmt = $db->prepare('SELECT name, club_name, budget, team FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Get user's staff
    $user_staff = getUserStaff($db, $_SESSION['user_id']);
    $staff_effectiveness = getStaffEffectiveness($user_staff);

    // Get team data and apply staff bonuses
    $team_data = json_decode($user['team'], true) ?: [];
    $original_team = $team_data;
    $team_with_bonuses = applyStaffBonuses($team_data, $user_staff);

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Staff System Demo</h1>
        <p class="text-gray-600">See how your staff affects your team's performance</p>
    </div>

    <!-- Staff Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Current Staff -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Your Staff</h2>
            <?php if (empty($user_staff)): ?>
                <div class="text-center py-8">
                    <i data-lucide="users" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Staff Hired</h3>
                    <p class="text-gray-600 mb-4">Hire professional staff to boost your team's performance</p>
                    <a href="staff.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Hire Staff
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($user_staff as $staff_type => $staff): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <div class="font-semibold"><?php echo htmlspecialchars($staff['name']); ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?php echo ucfirst(str_replace('_', ' ', $staff_type)); ?> - Level
                                        <?php echo $staff['level']; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold"><?php echo formatMarketValue($staff['salary']); ?>/week
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo $staff['contract_weeks_remaining']; ?> weeks
                                        left</div>
                                </div>
                            </div>
                            <div class="text-xs text-blue-600">
                                <strong>Level Management:</strong> Can upgrade/downgrade levels to optimize budget
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <div class="text-sm font-semibold text-blue-900 mb-2">Total Weekly Cost</div>
                    <div class="text-lg font-bold text-blue-700">
                        <?php echo formatMarketValue($staff_effectiveness['total_weekly_cost']); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Staff Benefits -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Active Bonuses</h2>
            <?php if (empty($user_staff)): ?>
                <div class="text-center py-8">
                    <i data-lucide="zap-off" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                    <p class="text-gray-600">No bonuses active</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php if ($staff_effectiveness['team_rating_bonus'] > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i data-lucide="trending-up" class="w-5 h-5 text-green-600"></i>
                                <span class="font-medium">Team Rating Boost</span>
                            </div>
                            <span
                                class="text-green-600 font-bold">+<?php echo $staff_effectiveness['team_rating_bonus']; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($staff_effectiveness['fitness_protection'] > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i data-lucide="shield" class="w-5 h-5 text-blue-600"></i>
                                <span class="font-medium">Fitness Protection</span>
                            </div>
                            <span
                                class="text-blue-600 font-bold"><?php echo $staff_effectiveness['fitness_protection']; ?>%</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($staff_effectiveness['youth_development'] > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i data-lucide="graduation-cap" class="w-5 h-5 text-purple-600"></i>
                                <span class="font-medium">Youth Development</span>
                            </div>
                            <span
                                class="text-purple-600 font-bold">+<?php echo $staff_effectiveness['youth_development']; ?>%</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($staff_effectiveness['medical_care'] > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i data-lucide="heart" class="w-5 h-5 text-red-600"></i>
                                <span class="font-medium">Medical Care</span>
                            </div>
                            <span class="text-red-600 font-bold">+<?php echo $staff_effectiveness['medical_care']; ?>%</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($staff_effectiveness['scouting_quality'] > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i data-lucide="search" class="w-5 h-5 text-yellow-600"></i>
                                <span class="font-medium">Scouting Quality</span>
                            </div>
                            <span class="text-yellow-600 font-bold">Level
                                <?php echo $staff_effectiveness['scouting_quality']; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Team Comparison -->
    <?php if (!empty($user_staff) && !empty($team_data)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Team Performance Comparison</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Without Staff -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Without Staff</h3>
                    <div class="space-y-2">
                        <?php
                        $total_rating_original = 0;
                        $player_count = 0;
                        foreach ($original_team as $player):
                            if ($player && isset($player['rating'])):
                                $total_rating_original += $player['rating'];
                                $player_count++;
                                ?>
                                <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                    <span class="text-sm"><?php echo htmlspecialchars($player['name']); ?></span>
                                    <span class="text-sm font-medium">★<?php echo $player['rating']; ?></span>
                                </div>
                            <?php
                            endif;
                        endforeach;
                        $avg_rating_original = $player_count > 0 ? round($total_rating_original / $player_count, 1) : 0;
                        ?>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between items-center font-semibold">
                                <span>Team Average:</span>
                                <span class="text-blue-600">★<?php echo $avg_rating_original; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- With Staff -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">With Staff Bonuses</h3>
                    <div class="space-y-2">
                        <?php
                        $total_rating_bonus = 0;
                        $player_count_bonus = 0;
                        foreach ($team_with_bonuses as $player):
                            if ($player && isset($player['rating'])):
                                $effective_rating = $player['effective_rating'] ?? $player['rating'];
                                $total_rating_bonus += $effective_rating;
                                $player_count_bonus++;
                                ?>
                                <div class="flex justify-between items-center p-2 bg-green-50 rounded">
                                    <span class="text-sm"><?php echo htmlspecialchars($player['name']); ?></span>
                                    <span class="text-sm font-medium text-green-600">★<?php echo $effective_rating; ?>
                                        <?php if (isset($player['staff_bonus']) && $player['staff_bonus'] > 0): ?>
                                            <span class="text-xs text-green-500">(+<?php echo $player['staff_bonus']; ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php
                            endif;
                        endforeach;
                        $avg_rating_bonus = $player_count_bonus > 0 ? round($total_rating_bonus / $player_count_bonus, 1) : 0;
                        ?>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between items-center font-semibold">
                                <span>Team Average:</span>
                                <span class="text-green-600">★<?php echo $avg_rating_bonus; ?>
                                    <?php if ($avg_rating_bonus > $avg_rating_original): ?>
                                        <span
                                            class="text-xs text-green-500">(+<?php echo round($avg_rating_bonus - $avg_rating_original, 1); ?>)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="flex justify-center gap-4">
        <a href="staff.php"
            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 flex items-center gap-2">
            <i data-lucide="user-check" class="w-5 h-5"></i>
            Manage Staff
        </a>
        <a href="team.php"
            class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 flex items-center gap-2">
            <i data-lucide="users" class="w-5 h-5"></i>
            Back to Team
        </a>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

<?php
// End content capture and render layout
endContent('Staff Demo - ' . APP_NAME, 'staff_demo');
?>