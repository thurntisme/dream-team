<?php
// Staff system functions

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Get user's staff members
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Array of staff members
 */
if (!function_exists('getUserStaff')) {
    function getUserStaff($db, $user_id)
    {
        $stmt = $db->prepare('SELECT * FROM club_staff WHERE user_id = (SELECT id FROM users WHERE uuid = :uuid)');
        $stmt->bindValue(':uuid', $user_id, SQLITE3_TEXT);
        $result = $stmt->execute();

        $staff = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $staff[$row['staff_type']] = $row;
        }

        return $staff;
    }
}

/**
 * Apply staff bonuses to team
 * 
 * @param array $team Team array
 * @param array $staff Staff array
 * @return array Modified team with bonuses applied
 */
if (!function_exists('applyStaffBonuses')) {
    function applyStaffBonuses($team, $staff)
    {
        if (!is_array($team) || empty($staff)) {
            return $team;
        }

        // Apply head coach bonus (team rating boost)
        if (isset($staff['head_coach'])) {
            $rating_bonus = $staff['head_coach']['level'] * 2; // +2 per level

            for ($i = 0; $i < count($team); $i++) {
                if ($team[$i] && isset($team[$i]['rating'])) {
                    $team[$i]['effective_rating'] = min(99, $team[$i]['rating'] + $rating_bonus);
                    $team[$i]['staff_bonus'] = $rating_bonus;
                }
            }
        }

        // Apply fitness coach bonus (reduced fitness loss)
        if (isset($staff['fitness_coach'])) {
            $fitness_reduction = 1 - ($staff['fitness_coach']['level'] * 0.15); // 15% less per level

            for ($i = 0; $i < count($team); $i++) {
                if ($team[$i] && isset($team[$i]['fitness'])) {
                    $team[$i]['fitness_bonus'] = $fitness_reduction;
                }
            }
        }

        return $team;
    }
}

/**
 * Calculate weekly staff salaries
 * 
 * @param array $staff Staff array
 * @return int Total weekly salary cost
 */
if (!function_exists('calculateStaffSalaries')) {
    function calculateStaffSalaries($staff)
    {
        $total_salary = 0;

        foreach ($staff as $staff_member) {
            $total_salary += $staff_member['salary'];
        }

        return $total_salary;
    }
}

/**
 * Update staff contracts (reduce remaining weeks)
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Array of expired staff
 */
if (!function_exists('updateStaffContracts')) {
    function updateStaffContracts($db, $user_id)
    {
        // Reduce contract weeks for all staff
        $stmt = $db->prepare('UPDATE club_staff SET contract_weeks_remaining = contract_weeks_remaining - 1 WHERE user_id = :user_id AND contract_weeks_remaining > 0');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Get expired staff
        $stmt = $db->prepare('SELECT * FROM club_staff WHERE user_id = :user_id AND contract_weeks_remaining <= 0');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $expired_staff = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $expired_staff[] = $row;
        }

        // Remove expired staff
        if (!empty($expired_staff)) {
            $stmt = $db->prepare('DELETE FROM club_staff WHERE user_id = :user_id AND contract_weeks_remaining <= 0');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();
        }

        return $expired_staff;
    }
}

/**
 * Apply scout bonus to player discovery
 * 
 * @param array $players Available players
 * @param array $staff Staff array
 * @return array Modified players with scout bonuses
 */
if (!function_exists('applyScoutBonus')) {
    function applyScoutBonus($players, $staff)
    {
        if (!isset($staff['scout']) || empty($players)) {
            return $players;
        }

        $scout_level = $staff['scout']['level'];

        // Higher level scouts reveal more information and find better players
        foreach ($players as &$player) {
            if ($scout_level >= 2) {
                // Reveal detailed stats
                $player['scout_info'] = [
                    'potential' => rand($player['rating'], min(99, $player['rating'] + 10)),
                    'form' => rand(5, 10),
                    'injury_risk' => rand(1, 10)
                ];
            }

            if ($scout_level >= 3) {
                // Chance to find hidden gems (boost rating)
                if (rand(1, 100) <= 5) { // 5% chance
                    $player['rating'] = min(99, $player['rating'] + rand(3, 8));
                    $player['hidden_gem'] = true;
                }
            }

            if ($scout_level >= 4) {
                // Predict potential more accurately
                $player['scout_info']['potential'] = min(99, $player['rating'] + rand(5, 15));
            }
        }

        return $players;
    }
}

