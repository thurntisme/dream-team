<?php
// Field Modal API - Returns club data for modal display
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';

// Set JSON header
header('Content-Type: application/json');

// Check if database is available
if (!isDatabaseAvailable()) {
    http_response_code(500);
    echo json_encode(['error' => 'Database not available']);
    exit;
}

// Get parameters
$club_id = $_GET['club_id'] ?? null;
$formation = $_GET['formation'] ?? '4-4-2';

if (!$club_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Club ID required']);
    exit;
}

try {
    $db = getDbConnection();

    // Get club data
    $stmt = $db->prepare('SELECT id, name, club_name, formation, team, budget FROM users WHERE id = :club_id');
    $stmt->bindValue(':club_id', $club_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $club = $result->fetchArray(SQLITE3_ASSOC);

    if (!$club) {
        http_response_code(404);
        echo json_encode(['error' => 'Club not found']);
        exit;
    }

    // Parse team data
    $team = json_decode($club['team'] ?? '[]', true);
    if (!is_array($team)) {
        $team = [];
    }

    // Ensure team array has correct length for formation
    $formationData = getFormationData($formation);
    $totalSlots = 0;
    foreach ($formationData['positions'] as $line) {
        $totalSlots += count($line);
    }

    // Pad or trim team array to match formation
    while (count($team) < $totalSlots) {
        $team[] = null;
    }
    if (count($team) > $totalSlots) {
        $team = array_slice($team, 0, $totalSlots);
    }

    $db->close();

    // Return data
    echo json_encode([
        'club' => [
            'id' => $club['id'],
            'name' => $club['name'],
            'club_name' => $club['club_name'],
            'formation' => $formation,
            'budget' => $club['budget']
        ],
        'formation' => $formationData,
        'players' => $team
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>