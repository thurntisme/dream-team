<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/constants.php';



// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_current_plan':
            $plan = getUserPlan($userId);
            echo json_encode([
                'success' => true,
                'plan' => $plan
            ]);
            break;

        case 'get_all_plans':
            $plans = getAllPlans();
            echo json_encode([
                'success' => true,
                'plans' => $plans
            ]);
            break;

        case 'upgrade_plan':
            $planKey = $_POST['plan'] ?? '';

            if (upgradeUserPlan($userId, $planKey)) {
                echo json_encode(['success' => true, 'message' => 'Plan upgraded successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upgrade plan']);
            }
            break;

        case 'check_feature_limit':
            $feature = $_GET['feature'] ?? '';
            $currentCount = $_GET['current_count'] ?? 0;

            $canPerform = canPerformAction($userId, $feature, $currentCount);
            $limit = getUserFeatureLimit($userId, $feature);

            echo json_encode([
                'success' => true,
                'can_perform' => $canPerform,
                'limit' => $limit,
                'current_count' => $currentCount
            ]);
            break;

        case 'should_show_ads':
            $showAds = shouldShowAds($userId);
            echo json_encode([
                'success' => true,
                'show_ads' => $showAds
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>