/**
 * Apply youth coach bonus to young players
 * 
 * @param array $team Team array
 * @param array $staff Staff array
 * @return array Modified team with youth development bonuses
 */
if (!function_exists('applyYouthCoachBonus')) {
    function applyYouthCoachBonus($team, $staff)
    {
        if (!isset($staff['youth_coach']) || !is_array($team)) {
            return $team;
        }

        $youth_level = $staff['youth_coach']['level'];
        $development_multiplier = 1 + ($youth_level * 0.25); // 25% faster per level

        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i] && isset($team[$i]['rating'])) {
                // Consider players under 23 as "young"
                $age = $team[$i]['age'] ?? rand(18, 35);
                if ($age < 23) {
                    $team[$i]['youth_development'] = $development_multiplier;

                    // Chance for rating improvement
                    if (rand(1, 100) <= 10 * $youth_level) { // 10% per level chance
                        $team[$i]['rating'] = min(99, $team[$i]['rating'] + 1);
                        $team[$i]['developed'] = true;
                    }
                }
            }
        }

        return $team;
    }
}

/**
 * Apply medical staff bonus to injured players
 * 
 * @param array $team Team array
 * @param array $staff Staff array
 * @return array Modified team with medical bonuses
 */
if (!function_exists('applyMedicalStaffBonus')) {
    function applyMedicalStaffBonus($team, $staff)
    {
        if (!isset($staff['medical_staff']) || !is_array($team)) {
            return $team;
        }

        $medical_level = $staff['medical_staff']['level'];
        $injury_reduction = $medical_level * 0.15; // 15% less injury duration per level

        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i] && isset($team[$i]['fitness'])) {
                // Improve fitness recovery for low fitness players
                if ($team[$i]['fitness'] < 50) {
                    $recovery_bonus = $medical_level * 5; // +5 fitness per level
                    $team[$i]['fitness'] = min(100, $team[$i]['fitness'] + $recovery_bonus);
                    $team[$i]['medical_treatment'] = $recovery_bonus;
                }

                // Reduce injury risk
                $team[$i]['injury_protection'] = $injury_reduction;
            }
        }

        return $team;
    }
}

/**
 * Get staff effectiveness summary
 * 
 * @param array $staff Staff array
 * @return array Staff effectiveness summary
 */
if (!function_exists('getStaffEffectiveness')) {
    function getStaffEffectiveness($staff)
    {
        $effectiveness = [
            'team_rating_bonus' => 0,
            'fitness_protection' => 0,
            'scouting_quality' => 0,
            'youth_development' => 0,
            'medical_care' => 0,
            'total_weekly_cost' => 0
        ];

        foreach ($staff as $staff_type => $staff_member) {
            $level = $staff_member['level'];
            $effectiveness['total_weekly_cost'] += $staff_member['salary'];

            switch ($staff_type) {
                case 'head_coach':
                    $effectiveness['team_rating_bonus'] = $level * 2;
                    break;
                case 'fitness_coach':
                    $effectiveness['fitness_protection'] = $level * 15; // Percentage
                    break;
                case 'scout':
                    $effectiveness['scouting_quality'] = $level;
                    break;
                case 'youth_coach':
                    $effectiveness['youth_development'] = $level * 25; // Percentage faster
                    break;
                case 'medical_staff':
                    $effectiveness['medical_care'] = $level * 15; // Percentage better recovery
                    break;
            }
        }

        return $effectiveness;
    }
}

/**
 * Generate academy prospects (youth coach level 5 bonus)
 * 
 * @param array $staff Staff array
 * @return array|null Generated prospect or null
 */
