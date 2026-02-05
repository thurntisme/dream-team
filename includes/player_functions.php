<?php

/**
 * Player Management Functions
 * 
 * This file contains functions related to player management, ratings, fitness,
 * form, experience, card levels, and player statistics.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Calculate player value based on rating and position
 * 
 * @param array $player Player data array
 * @return int Calculated player value
 */
if (!function_exists('calculatePlayerValue')) {
    function calculatePlayerValue($player)
    {
        $rating = $player['rating'] ?? 50;
        $position = $player['position'] ?? 'CM';

        // Base value calculation
        $base_value = $rating * 100000;

        // Position multipliers
        $position_multipliers = [
            'GK' => 0.8,
            'CB' => 0.9,
            'LB' => 0.95,
            'RB' => 0.95,
            'CDM' => 1.0,
            'CM' => 1.0,
            'CAM' => 1.1,
            'LW' => 1.15,
            'RW' => 1.15,
            'ST' => 1.2
        ];

        $multiplier = $position_multipliers[$position] ?? 1.0;

        return (int) ($base_value * $multiplier);
    }
}

/**
 * Get position display name
 * 
 * @param string $position Position code
 * @return string Full position name
 */
if (!function_exists('getPositionName')) {
    function getPositionName($position)
    {
        $positions = [
            'GK' => 'Goalkeeper',
            'CB' => 'Centre Back',
            'LB' => 'Left Back',
            'RB' => 'Right Back',
            'CDM' => 'Defensive Midfielder',
            'CM' => 'Central Midfielder',
            'CAM' => 'Attacking Midfielder',
            'LW' => 'Left Winger',
            'RW' => 'Right Winger',
            'ST' => 'Striker'
        ];

        return $positions[$position] ?? $position;
    }
}

/**
 * Generate random player name
 * 
 * @return string Random player name
 */
if (!function_exists('generateRandomPlayerName')) {
    function generateRandomPlayerName()
    {
        $first_names = [
            'Alex',
            'Bruno',
            'Carlos',
            'David',
            'Eduardo',
            'Fernando',
            'Gabriel',
            'Hugo',
            'Ivan',
            'João',
            'Kevin',
            'Lucas',
            'Miguel',
            'Nicolas',
            'Oscar',
            'Pedro',
            'Rafael',
            'Samuel',
            'Thiago',
            'Victor',
            'William',
            'Xavier',
            'Yuri',
            'Zack'
        ];

        $last_names = [
            'Silva',
            'Santos',
            'Oliveira',
            'Souza',
            'Rodriguez',
            'Fernandez',
            'Lopez',
            'Martinez',
            'Garcia',
            'Gonzalez',
            'Perez',
            'Sanchez',
            'Ramirez',
            'Cruz',
            'Flores',
            'Torres',
            'Rivera',
            'Gomez',
            'Diaz',
            'Morales',
            'Jimenez',
            'Herrera'
        ];

        return $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
    }
}

/**
 * Initialize player fitness and form if not set
 * 
 * @param array $player Player data
 * @return array Player data with fitness and form
 */
if (!function_exists('initializePlayerCondition')) {
    function initializePlayerCondition($player)
    {
        if (!isset($player['fitness'])) {
            $player['fitness'] = rand(75, 100); // Start with good fitness
        }
        if (!isset($player['form'])) {
            $player['form'] = rand(6, 8); // Start with decent form (1-10 scale)
        }
        if (!isset($player['matches_played'])) {
            $player['matches_played'] = 0;
        }
        if (!isset($player['last_match_date'])) {
            $player['last_match_date'] = null;
        }
        if (!isset($player['contract_matches'])) {
            $player['contract_matches'] = rand(15, 50); // Contract for 15-50 matches
        }
        if (!isset($player['contract_matches_remaining'])) {
            $player['contract_matches_remaining'] = $player['contract_matches'];
        }
        if (!isset($player['level'])) {
            $player['level'] = 1; // Default level for players joining the club
        }
        if (!isset($player['experience'])) {
            $player['experience'] = 0; // Experience points for leveling up
        }
        if (!isset($player['card_level'])) {
            $player['card_level'] = 1; // Default card level for players joining the club
        }
        if (!isset($player['base_salary'])) {
            // Calculate base salary from player value (weekly salary = 0.1% of value)
            $player['base_salary'] = max(1000, ($player['value'] ?? 1000000) * 0.001);
        }
        return $player;
    }
}

