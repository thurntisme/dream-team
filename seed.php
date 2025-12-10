<?php
// Dream Team Seeder - Generate fake clubs with realistic teams
require_once 'config.php';
require_once 'constants.php';

/**
 * Seed function to create 4 fake clubs with realistic teams
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

        $clubs = getFakeClubData();
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
 * Get fake club data with realistic information
 */
function getFakeClubData()
{
    return [
        [
            'name' => 'Manchester Legends',
            'manager' => 'Alex Ferguson Jr',
            'email' => 'alex@manchester-legends.com',
            'password' => 'legends123',
            'formation' => '4-4-2',
            'budget' => 450000000, // €450M
            'strategy' => 'balanced'
        ],
        [
            'name' => 'Barcelona Dreams',
            'manager' => 'Pep Guardiola II',
            'email' => 'pep@barca-dreams.com',
            'password' => 'dreams123',
            'formation' => '4-3-3',
            'budget' => 520000000, // €520M
            'strategy' => 'attacking'
        ],
        [
            'name' => 'Real Madrid Elite',
            'manager' => 'Zinedine Zidane Jr',
            'email' => 'zidane@real-elite.com',
            'password' => 'elite123',
            'formation' => '4-2-3-1',
            'budget' => 480000000, // €480M
            'strategy' => 'galactico'
        ],
        [
            'name' => 'Liverpool Warriors',
            'manager' => 'Jurgen Klopp II',
            'email' => 'jurgen@liverpool-warriors.com',
            'password' => 'warriors123',
            'formation' => '4-3-3',
            'budget' => 400000000, // €400M
            'strategy' => 'high_intensity'
        ]
    ];
}

/**
 * Generate a realistic team based on formation and budget
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

    // Sort players by rating (descending) within each position
    foreach ($playersByPosition as $pos => $posPlayers) {
        usort($playersByPosition[$pos], function ($a, $b) {
            return $b['rating'] - $a['rating'];
        });
    }

    $usedPlayers = [];
    $currentBudget = $budget;

    // Fill positions with strategy-based selection
    foreach ($roles as $slotIdx => $requiredPos) {
        $availablePlayers = array_filter(
            $playersByPosition[$requiredPos] ?? [],
            function ($player) use ($usedPlayers, $currentBudget) {
                return !in_array($player['name'], $usedPlayers) &&
                    $player['value'] <= $currentBudget;
            }
        );

        if (!empty($availablePlayers)) {
            // Select player based on budget and strategy
            $selectedPlayer = selectPlayerByStrategy($availablePlayers, $currentBudget, $requiredPos);

            if ($selectedPlayer) {
                $team[$slotIdx] = $selectedPlayer;
                $usedPlayers[] = $selectedPlayer['name'];
                $currentBudget -= $selectedPlayer['value'];
            }
        }
    }

    return $team;
}

/**
 * Select player based on strategy and budget
 */
function selectPlayerByStrategy($players, $budget, $position)
{
    if (empty($players))
        return null;

    // Filter affordable players
    $affordablePlayers = array_filter($players, function ($player) use ($budget) {
        return $player['value'] <= $budget;
    });

    if (empty($affordablePlayers))
        return null;

    // Strategy: Mix of high-rated and budget-conscious selections
    $budgetTier = $budget / DEFAULT_BUDGET;

    if ($budgetTier > 0.8) {
        // High budget: Go for top players (top 20%)
        $topIndex = max(0, floor(count($affordablePlayers) * 0.2));
        return $affordablePlayers[rand(0, $topIndex)];
    } elseif ($budgetTier > 0.5) {
        // Medium budget: Mix of good players (top 50%)
        $midIndex = max(0, floor(count($affordablePlayers) * 0.5));
        return $affordablePlayers[rand(0, $midIndex)];
    } else {
        // Low budget: More careful selection (top 70%)
        $budgetIndex = max(0, floor(count($affordablePlayers) * 0.7));
        return $affordablePlayers[rand(0, $budgetIndex)];
    }
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

    echo "This will create 4 demo clubs with realistic teams and formations.\n";
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
?>