if (!function_exists('generateAcademyProspect')) {
    function generateAcademyProspect($staff)
    {
        if (!isset($staff['youth_coach']) || $staff['youth_coach']['level'] < 5) {
            return null;
        }

        // 5% chance per week to generate a prospect
        if (rand(1, 100) > 5) {
            return null;
        }

        $positions = ['GK', 'CB', 'LB', 'RB', 'CDM', 'CM', 'CAM', 'LW', 'RW', 'ST'];
        $position = $positions[array_rand($positions)];

        $prospect = [
            'uuid' => generateUUID(),
            'name' => generateRandomPlayerName(),
            'position' => $position,
            'rating' => rand(60, 80), // Academy prospects start decent
            'age' => rand(16, 19),
            'value' => rand(500000, 5000000), // €0.5M - €5M
            'potential' => rand(75, 95),
            'academy_product' => true,
            'playablePositions' => [$position],
            'club' => 'Academy',
            'description' => 'Promising academy graduate with great potential.'
        ];

        return $prospect;
    }
}

/**
 * Process weekly staff maintenance
 * 
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Results of weekly processing
 */
if (!function_exists('processWeeklyStaffMaintenance')) {
    function processWeeklyStaffMaintenance($db, $user_id)
    {
        $results = [
            'salary_cost' => 0,
            'expired_staff' => [],
            'academy_prospect' => null,
            'staff_bonuses_applied' => false
        ];

        // Get current staff
        $staff = getUserStaff($db, $user_id);

        if (empty($staff)) {
            return $results;
        }

        // Calculate and deduct weekly salaries
        $total_salary = calculateStaffSalaries($staff);
        $results['salary_cost'] = $total_salary;

        if ($total_salary > 0) {
            // Get current budget
        $stmt = $db->prepare('SELECT budget FROM user_club WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $user_data = $result->fetchArray(SQLITE3_ASSOC);

            if ($user_data && $user_data['budget'] >= $total_salary) {
                // Deduct salaries
                $new_budget = $user_data['budget'] - $total_salary;
                $stmt = $db->prepare('UPDATE user_club SET budget = :budget WHERE user_id = :user_id');
                $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }

        // Update contracts and remove expired staff
        $results['expired_staff'] = updateStaffContracts($db, $user_id);

        // Generate academy prospect if applicable
        $results['academy_prospect'] = generateAcademyProspect($staff);

        $results['staff_bonuses_applied'] = true;

        return $results;
    }
}

/**
 * Get staff recommendations based on team needs
 * 
 * @param array $team Team data
 * @param array $current_staff Current staff
 * @return array Staff recommendations
 */
if (!function_exists('getStaffRecommendations')) {
    function getStaffRecommendations($team, $current_staff)
    {
        $recommendations = [];

        if (!is_array($team)) {
            return $recommendations;
        }

        // Calculate team stats
        $total_fitness = 0;
        $low_fitness_count = 0;
        $young_players = 0;
        $total_rating = 0;
        $player_count = 0;

        foreach ($team as $player) {
            if ($player && isset($player['rating'])) {
                $player_count++;
                $total_rating += $player['rating'];

                $fitness = $player['fitness'] ?? 100;
                $total_fitness += $fitness;
                if ($fitness < 70)
                    $low_fitness_count++;

                $age = $player['age'] ?? rand(18, 35);
                if ($age < 23)
                    $young_players++;
            }
        }

        if ($player_count === 0) {
            return $recommendations;
        }

        $avg_fitness = $total_fitness / $player_count;
        $avg_rating = $total_rating / $player_count;

        // Recommend based on team needs
        if (!isset($current_staff['head_coach']) && $avg_rating < 75) {
            $recommendations[] = [
                'type' => 'head_coach',
                'priority' => 'high',
                'reason' => 'Your team rating is below average. A head coach will boost all players.'
            ];
        }

        if (!isset($current_staff['fitness_coach']) && ($avg_fitness < 80 || $low_fitness_count > 3)) {
            $recommendations[] = [
                'type' => 'fitness_coach',
                'priority' => 'high',
                'reason' => 'Many players have low fitness. A fitness coach will help maintain condition.'
            ];
        }

        if (!isset($current_staff['youth_coach']) && $young_players > 2) {
            $recommendations[] = [
                'type' => 'youth_coach',
                'priority' => 'medium',
                'reason' => 'You have several young players who could benefit from accelerated development.'
            ];
        }

        if (!isset($current_staff['scout'])) {
            $recommendations[] = [
                'type' => 'scout',
                'priority' => 'medium',
                'reason' => 'A scout will help you discover better players and hidden gems.'
            ];
        }

        if (!isset($current_staff['medical_staff']) && $low_fitness_count > 2) {
            $recommendations[] = [
                'type' => 'medical_staff',
                'priority' => 'medium',
                'reason' => 'Medical staff will help prevent injuries and speed up recovery.'
            ];
        }

        return $recommendations;
    }
}

/**
 * Process daily injury recovery for all players
 * @param SQLite3 $db Database connection
 * @param int $user_id User ID
 * @return array Results of injury recovery processing
 */
if (!function_exists('processDailyInjuryRecovery')) {
    function processDailyInjuryRecovery($db, $user_id)
    {
        // Get user data
        $stmt = $db->prepare('SELECT team, substitutes FROM user_club WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $team = json_decode($user['team'], true) ?: [];
        $substitutes = json_decode($user['substitutes'], true) ?: [];
        $recoveries = [];
        $fitness_improvements = [];

        // Process recovery for main team
        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i]) {
                if (isPlayerInjuredStaff($team[$i])) {
                    $recovery_result = processPlayerRecoveryStaff($team[$i]);
                    $team[$i] = $recovery_result['player'];
                    if ($recovery_result['recovered']) {
                        $recoveries[] = [
                            'player_name' => $team[$i]['name'],
                            'position' => 'team'
                        ];
                    }
                } else {
                    // Natural fitness recovery for non-injured players
                    $fitness_gain = rand(1, 3);
                    $old_fitness = $team[$i]['fitness'] ?? 100;
                    $team[$i]['fitness'] = min(100, $old_fitness + $fitness_gain);
                    
                    if ($team[$i]['fitness'] > $old_fitness) {
                        $fitness_improvements[] = [
                            'player_name' => $team[$i]['name'],
                            'fitness_gain' => $fitness_gain,
                            'new_fitness' => $team[$i]['fitness']
                        ];
                    }
                }
            }
        }

        // Process recovery for substitutes
        for ($i = 0; $i < count($substitutes); $i++) {
            if ($substitutes[$i]) {
                if (isPlayerInjuredStaff($substitutes[$i])) {
                    $recovery_result = processPlayerRecoveryStaff($substitutes[$i]);
                    $substitutes[$i] = $recovery_result['player'];
                    if ($recovery_result['recovered']) {
                        $recoveries[] = [
                            'player_name' => $substitutes[$i]['name'],
                            'position' => 'substitute'
                        ];
                    }
                } else {
                    // Natural fitness recovery for non-injured players
                    $fitness_gain = rand(1, 3);
                    $old_fitness = $substitutes[$i]['fitness'] ?? 100;
                    $substitutes[$i]['fitness'] = min(100, $old_fitness + $fitness_gain);
                    
                    if ($substitutes[$i]['fitness'] > $old_fitness) {
                        $fitness_improvements[] = [
                            'player_name' => $substitutes[$i]['name'],
                            'fitness_gain' => $fitness_gain,
                            'new_fitness' => $substitutes[$i]['fitness']
                        ];
                    }
                }
            }
        }

        // Update database
        $stmt = $db->prepare('UPDATE user_club SET team = :team, substitutes = :substitutes WHERE user_id = :user_id');
        $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
        $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        return [
            'success' => true,
            'recoveries' => $recoveries,
            'fitness_improvements' => $fitness_improvements,
            'recovery_count' => count($recoveries),
            'fitness_improvement_count' => count($fitness_improvements)
        ];
    }

    function isPlayerInjuredStaff($player) {
        return isset($player['injury']) && $player['injury']['days_remaining'] > 0;
    }

    function processPlayerRecoveryStaff($player) {
        if (!isPlayerInjuredStaff($player)) {
            return ['player' => $player, 'recovered' => false];
        }
        
        $player['injury']['days_remaining']--;
        
        // Check if player has recovered
        if ($player['injury']['days_remaining'] <= 0) {
            // Remove injury
            unset($player['injury']);
            
            // Gradual fitness recovery (not full recovery immediately)
            $player['fitness'] = min(100, ($player['fitness'] ?? 50) + rand(10, 20));
            
            return ['player' => $player, 'recovered' => true];
        }
        
        // Gradual fitness improvement during recovery
        $player['fitness'] = min(100, ($player['fitness'] ?? 50) + rand(1, 3));
        
        return ['player' => $player, 'recovered' => false];
    }
}