/**
 * Update player fitness based on activity
 * 
 * @param array $player Player data
 * @param bool $played_match Whether player played a match
 * @param int $days_since_last_match Days since last match
 * @return array Updated player data
 */
if (!function_exists('updatePlayerFitness')) {
    function updatePlayerFitness($player, $played_match = false, $days_since_last_match = 0)
    {
        $fitness = $player['fitness'] ?? 100;

        if ($played_match) {
            // Fitness decreases after playing
            $loss = rand(5, 15);
            // Apply fitness coach reduction if present (value between 0 and 1)
            if (isset($player['fitness_bonus']) && is_numeric($player['fitness_bonus'])) {
                $loss = (int) round($loss * $player['fitness_bonus']);
            }
            $fitness -= $loss;
        } else {
            // Fitness recovers when resting
            $recovery = min(3 + ($days_since_last_match * 2), 10);
            $fitness += $recovery;
        }

        // Keep fitness between 0 and 100
        $player['fitness'] = max(0, min(100, $fitness));

        return $player;
    }
}

/**
 * Update player form based on performance
 * 
 * @param array $player Player data
 * @param string $performance Performance rating (excellent, good, average, poor)
 * @return array Updated player data
 */
if (!function_exists('updatePlayerForm')) {
    function updatePlayerForm($player, $performance = 'average')
    {
        $form = $player['form'] ?? 7;

        switch ($performance) {
            case 'excellent':
                $form += (rand(15, 25) / 10); // +1.5 to +2.5
                break;
            case 'good':
                $form += (rand(5, 15) / 10); // +0.5 to +1.5
                break;
            case 'average':
                // Fluctuation
                $change = rand(-10, 10) / 10; // -1.0 to +1.0
                if ($change == 0) $change = (rand(0, 1) ? 0.2 : -0.2);
                $form += $change;
                break;
            case 'poor':
                $form -= (rand(10, 25) / 10); // -1.0 to -2.5
                break;
        }

        // Keep form between 1 and 10
        $player['form'] = max(1, min(10, $form));

        return $player;
    }
}

/**
 * Get fitness status text and color
 * 
 * @param int $fitness Fitness level (0-100)
 * @return array Status info with text and color
 */
