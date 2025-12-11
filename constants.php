<?php
// Dream Team Constants - Formations and Player Data

// Budget configuration
define('DEFAULT_BUDGET', 500000000); // €500M default budget - optimal for competitive lineups
define('DEFAULT_MAX_PLAYERS', 23); // Default maximum players in squad

// Demo clubs for seeding
define('DEMO_CLUBS', [
    [
        'name' => 'Thunder Bay United',
        'manager' => 'Marcus Thompson',
        'email' => 'marcus.thompson@thunderbay.com',
        'password' => 'thunder123',
        'formation' => '4-4-2',
        'budget' => 1200000000, // €1.2B
        'strategy' => 'balanced'
    ],
    [
        'name' => 'Golden Eagles FC',
        'manager' => 'Sofia Rodriguez',
        'email' => 'sofia.rodriguez@goldeneagles.com',
        'password' => 'eagles123',
        'formation' => '4-3-3',
        'budget' => 1100000000, // €1.1B
        'strategy' => 'attacking'
    ],
    [
        'name' => 'Crystal Wolves',
        'manager' => 'David Chen',
        'email' => 'david.chen@crystalwolves.com',
        'password' => 'wolves123',
        'formation' => '4-2-3-1',
        'budget' => 1300000000, // €1.3B
        'strategy' => 'galactico'
    ],
    [
        'name' => 'Phoenix Rising',
        'manager' => 'Emma Johnson',
        'email' => 'emma.johnson@phoenixrising.com',
        'password' => 'phoenix123',
        'formation' => '4-3-3',
        'budget' => 1000000000, // €1.0B
        'strategy' => 'high_intensity'
    ],
    [
        'name' => 'Midnight Strikers',
        'manager' => 'Alessandro Rossi',
        'email' => 'alessandro.rossi@midnightstrikers.com',
        'password' => 'midnight123',
        'formation' => '3-5-2',
        'budget' => 1150000000, // €1.15B
        'strategy' => 'defensive'
    ],
    [
        'name' => 'Velocity FC',
        'manager' => 'Priya Patel',
        'email' => 'priya.patel@velocityfc.com',
        'password' => 'velocity123',
        'formation' => '4-1-4-1',
        'budget' => 1250000000, // €1.25B
        'strategy' => 'counter_attack'
    ]
]);

// Formation configurations
define('FORMATIONS', [
    '4-4-2' => [
        'name' => '4-4-2',
        'positions' => [[50], [20, 40, 60, 80], [20, 40, 60, 80], [35, 65]],
        'roles' => ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'],
        'description' => 'Classic formation with solid defense and midfield'
    ],
    '4-3-3' => [
        'name' => '4-3-3',
        'positions' => [[50], [20, 40, 60, 80], [30, 50, 70], [20, 50, 80]],
        'roles' => ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'CM', 'CM', 'LW', 'ST', 'RW'],
        'description' => 'Attacking formation with wide forwards'
    ],
    '3-5-2' => [
        'name' => '3-5-2',
        'positions' => [[50], [25, 50, 75], [15, 35, 50, 65, 85], [35, 65]],
        'roles' => ['GK', 'CB', 'CB', 'CB', 'LWB', 'CDM', 'CM', 'CAM', 'RWB', 'ST', 'ST'],
        'description' => 'Strong midfield with wing-backs'
    ],
    '4-2-3-1' => [
        'name' => '4-2-3-1',
        'positions' => [[50], [20, 40, 60, 80], [35, 65], [20, 50, 80], [50]],
        'roles' => ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'CDM', 'LW', 'CAM', 'RW', 'ST'],
        'description' => 'Defensive midfield with attacking midfielder'
    ],
    '4-1-4-1' => [
        'name' => '4-1-4-1',
        'positions' => [[50], [20, 40, 60, 80], [50], [20, 40, 60, 80], [50]],
        'roles' => ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'LM', 'CM', 'CM', 'RM', 'ST'],
        'description' => 'Single defensive midfielder with wide midfield'
    ],
    '5-3-2' => [
        'name' => '5-3-2',
        'positions' => [[50], [15, 30, 50, 70, 85], [30, 50, 70], [35, 65]],
        'roles' => ['GK', 'LWB', 'CB', 'CB', 'CB', 'RWB', 'CM', 'CAM', 'CM', 'ST', 'ST'],
        'description' => 'Five at the back with wing-backs'
    ],
    '3-4-3' => [
        'name' => '3-4-3',
        'positions' => [[50], [25, 50, 75], [25, 40, 60, 75], [20, 50, 80]],
        'roles' => ['GK', 'CB', 'CB', 'CB', 'LM', 'CDM', 'CDM', 'RM', 'LW', 'ST', 'RW'],
        'description' => 'Three center-backs with wide midfielders'
    ],
    '4-3-2-1' => [
        'name' => '4-3-2-1',
        'positions' => [[50], [20, 40, 60, 80], [30, 50, 70], [35, 65], [50]],
        'roles' => ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'CM', 'CM', 'CAM', 'CAM', 'ST'],
        'description' => 'Christmas tree formation with two attacking midfielders'
    ],
    '4-5-1' => [
        'name' => '4-5-1',
        'positions' => [[50], [20, 40, 60, 80], [15, 30, 50, 70, 85], [50]],
        'roles' => ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CDM', 'CM', 'CAM', 'RM', 'ST'],
        'description' => 'Defensive formation with packed midfield'
    ]
]);

