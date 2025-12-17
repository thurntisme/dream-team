<?php
// Weekly maintenance script for staff and other recurring tasks
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/staff_functions.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['error' => 'Database not available']);
    exit;
}



try {
    $db = getDbConnection();

    // Process weekly staff maintenance
    $staff_results = processWeeklyStaffMaintenance($db, $_SESSION['user_id']);

    // Update user budget if salary was deducted
    if ($staff_results['salary_cost'] > 0) {
        $stmt = $db->prepare('SELECT budget FROM users WHERE id = :user_id');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user_data = $result->fetchArray(SQLITE3_ASSOC);

        if ($user_data && $user_data['budget'] >= $staff_results['salary_cost']) {
            $new_budget = $user_data['budget'] - $staff_results['salary_cost'];
            $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
            $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // Add academy prospect to available players if generated
    if ($staff_results['academy_prospect']) {
        // This would typically be added to a prospects table or available players
        // For now, we'll just include it in the response
    }

    // Process daily injury recovery for all players
    $injury_results = processDailyInjuryRecovery($db, $_SESSION['user_id']);

    // Process young player development
    $young_player_results = processWeeklyYoungPlayerDevelopment($db, $_SESSION['user_id']);

    $db->close();

    // Return results
    echo json_encode([
        'success' => true,
        'results' => $staff_results,
        'young_player_development' => $young_player_results,
        'injury_recovery' => $injury_results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Maintenance failed: ' . $e->getMessage()]);
}
?>