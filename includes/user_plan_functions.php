<?php
/**
 * User Plan/Subscription Management Functions
 * 
 * This file contains functions related to user plans, subscriptions,
 * feature access control, and plan management.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

/**
 * Get user's current plan information
 * 
 * @param int $user_id User ID
 * @return array User plan information
 */
if (!function_exists('getUserPlan')) {
    function getUserPlan($user_id)
    {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT user_plan, plan_expires_at FROM users WHERE id = :user_id');
            if ($stmt === false) {
                $db->close();
                return null;
            }
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result === false) {
                $db->close();
                return null;
            }
            $userData = $result->fetchArray(SQLITE3_ASSOC);
            $db->close();

            if (!$userData) {
                return null;
            }

            $planKey = $userData['user_plan'] ?? DEFAULT_USER_PLAN;
            $plans = USER_PLANS;

            if (!isset($plans[$planKey])) {
                $planKey = DEFAULT_USER_PLAN;
            }

            $planInfo = $plans[$planKey];
            $planInfo['key'] = $planKey;
            $planInfo['expires_at'] = $userData['plan_expires_at'];
            $planInfo['is_active'] = isPlanActive($userData['plan_expires_at']);

            return $planInfo;
        } catch (Exception $e) {
            return null;
        }
    }
}

/**
 * Check if user's plan is active
 * 
 * @param string $expires_at Plan expiration date
 * @return bool True if plan is active
 */
if (!function_exists('isPlanActive')) {
    function isPlanActive($expires_at)
    {
        if (!$expires_at) {
            return true; // Free plan never expires
        }

        return strtotime($expires_at) > time();
    }
}

/**
 * Check if user has a specific feature
 * 
 * @param int $user_id User ID
 * @param string $feature Feature key
 * @return bool|int Feature value (boolean or integer limit)
 */
if (!function_exists('userHasFeature')) {
    function userHasFeature($user_id, $feature)
    {
        $plan = getUserPlan($user_id);

        if (!$plan || !$plan['is_active']) {
            // If plan expired, fall back to free plan
            $plan = USER_PLANS[DEFAULT_USER_PLAN];
        }

        return $plan['features'][$feature] ?? false;
    }
}

/**
 * Check if user should see ads
 * 
 * @param int $user_id User ID
 * @return bool True if ads should be shown
 */
if (!function_exists('shouldShowAds')) {
    function shouldShowAds($user_id)
    {
        return userHasFeature($user_id, 'show_ads');
    }
}

/**
 * Get user's feature limit
 * 
 * @param int $user_id User ID
 * @param string $feature Feature key
 * @return int Feature limit
 */
if (!function_exists('getUserFeatureLimit')) {
    function getUserFeatureLimit($user_id, $feature)
    {
        $value = userHasFeature($user_id, $feature);
        return is_numeric($value) ? (int) $value : 0;
    }
}

/**
 * Upgrade user plan
 * 
 * @param int $user_id User ID
 * @param string $plan_key Plan key
 * @return bool Success status
 */
if (!function_exists('upgradeUserPlan')) {
    function upgradeUserPlan($user_id, $plan_key)
    {
        $plans = USER_PLANS;

        if (!isset($plans[$plan_key])) {
            return false;
        }

        try {
            $db = getDbConnection();

            $plan = $plans[$plan_key];
            $expires_at = null;

            if ($plan['duration_days'] > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));
            }

            $stmt = $db->prepare('UPDATE users SET user_plan = :plan, plan_expires_at = :expires_at WHERE id = :user_id');
            if ($stmt === false) {
                $db->close();
                return false;
            }
            $stmt->bindValue(':plan', $plan_key, SQLITE3_TEXT);
            $stmt->bindValue(':expires_at', $expires_at, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

            $result = $stmt->execute();
            $db->close();

            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get all available plans
 * 
 * @return array All plans
 */
if (!function_exists('getAllPlans')) {
    function getAllPlans()
    {
        return USER_PLANS;
    }
}

/**
 * Format plan price for display
 * 
 * @param int $price_cents Price in cents
 * @return string Formatted price
 */
if (!function_exists('formatPlanPrice')) {
    function formatPlanPrice($price_cents)
    {
        if ($price_cents === 0) {
            return 'Free';
        }

        return '€' . number_format($price_cents / 100, 2);
    }
}

/**
 * Check if user can perform action based on limits
 * 
 * @param int $user_id User ID
 * @param string $feature Feature to check
 * @param int $current_count Current usage count
 * @return bool True if action is allowed
 */
if (!function_exists('canPerformAction')) {
    function canPerformAction($user_id, $feature, $current_count)
    {
        $limit = getUserFeatureLimit($user_id, $feature);

        if ($limit === 0) {
            return false; // Feature not available
        }

        return $current_count < $limit;
    }
}
