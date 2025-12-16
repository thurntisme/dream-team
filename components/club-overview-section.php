<!-- Club Overview Section -->
<div class="mb-6">
    <div class="bg-white rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div
                    class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                    <i data-lucide="shield" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($user['club_name']); ?>
                    </h1>
                    <p class="text-gray-600">Manager: <?php echo htmlspecialchars($user['name']); ?></p>
                    <div class="flex items-center gap-2 mt-2">
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border <?php echo getLevelColor($club_level); ?>">
                            <i data-lucide="star" class="w-4 h-4"></i>
                            Level <?php echo $club_level; ?> - <?php echo $level_name; ?>
                        </span>
                        <span
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                            <i data-lucide="trophy" class="w-4 h-4"></i>
                            Rank #
                            <?php echo $club_ranking; ?> of <?php echo $total_clubs; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-600">Club Founded</div>
                <div class="text-lg font-bold text-gray-900">
                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    <?php echo floor((time() - strtotime($user['created_at'])) / 86400); ?> days ago
                </div>
            </div>
        </div>

        <!-- Team Management Navigation -->
        <div class="mb-6 flex flex-wrap gap-3">
            <a href="shirt_numbers.php"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition-colors">
                <i data-lucide="shirt" class="w-4 h-4"></i>
                Shirt Numbers
            </a>
            <a href="contracts.php"
                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 transition-colors">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Contracts
            </a>
            <a href="transfer.php"
                class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center gap-2 transition-colors">
                <i data-lucide="users" class="w-4 h-4"></i>
                Transfer Market
            </a>
            <a href="staff.php"
                class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 flex items-center gap-2 transition-colors">
                <i data-lucide="briefcase" class="w-4 h-4"></i>
                Staff
            </a>
        </div>

        <!-- Club Statistics Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div
                class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4 text-center border border-green-200">
                <div class="text-2xl font-bold text-green-700" id="clubTeamValue">
                    <?php echo formatMarketValue($team_value); ?>
                </div>
                <div class="text-sm text-green-600">Team Value</div>
            </div>
            <div
                class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 text-center border border-blue-200">
                <div class="text-2xl font-bold text-blue-700" id="clubBudget">
                    <?php echo formatMarketValue($user_budget); ?>
                </div>
                <div class="text-sm text-blue-600">Budget</div>
            </div>
            <div
                class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4 text-center border border-purple-200">
                <div class="text-2xl font-bold text-purple-700" id="clubPlayerCount">
                    <?php echo $total_players . '/' . $max_players; ?>
                </div>
                <div class="text-sm text-purple-600">Squad Size</div>
            </div>
            <div
                class="bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg p-4 text-center border border-yellow-200">
                <div class="text-2xl font-bold text-yellow-700" id="clubAvgRating">
                    <?php
                    $total_rating = 0;
                    $rated_players = 0;
                    if (is_array($team_data)) {
                        foreach ($team_data as $player) {
                            if ($player && isset($player['rating']) && $player['rating'] > 0) {
                                $total_rating += $player['rating'];
                                $rated_players++;
                            }
                        }
                    }
                    echo $rated_players > 0 ? round($total_rating / $rated_players, 1) : '0';
                    ?>
                </div>
                <div class="text-sm text-yellow-600">Avg Rating</div>
            </div>
        </div>

        <!-- Staff Effectiveness -->
        <?php if (!empty($user_staff)): ?>
            <?php $staff_effectiveness = getStaffEffectiveness($user_staff); ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-3 flex items-center gap-2">
                    <i data-lucide="user-check" class="w-5 h-5"></i>
                    Staff Bonuses Active
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                    <?php if ($staff_effectiveness['team_rating_bonus'] > 0): ?>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600">+
                                <?php echo $staff_effectiveness['team_rating_bonus']; ?>
                            </div>
                            <div class="text-xs text-blue-700">Team Rating</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($staff_effectiveness['fitness_protection'] > 0): ?>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600">
                                <?php echo $staff_effectiveness['fitness_protection']; ?>%
                            </div>
                            <div class="text-xs text-blue-700">Fitness Protection</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($staff_effectiveness['youth_development'] > 0): ?>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600">+
                                <?php echo $staff_effectiveness['youth_development']; ?>%
                            </div>
                            <div class="text-xs text-blue-700">Youth Development</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($staff_effectiveness['medical_care'] > 0): ?>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600">
                                +<?php echo $staff_effectiveness['medical_care']; ?>%</div>
                            <div class=" text-xs text-blue-700">Medical Care</div>
                        </div>
                    <?php endif; ?>
                    <div class="text-center">
                        <div class="text-lg font-bold text-red-600">
                            <?php echo formatMarketValue($staff_effectiveness['total_weekly_cost']); ?>
                        </div>
                        <div class="text-xs text-blue-700">Weekly Cost</div>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <a href="staff.php" class="text-sm text-blue-600 hover:text-blue-800 underline">
                        Manage Staff →
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formation and Strategy Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="layout" class="w-4 h-4"></i>
                    Formation
                </h3>
                <div class="text-lg font-bold text-gray-700"><?php echo htmlspecialchars($saved_formation); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    <?php echo htmlspecialchars(FORMATIONS[$saved_formation]['description'] ?? 'Classic formation'); ?>
                </div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="target" class="w-4 h-4"></i>
                    Challenge Status
                </h3>
                <?php
                $player_count = count(array_filter($team_data ?: [], fn($p) => $p !== null));
                $can_challenge = $player_count >= 11;
                ?>
                <div class="text-lg font-bold <?php echo $can_challenge ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $can_challenge ? 'Ready' : 'Not Ready'; ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    <?php echo $can_challenge ? 'Can challenge other clubs' : 'Need ' . (11 - $player_count) . ' more players'; ?>
                </div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                    Level Progress
                </h3>
                <?php
                $progress = getExpProgress($club_exp, $club_level);
                $next_level = $club_level + 1;
                ?>
                <div class="text-lg font-bold text-purple-600">
                    Level <?php echo $club_level; ?>
                </div>
                <?php if ($club_level < 50): ?>
                    <div class="text-sm text-gray-600 mt-1 mb-2">
                        <?php echo number_format($progress['exp_in_current_level']); ?> /
                        <?php echo number_format($progress['exp_needed_for_next']); ?> EXP
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                        <div class="bg-purple-600 h-2 rounded-full transition-all duration-300"
                            style="width: <?php echo $progress['progress_percentage']; ?>%"></div>
                    </div>
                    <div class="text-xs text-gray-500">
                        <?php echo $progress['progress_percentage']; ?>% to Level <?php echo $next_level; ?>
                    </div>
                <?php else: ?>
                    <div class="text-sm text-gray-600 mt-1">
                        Maximum level reached!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>