// Player positions
define('PLAYER_POSITIONS', [
    'GK' => [
        'name' => 'Goalkeeper',
        'short' => 'GK',
        'color' => 'bg-yellow-500',
        'description' => 'Last line of defense'
    ],
    'CB' => [
        'name' => 'Centre Back',
        'short' => 'CB',
        'color' => 'bg-blue-600',
        'description' => 'Central defender'
    ],
    'LB' => [
        'name' => 'Left Back',
        'short' => 'LB',
        'color' => 'bg-blue-500',
        'description' => 'Left side defender'
    ],
    'RB' => [
        'name' => 'Right Back',
        'short' => 'RB',
        'color' => 'bg-blue-500',
        'description' => 'Right side defender'
    ],
    'CDM' => [
        'name' => 'Defensive Midfielder',
        'short' => 'CDM',
        'color' => 'bg-green-600',
        'description' => 'Defensive midfielder'
    ],
    'CM' => [
        'name' => 'Central Midfielder',
        'short' => 'CM',
        'color' => 'bg-green-500',
        'description' => 'Central midfielder'
    ],
    'CAM' => [
        'name' => 'Attacking Midfielder',
        'short' => 'CAM',
        'color' => 'bg-green-400',
        'description' => 'Attacking midfielder'
    ],
    'LW' => [
        'name' => 'Left Winger',
        'short' => 'LW',
        'color' => 'bg-red-500',
        'description' => 'Left wing forward'
    ],
    'RW' => [
        'name' => 'Right Winger',
        'short' => 'RW',
        'color' => 'bg-red-500',
        'description' => 'Right wing forward'
    ],
    'ST' => [
        'name' => 'Striker',
        'short' => 'ST',
        'color' => 'bg-red-600',
        'description' => 'Main striker'
    ],
    'CF' => [
        'name' => 'Centre Forward',
        'short' => 'CF',
        'color' => 'bg-red-600',
        'description' => 'Central forward'
    ],
    'LWB' => [
        'name' => 'Left Wing-Back',
        'short' => 'LWB',
        'color' => 'bg-blue-400',
        'description' => 'Left wing-back (defensive + attacking)'
    ],
    'RWB' => [
        'name' => 'Right Wing-Back',
        'short' => 'RWB',
        'color' => 'bg-blue-400',
        'description' => 'Right wing-back (defensive + attacking)'
    ],
    'LM' => [
        'name' => 'Left Midfielder',
        'short' => 'LM',
        'color' => 'bg-green-300',
        'description' => 'Left side midfielder'
    ],
    'RM' => [
        'name' => 'Right Midfielder',
        'short' => 'RM',
        'color' => 'bg-green-300',
        'description' => 'Right side midfielder'
    ]
]);

// Load player database from JSON file
// This function reads players.json and converts it to the expected PHP array format
// Includes caching, error handling, and data validation
function loadPlayersFromJson()
{
    static $cachedPlayers = null;

    // Return cached data if already loaded
    if ($cachedPlayers !== null) {
        return $cachedPlayers;
    }

    $jsonFile = __DIR__ . '/players.json';

    if (!file_exists($jsonFile)) {
        error_log("Dream Team: players.json file not found at: " . $jsonFile);
        $cachedPlayers = [];
        return $cachedPlayers;
    }

    $jsonContent = file_get_contents($jsonFile);
    if ($jsonContent === false) {
        error_log("Dream Team: Failed to read players.json file");
        $cachedPlayers = [];
        return $cachedPlayers;
    }

    $playersData = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Dream Team: Invalid JSON in players.json - " . json_last_error_msg());
        $cachedPlayers = [];
        return $cachedPlayers;
    }

    if (!is_array($playersData)) {
        error_log("Dream Team: players.json must contain an array of players");
        $cachedPlayers = [];
        return $cachedPlayers;
    }

    // Convert JSON objects to the expected array format
    $players = [];
    foreach ($playersData as $index => $player) {
        // Validate required fields
        if (!isset($player['name']) || !isset($player['position']) || !isset($player['rating']) || !isset($player['value'])) {
            error_log("Dream Team: Invalid player data at index $index - missing required fields");
            continue;
        }

        $players[] = [
            'name' => (string) $player['name'],
            'position' => (string) $player['position'],
            'rating' => (int) $player['rating'],
            'value' => (int) $player['value']
        ];
    }

    $cachedPlayers = $players;
    return $cachedPlayers;
}

// Default player database - Loaded from players.json
define('DEFAULT_PLAYERS', loadPlayersFromJson());

// Helper functions
function getFormationData($formation)
{
    return FORMATIONS[$formation] ?? FORMATIONS['4-4-2'];
}

function getPlayerPositionData($position)
{
    return PLAYER_POSITIONS[$position] ?? PLAYER_POSITIONS['MID'];
}

function getDefaultPlayers()
{
    return DEFAULT_PLAYERS;
}

function getFormationsList()
{
    return array_keys(FORMATIONS);
}

function getPositionsList()
{
    return array_keys(PLAYER_POSITIONS);
}

function getDemoClubs()
{
    return DEMO_CLUBS;
}

// Format market value for display
function formatMarketValue($value)
{
    if ($value >= 1000000) {
        return '€' . number_format($value / 1000000, 1) . 'M';
    } elseif ($value >= 1000) {
        return '€' . number_format($value / 1000, 0) . 'K';
    } else {
        return '€' . number_format($value, 0);
    }
}