if (!function_exists('getFitnessStatus')) {
    function getFitnessStatus($fitness)
    {
        if ($fitness >= 90) {
            return ['text' => 'Excellent', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
        } elseif ($fitness >= 75) {
            return ['text' => 'Good', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'];
        } elseif ($fitness >= 60) {
            return ['text' => 'Average', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'];
        } elseif ($fitness >= 40) {
            return ['text' => 'Poor', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100'];
        } else {
            return ['text' => 'Injured', 'color' => 'text-red-600', 'bg' => 'bg-red-100'];
        }
    }
}

/**
 * Get form status text and color
 * 
 * @param float $form Form level (1-10)
 * @return array Status info with text and color
 */
if (!function_exists('getFormStatus')) {
    function getFormStatus($form)
    {
        if ($form >= 8.5) {
            return ['text' => 'Superb', 'color' => 'text-purple-600', 'bg' => 'bg-purple-100'];
        } elseif ($form >= 7.5) {
            return ['text' => 'Excellent', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
        } elseif ($form >= 6.5) {
            return ['text' => 'Good', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'];
        } elseif ($form >= 5.5) {
            return ['text' => 'Average', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'];
        } elseif ($form >= 4) {
            return ['text' => 'Poor', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100'];
        } else {
            return ['text' => 'Terrible', 'color' => 'text-red-600', 'bg' => 'bg-red-100'];
        }
    }
}

/**
 * Calculate effective player rating based on fitness, form, level, and card level
 * 
 * @param array $player Player data
 * @return int Effective rating
 */
if (!function_exists('getEffectiveRating')) {
    function getEffectiveRating($player)
    {
        $base_rating = ($player['effective_rating'] ?? $player['rating'] ?? 70);
        $fitness = $player['fitness'] ?? 100;
        $form = $player['form'] ?? 7;
        $level = $player['level'] ?? 1;
        $card_level = $player['card_level'] ?? 1;

        // Fitness affects rating (0.5-1.0 multiplier)
        $fitness_multiplier = 0.5 + ($fitness / 200);

        // Form affects rating (-5 to +5 points)
        $form_bonus = ($form - 7) * 0.7;

        // Level affects rating (+0.5 points per level above 1)
        $level_bonus = ($level - 1) * 0.5;

        // Card level affects rating (+1 point per card level above 1)
        $card_level_bonus = ($card_level - 1) * 1.0;

        $effective_rating = ($base_rating * $fitness_multiplier) + $form_bonus + $level_bonus + $card_level_bonus;

        return max(1, min(99, round($effective_rating)));
    }
}

/**
 * Calculate experience required for next level
 * 
 * @param int $level Current level
 * @return int Experience required for next level
 */
if (!function_exists('getExperienceForLevel')) {
    function getExperienceForLevel($level)
    {
        // Experience required increases exponentially: level * 100 + (level-1) * 50
        return $level * 100 + ($level - 1) * 50;
    }
}

/**
 * Calculate total experience required to reach a specific level
 * 
 * @param int $target_level Target level
 * @return int Total experience required
 */
if (!function_exists('getTotalExperienceForLevel')) {
    function getTotalExperienceForLevel($target_level)
    {
        $total = 0;
        for ($i = 1; $i < $target_level; $i++) {
            $total += getExperienceForLevel($i + 1);
        }
        return $total;
    }
}

/**
 * Add experience to player and handle level ups
 * 
 * @param array $player Player data
 * @param int $experience Experience points to add
 * @return array Updated player data with level up information
 */
if (!function_exists('addPlayerExperience')) {
    function addPlayerExperience($player, $experience)
    {
        $current_level = $player['level'] ?? 1;
        $current_experience = $player['experience'] ?? 0;

        $new_experience = $current_experience + $experience;
        $new_level = $current_level;

        // Check for level ups
        while ($new_level < 50) { // Max level 50
            $required_exp = getExperienceForLevel($new_level + 1);
            $total_required = getTotalExperienceForLevel($new_level + 1);

            if ($new_experience >= $total_required) {
                $new_level++;
            } else {
                break;
            }
        }

        $player['experience'] = $new_experience;
        $player['level'] = $new_level;

        // Add level up information if leveled up
        if ($new_level > $current_level) {
            $player['level_up_info'] = [
                'previous_level' => $current_level,
                'new_level' => $new_level,
                'levels_gained' => $new_level - $current_level
            ];
        }

        return $player;
    }
}

/**
 * Get player level status information
 * 
 * @param array $player Player data
 * @return array Level status with progress information
 */
if (!function_exists('getPlayerLevelStatus')) {
    function getPlayerLevelStatus($player)
    {
        $level = $player['level'] ?? 1;
        $experience = $player['experience'] ?? 0;

        if ($level >= 50) {
            return [
                'level' => $level,
                'experience' => $experience,
                'experience_for_next' => 0,
                'experience_progress' => 0,
                'progress_percentage' => 100,
                'is_max_level' => true
            ];
        }

        $total_required_current = getTotalExperienceForLevel($level);
        $total_required_next = getTotalExperienceForLevel($level + 1);
        $experience_for_next = $total_required_next - $experience;
        $experience_in_current_level = $experience - $total_required_current;
        $experience_needed_for_level = $total_required_next - $total_required_current;

        $progress_percentage = $experience_needed_for_level > 0
            ? ($experience_in_current_level / $experience_needed_for_level) * 100
            : 0;

        return [
            'level' => $level,
            'experience' => $experience,
            'experience_for_next' => $experience_for_next,
            'experience_progress' => $experience_in_current_level,
            'experience_needed' => $experience_needed_for_level,
            'progress_percentage' => min(100, max(0, $progress_percentage)),
            'is_max_level' => false
        ];
    }
}

/**
 * Get level display information
 * 
 * @param int $level Player level
 * @return array Level display info with colors and text
 */
if (!function_exists('getLevelDisplayInfo')) {
    function getLevelDisplayInfo($level)
    {
        if ($level >= 40) {
            return [
                'text' => 'Legendary',
                'color' => 'text-purple-600',
                'bg' => 'bg-purple-100',
                'border' => 'border-purple-200'
            ];
        } elseif ($level >= 30) {
            return [
                'text' => 'Elite',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-200'
            ];
        } elseif ($level >= 20) {
            return [
                'text' => 'Expert',
                'color' => 'text-blue-600',
                'bg' => 'bg-blue-100',
                'border' => 'border-blue-200'
            ];
        } elseif ($level >= 10) {
            return [
                'text' => 'Professional',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'border' => 'border-green-200'
            ];
        } elseif ($level >= 5) {
            return [
                'text' => 'Experienced',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-200'
            ];
        } else {
            return [
                'text' => 'Rookie',
                'color' => 'text-gray-600',
                'bg' => 'bg-gray-100',
                'border' => 'border-gray-200'
            ];
        }
    }
}

/**
 * Calculate card level upgrade cost
 * 
 * @param int $current_level Current card level
 * @param int $player_value Player's market value
 * @return int Upgrade cost
 */
if (!function_exists('getCardLevelUpgradeCost')) {
    function getCardLevelUpgradeCost($current_level, $player_value)
    {
        // Base cost increases exponentially with level
        $base_cost = $current_level * 500000; // €0.5M per level

        // Player value multiplier (higher value players cost more to upgrade)
        $value_multiplier = 1 + ($player_value / 50000000); // +1 for every €50M value

        return (int) ($base_cost * $value_multiplier);
    }
}

/**
 * Get card level display information
 * 
 * @param int $card_level Player card level
 * @return array Card level display info with colors and text
 */
if (!function_exists('getCardLevelDisplayInfo')) {
    function getCardLevelDisplayInfo($card_level)
    {
        if ($card_level >= 10) {
            return [
                'text' => 'Diamond',
                'color' => 'text-cyan-600',
                'bg' => 'bg-cyan-100',
                'border' => 'border-cyan-200',
                'icon' => 'diamond'
            ];
        } elseif ($card_level >= 8) {
            return [
                'text' => 'Platinum',
                'color' => 'text-purple-600',
                'bg' => 'bg-purple-100',
                'border' => 'border-purple-200',
                'icon' => 'star'
            ];
        } elseif ($card_level >= 6) {
            return [
                'text' => 'Gold',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-200',
                'icon' => 'award'
            ];
        } elseif ($card_level >= 4) {
            return [
                'text' => 'Silver',
                'color' => 'text-gray-600',
                'bg' => 'bg-gray-100',
                'border' => 'border-gray-200',
                'icon' => 'medal'
            ];
        } elseif ($card_level >= 2) {
            return [
                'text' => 'Bronze',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-200',
                'icon' => 'shield'
            ];
        } else {
            return [
                'text' => 'Basic',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'border' => 'border-green-200',
                'icon' => 'user'
            ];
        }
    }
}

/**
 * Calculate player weekly salary based on card level
 * 
 * @param array $player Player data
 * @return int Weekly salary
 */
if (!function_exists('calculatePlayerSalary')) {
    function calculatePlayerSalary($player)
    {
        $base_salary = $player['base_salary'] ?? max(1000, ($player['value'] ?? 1000000) * 0.001);
        $card_level = $player['card_level'] ?? 1;

        // Salary increases by 20% per card level above 1
        $salary_multiplier = 1 + (($card_level - 1) * 0.2);

        return (int) ($base_salary * $salary_multiplier);
    }
}

/**
 * Get card level benefits
 * 
 * @param int $card_level Player card level
 * @return array Benefits information
 */
if (!function_exists('getCardLevelBenefits')) {
    function getCardLevelBenefits($card_level)
    {
        $rating_bonus = ($card_level - 1) * 1.0;
        $fitness_bonus = ($card_level - 1) * 2; // +2 max fitness per level
        $salary_increase = ($card_level - 1) * 20; // +20% salary per level

        return [
            'rating_bonus' => $rating_bonus,
            'fitness_bonus' => $fitness_bonus,
            'salary_increase_percent' => $salary_increase,
            'max_fitness' => min(100, 100 + $fitness_bonus)
        ];
    }
}

/**
 * Calculate card level upgrade success rate
 * 
 * @param int $current_level Current card level
 * @return int Success rate percentage (30-85%)
 */
if (!function_exists('getCardLevelUpgradeSuccessRate')) {
    function getCardLevelUpgradeSuccessRate($current_level)
    {
        $base_success_rate = 85; // 85% base success rate
        $level_penalty = ($current_level - 1) * 10; // -10% per level above 1
        return max(30, $base_success_rate - $level_penalty); // Minimum 30% success rate
    }
}

/**
 * Update player fitness with card level bonus
 * 
 * @param array $player Player data
 * @param bool $played_match Whether player played a match
 * @param int $days_since_last_match Days since last match
 * @return array Updated player data
 */
if (!function_exists('updatePlayerFitnessWithCardLevel')) {
    function updatePlayerFitnessWithCardLevel($player, $played_match = false, $days_since_last_match = 0)
    {
        $fitness = $player['fitness'] ?? 100;
        $card_level = $player['card_level'] ?? 1;
        $benefits = getCardLevelBenefits($card_level);
        $max_fitness = $benefits['max_fitness'];

        if ($played_match) {
            // Fitness decreases after playing
            $fitness -= rand(5, 15);
        } else {
            // Fitness recovers when resting
            $recovery = min(3 + ($days_since_last_match * 2), 10);
            $fitness += $recovery;
        }

        // Keep fitness between 0 and max fitness (based on card level)
        $player['fitness'] = max(0, min($max_fitness, $fitness));

        return $player;
    }
}

/**
 * Get contract status information
 * 
 * @param array $player Player data
 * @return array Contract status with text, color, and urgency level
 */
if (!function_exists('getContractStatus')) {
    function getContractStatus($player)
    {
        $remaining = $player['contract_matches_remaining'] ?? ($player['contract_matches'] ?? 25);

        if ($remaining <= 0) {
            return [
                'text' => 'Expired',
                'color' => 'text-red-600',
                'bg' => 'bg-red-100',
                'border' => 'border-red-200',
                'urgency' => 'critical'
            ];
        } elseif ($remaining <= 3) {
            return [
                'text' => 'Expiring Soon',
                'color' => 'text-red-600',
                'bg' => 'bg-red-100',
                'border' => 'border-red-200',
                'urgency' => 'high'
            ];
        } elseif ($remaining <= 8) {
            return [
                'text' => 'Renewal Needed',
                'color' => 'text-orange-600',
                'bg' => 'bg-orange-100',
                'border' => 'border-orange-200',
                'urgency' => 'medium'
            ];
        } elseif ($remaining <= 15) {
            return [
                'text' => 'Active',
                'color' => 'text-yellow-600',
                'bg' => 'bg-yellow-100',
                'border' => 'border-yellow-200',
                'urgency' => 'low'
            ];
        } else {
            return [
                'text' => 'Secure',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'border' => 'border-green-200',
                'urgency' => 'none'
            ];
        }
    }
}

/**
 * Calculate team value
 * 
 * @param array $team Team array
 * @return int Total team value
 */
if (!function_exists('calculateTeamValue')) {
    function calculateTeamValue($team)
    {
        if (!is_array($team)) {
            return 0;
        }

        $totalValue = 0;
        foreach ($team as $player) {
            if ($player && isset($player['value'])) {
                $totalValue += $player['value'];
            }
        }

        return $totalValue;
    }
}
