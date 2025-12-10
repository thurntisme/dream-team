<?php
// Dream Team Seeder - Generate fake clubs with realistic teams
require_once 'config.php';
require_once 'constants.php';

/**
 * Seed function to create demo clubs with realistic teams
 * Players can be shared across multiple clubs (realistic scenario)
 */
function seedFakeClubs()
{
    try {
        $db = getDbConnection();

        // Check if seeding is needed
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM users');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row['count'] > 1) {
            echo "Database already has users. Skipping seeding.\n";
            return false;
        }

        $clubs = getDemoClubs();
        $players = getDefaultPlayers();

        if (empty($players)) {
            echo "No players available for seeding. Please check players.json.\n";
            return false;
        }

        $seededCount = 0;

        foreach ($clubs as $club) {
            // Create user account
            $stmt = $db->prepare('INSERT INTO users (name, email, password, club_name, formation, team, budget) VALUES (:name, :email, :password, :club_name, :formation, :team, :budget)');

            // Generate team for this club
            $team = generateRealisticTeam($players, $club['formation'], $club['budget']);

            $stmt->bindValue(':name', $club['manager'], SQLITE3_TEXT);
            $stmt->bindValue(':email', $club['email'], SQLITE3_TEXT);
            $stmt->bindValue(':password', password_hash($club['password'], PASSWORD_DEFAULT), SQLITE3_TEXT);
            $stmt->bindValue(':club_name', $club['name'], SQLITE3_TEXT);
            $stmt->bindValue(':formation', $club['formation'], SQLITE3_TEXT);
            $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
            $stmt->bindValue(':budget', $club['budget'], SQLITE3_INTEGER);

            if ($stmt->execute()) {
                $seededCount++;
                echo "✅ Created club: {$club['name']} (Manager: {$club['manager']})\n";
                echo "   Formation: {$club['formation']} | Budget: €" . number_format($club['budget'] / 1000000, 0) . "M\n";
                echo "   Team Value: €" . number_format(calculateTeamValue($team) / 1000000, 1) . "M\n\n";
            } else {
                echo "❌ Failed to create club: {$club['name']}\n";
            }
        }

        $db->close();

        echo "🏆 Seeding completed! Created $seededCount clubs.\n";
        echo "📧 Login credentials:\n";
        foreach ($clubs as $club) {
            echo "   {$club['name']}: {$club['email']} / {$club['password']}\n";
        }

        return true;

    } catch (Exception $e) {
        echo "❌ Seeding failed: " . $e->getMessage() . "\n";
        return false;
    }
}



/**
 * Generate a realistic team based on formation and budget
 * Each club can independently select any player (same player can be in multiple clubs)
 */
function generateRealisticTeam($players, $formation, $budget)
{
    $formationData = FORMATIONS[$formation];
    $roles = $formationData['roles'];
    $team = array_fill(0, count($roles), null);

    // Group players by position
    $playersByPosition = [];
    foreach ($players as $player) {
        $pos = $player['position'];
        if (!isset($playersByPosition[$pos])) {
            $playersByPosition[$pos] = [];
        }
        $playersByPosition[$pos][] = $player;
    }

    // Shuffle players randomly within each position (no rating preference)
    foreach ($playersByPosition as $pos => $posPlayers) {
        shuffle($playersByPosition[$pos]);
    }

    // Track players used within THIS team only (prevent duplicates within same team)
    $usedPlayersInTeam = [];
    $currentBudget = $budget;

    // Fill positions with strategy-based selection
    foreach ($roles as $slotIdx => $requiredPos) {
        $availablePlayers = array_filter(
            $playersByPosition[$requiredPos] ?? [],
            fn($player) => !in_array($player['name'], $usedPlayersInTeam) && $player['value'] <= $currentBudget
        );

        if (!empty($availablePlayers)) {
            // Select random player that fits position and budget
            $selectedPlayer = selectRandomPlayer($availablePlayers, $currentBudget);

            if ($selectedPlayer) {
                $team[$slotIdx] = $selectedPlayer;
                $usedPlayersInTeam[] = $selectedPlayer['name']; // Only track within this team
                $currentBudget -= $selectedPlayer['value'];
            }
        }
    }

    return $team;
}

/**
 * Select random player that fits position and budget constraints
 * No preference for rating - completely random selection
 */
function selectRandomPlayer($players, $budget)
{
    if (empty($players))
        return null;

    // Filter affordable players
    $affordablePlayers = array_filter($players, fn($player) => $player['value'] <= $budget);

    if (empty($affordablePlayers))
        return null;

    // Select completely random player from affordable options
    $randomIndex = rand(0, count($affordablePlayers) - 1);
    return array_values($affordablePlayers)[$randomIndex];
}

/**
 * Calculate total team value
 */
function calculateTeamValue($team)
{
    $totalValue = 0;
    foreach ($team as $player) {
        if ($player) {
            $totalValue += $player['value'];
        }
    }
    return $totalValue;
}

/**
 * Display team information
 */
function displayTeamInfo($team, $formation)
{
    $formationData = FORMATIONS[$formation];
    $roles = $formationData['roles'];

    echo "Team Lineup ({$formation}):\n";
    foreach ($team as $idx => $player) {
        $position = $roles[$idx] ?? 'Unknown';
        if ($player) {
            $value = number_format($player['value'] / 1000000, 1);
            echo "  {$position}: {$player['name']} (Rating: {$player['rating']}, Value: €{$value}M)\n";
        } else {
            echo "  {$position}: [Empty]\n";
        }
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    echo "🌱 Dream Team Club Seeder\n";
    echo "========================\n\n";

    if (!isDatabaseAvailable()) {
        echo "❌ Database not available. Please run install.php first.\n";
        echo "💡 Run: php install.php or visit install.php in your browser\n";
        exit(1);
    }

    $clubCount = count(getDemoClubs());
    echo "This will create {$clubCount} demo clubs with realistic teams and formations.\n";
    echo "Each club will have different strategies and budgets.\n\n";

    seedFakeClubs();

    echo "\n💡 Usage:\n";
    echo "   CLI: php seed.php\n";
    echo "   Web: visit seed.php?seed=clubs\n";
    echo "   Install: Use 'Seed Demo Clubs' button in install.php\n";
}

// Web execution
if (isset($_GET['seed']) && $_GET['seed'] === 'clubs') {
    header('Content-Type: text/plain');

    if (!isDatabaseAvailable()) {
        echo "Database not available. Please run install.php first.\n";
        exit;
    }

    seedFakeClubs();
}