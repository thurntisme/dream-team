<?php
// Field Modal Endpoint
// Returns HTML for field display in modals

session_start();
require_once 'config.php';
require_once 'constants.php';
require_once 'field-component.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// Get parameters
$club_id = $_GET['club_id'] ?? null;
$formation = $_GET['formation'] ?? '4-4-2';
$team_data = $_GET['team'] ?? '[]';

if (!$club_id) {
    http_response_code(400);
    exit('Missing club_id');
}

try {
    $db = getDbConnection();

    // Get club data
    $stmt = $db->prepare('SELECT name, formation, team FROM users WHERE id = :id AND id != :current_user_id');
    $stmt->bindValue(':id', $club_id, SQLITE3_INTEGER);
    $stmt->bindValue(':current_user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $club = $result->fetchArray(SQLITE3_ASSOC);

    if (!$club) {
        http_response_code(404);
        exit('Club not found');
    }

    $team = json_decode($club['team'] ?? '[]', true);
    if (!is_array($team)) {
        $team = [];
    }

    // Ensure team array has the correct length for the formation
    $formationData = FORMATIONS[$formation] ?? FORMATIONS['4-4-2'];
    $expectedLength = count($formationData['roles']);

    // Pad or trim the team array to match the formation
    if (count($team) < $expectedLength) {
        $team = array_pad($team, $expectedLength, null);
    } elseif (count($team) > $expectedLength) {
        $team = array_slice($team, 0, $expectedLength);
    }

    // Debug: Ensure we have the formation data
    $formation = $club['formation'] ?? '4-4-2';
    if (!isset(FORMATIONS[$formation])) {
        $formation = '4-4-2'; // Fallback to default
    }

    echo json_encode([
        'club' => $club['name'],
        'formation' => $formation,
        'players' => $team,
    ]);

    // Render field component
    // echo renderFootballField($team, $formation, [
    //     'interactive' => false,
    //     'size' => 'medium',
    //     'show_names' => true,
    //     'show_actions' => false,
    //     'field_id' => 'modalField'
    // ]);

    $db->close();

} catch (Exception $e) {
    http_response_code(500);
    exit('Server error');
}
?>