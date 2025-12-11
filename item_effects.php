<?php
// Item Effects Helper Functions

/**
 * Get all active items for a user
 */
function getUserActiveItems($db, $user_id)
{
    $stmt = $db->prepare('SELECT ui.*, si.name, si.effect_type, si.effect_value, si.duration 
                         FROM user_items ui 
                         JOIN shop_items si ON ui.item_id = si.id 
                         WHERE ui.user_id = :user_id AND ui.is_active = 1 
                         AND (ui.expires_at IS NULL OR ui.expires_at > datetime("now"))');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row;
    }

    return $items;
}

/**
 * Apply item effects to player stats
 */
function applyItemEffectsToPlayers($players, $active_items)
{
    foreach ($active_items as $item) {
        $effect_value = json_decode($item['effect_value'], true);

        switch ($item['effect_type']) {
            case 'player_boost':
                // Boost all players rating
                $rating_boost = $effect_value['rating'] ?? 0;
                foreach ($players as $index => $player) {
                    if ($player && isset($player['rating'])) {
                        $players[$index]['rating'] += $rating_boost;
                        $players[$index]['boosted'] = true;
                    }
                }
                break;

            case 'position_boost':
                // Boost specific position players
                $rating_boost = $effect_value['rating'] ?? 0;
                $target_position = $effect_value['position'] ?? '';

                foreach ($players as $index => $player) {
                    if ($player && isset($player['position']) && $player['position'] === $target_position) {
                        $players[$index]['rating'] += $rating_boost;
                        $players[$index]['boosted'] = true;
                    }
                }
                break;
        }
    }

    return $players;
}

/**
 * Calculate transfer sale price with item effects
 */
function calculateSalePriceWithEffects($base_price, $active_items)
{
    $final_price = $base_price;

    foreach ($active_items as $item) {
        $effect_value = json_decode($item['effect_value'], true);

        if ($item['effect_type'] === 'sale_boost') {
            $multiplier = $effect_value['multiplier'] ?? 1.0;
            $final_price = (int) ($final_price * $multiplier);
        }
    }

    return $final_price;
}

/**
 * Check if user has transfer luck boost
 */
function hasTransferLuckBoost($active_items)
{
    foreach ($active_items as $item) {
        if ($item['effect_type'] === 'transfer_luck') {
            $effect_value = json_decode($item['effect_value'], true);
            return $effect_value['boost'] ?? 0;
        }
    }
    return 0;
}

/**
 * Check if user has player insight enabled
 */
function hasPlayerInsight($active_items)
{
    foreach ($active_items as $item) {
        if ($item['effect_type'] === 'player_insight') {
            $effect_value = json_decode($item['effect_value'], true);
            return $effect_value['enabled'] ?? false;
        }
    }
    return false;
}

/**
 * Get match performance boost
 */
function getMatchPerformanceBoost($active_items)
{
    foreach ($active_items as $item) {
        if ($item['effect_type'] === 'match_boost') {
            $effect_value = json_decode($item['effect_value'], true);
            return [
                'performance' => $effect_value['performance'] ?? 0,
                'matches' => $effect_value['matches'] ?? 0
            ];
        }
    }
    return ['performance' => 0, 'matches' => 0];
}

/**
 * Check if user has advanced formations unlocked
 */
function hasAdvancedFormations($active_items)
{
    foreach ($active_items as $item) {
        if ($item['effect_type'] === 'formation_unlock') {
            $effect_value = json_decode($item['effect_value'], true);
            return $effect_value['advanced'] ?? false;
        }
    }
    return false;
}

/**
 * Get daily income from items
 */
function getDailyIncome($active_items)
{
    $total_income = 0;

    foreach ($active_items as $item) {
        if ($item['effect_type'] === 'daily_income') {
            $effect_value = json_decode($item['effect_value'], true);
            $total_income += $effect_value['amount'] ?? 0;
        }
    }

    return $total_income;
}

/**
 * Process daily income for all users (to be called by a cron job)
 */
function processDailyIncome($db)
{
    // Get all users with active daily income items
    $stmt = $db->prepare('SELECT DISTINCT ui.user_id, SUM(CAST(JSON_EXTRACT(si.effect_value, "$.amount") AS INTEGER)) as daily_amount
                         FROM user_items ui 
                         JOIN shop_items si ON ui.item_id = si.id 
                         WHERE ui.is_active = 1 
                         AND si.effect_type = "daily_income"
                         AND (ui.expires_at IS NULL OR ui.expires_at > datetime("now"))
                         GROUP BY ui.user_id');
    $result = $stmt->execute();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $user_id = $row['user_id'];
        $daily_amount = $row['daily_amount'];

        // Add daily income to user's budget
        $stmt2 = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :user_id');
        $stmt2->bindValue(':amount', $daily_amount, SQLITE3_INTEGER);
        $stmt2->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt2->execute();
    }
}

/**
 * Clean up expired items
 */
function cleanupExpiredItems($db)
{
    $stmt = $db->prepare('UPDATE user_items SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at <= datetime("now")');
    $stmt->execute();
}

/**
 * Get item effect description for display
 */
function getItemEffectDescription($effect_type, $effect_value)
{
    $effect_data = json_decode($effect_value, true);

    switch ($effect_type) {
        case 'player_boost':
            return "All players get +" . ($effect_data['rating'] ?? 0) . " rating";

        case 'position_boost':
            return ($effect_data['position'] ?? 'Unknown') . " players get +" . ($effect_data['rating'] ?? 0) . " rating";

        case 'budget_boost':
            return "Instant budget increase of €" . number_format($effect_data['amount'] ?? 0);

        case 'daily_income':
            return "Generates €" . number_format($effect_data['amount'] ?? 0) . " daily";

        case 'sale_boost':
            $percentage = (($effect_data['multiplier'] ?? 1.0) - 1) * 100;
            return "Transfer sales +" . $percentage . "%";

        case 'transfer_luck':
            $percentage = ($effect_data['boost'] ?? 0) * 100;
            return "Transfer success +" . $percentage . "%";

        case 'player_insight':
            return "Reveals hidden player stats";

        case 'match_boost':
            $percentage = ($effect_data['performance'] ?? 0) * 100;
            $matches = $effect_data['matches'] ?? 0;
            return "+" . $percentage . "% performance for " . $matches . " matches";

        case 'permanent_boost':
            return "Permanent +" . ($effect_data['rating'] ?? 0) . " rating for " . ($effect_data['position'] ?? 'Unknown');

        case 'formation_unlock':
            return "Unlocks advanced formations";

        case 'player_attraction':
            $percentage = ($effect_data['quality_boost'] ?? 0) * 100;
            return "Attracts +" . $percentage . "% better players";

        default:
            return "Special effect";
    }
}
?>