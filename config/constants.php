<?php
// Dream Team Constants - Formations and Player Data

// Define app constant to prevent direct access to helpers
define('DREAM_TEAM_APP', true);

// Include helper functions
require_once __DIR__ . '/../includes/helpers.php';

// Budget configuration
define('DEFAULT_BUDGET', 500000000); // €500M default budget - optimal for competitive lineups
define('DEFAULT_MAX_PLAYERS', 23); // Default maximum players in squad

// Academy scouting cost
define('ACADEMY_SCOUT_COST', 500000); // €500K to scout a new academy player

// User Plan/Package Configuration
define('USER_PLANS', [
    'free' => [
        'name' => 'Free Plan',
        'price' => 0,
        'duration_days' => 0, // Unlimited
        'features' => [
            'max_academy_players' => 3,
            'max_staff_members' => 2,
            'max_stadium_capacity' => 25000,
            'weekly_scout_limit' => 1,
            'transfer_market_access' => true,
            'young_player_market_access' => true,
            'show_ads' => true,
            'priority_support' => false
        ],
        'description' => 'Basic features with ads'
    ],
    'premium' => [
        'name' => 'Premium Plan',
        'price' => 999, // €9.99 in cents
        'duration_days' => 30,
        'features' => [
            'max_academy_players' => 10,
            'max_staff_members' => 8,
            'max_stadium_capacity' => 75000,
            'weekly_scout_limit' => 5,
            'transfer_market_access' => true,
            'young_player_market_access' => true,
            'show_ads' => false,
            'priority_support' => true,
            'advanced_analytics' => true,
            'custom_formations' => true
        ],
        'description' => 'Enhanced features without ads'
    ],
    'pro' => [
        'name' => 'Pro Plan',
        'price' => 1999, // €19.99 in cents
        'duration_days' => 30,
        'features' => [
            'max_academy_players' => 25,
            'max_staff_members' => 15,
            'max_stadium_capacity' => 100000,
            'weekly_scout_limit' => 10,
            'transfer_market_access' => true,
            'young_player_market_access' => true,
            'show_ads' => false,
            'priority_support' => true,
            'advanced_analytics' => true,
            'custom_formations' => true,
            'exclusive_players' => true,
            'tournament_access' => true
        ],
        'description' => 'All features unlocked'
    ]
]);

define('DEFAULT_USER_PLAN', 'free');

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

// Demo club login credentials for easy reference
define('DEMO_CREDENTIALS', [
    'marcus.thompson@thunderbay.com' => 'thunder123',
    'sofia.rodriguez@goldeneagles.com' => 'eagles123',
    'david.chen@crystalwolves.com' => 'wolves123',
    'emma.johnson@phoenixrising.com' => 'phoenix123',
    'alessandro.rossi@midnightstrikers.com' => 'midnight123',
    'priya.patel@velocityfc.com' => 'velocity123'
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
// This function reads assets/json/players.json and converts it to the expected PHP array format
// Includes caching, error handling, and data validation
function loadPlayersFromJson()
{
    static $cachedPlayers = null;

    // Return cached data if already loaded
    if ($cachedPlayers !== null) {
        return $cachedPlayers;
    }

    $jsonDir = __DIR__ . '/../assets/json/';
    $players = [];

    // Get all JSON files in the assets/json directory
    $jsonFiles = glob($jsonDir . '*.json');

    if (empty($jsonFiles)) {
        error_log("Dream Team: No JSON files found in assets/json directory");
        $cachedPlayers = [];
        return $cachedPlayers;
    }

    foreach ($jsonFiles as $jsonFile) {
        $filename = basename($jsonFile);

        if (!file_exists($jsonFile)) {
            error_log("Dream Team: JSON file not found: " . $filename);
            continue;
        }

        $jsonContent = file_get_contents($jsonFile);
        if ($jsonContent === false) {
            error_log("Dream Team: Failed to read JSON file: " . $filename);
            continue;
        }

        $playersData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Dream Team: Invalid JSON in " . $filename . " - " . json_last_error_msg());
            continue;
        }

        if (!is_array($playersData)) {
            error_log("Dream Team: " . $filename . " must contain an array of players");
            continue;
        }

        // Convert JSON objects to the expected array format
        foreach ($playersData as $index => $player) {
            // Validate required fields
            if (!isset($player['name']) || !isset($player['position']) || !isset($player['rating']) || !isset($player['value']) || !isset($player['uuid'])) {
                error_log("Dream Team: Invalid player data in " . $filename . " at index $index - missing required fields (name, position, rating, value, uuid)");
                continue;
            }

            // Determine player category based on filename
            $category = 'modern';
            if (strpos($filename, 'legend') !== false) {
                $category = 'legend';
            } elseif (strpos($filename, 'young') !== false) {
                $category = 'young';
            } elseif (strpos($filename, 'standard') !== false) {
                $category = 'standard';
            }

            $players[] = [
                'uuid' => $player['uuid'] ?? null,
                'name' => (string) $player['name'],
                'position' => (string) $player['position'],
                'rating' => (int) $player['rating'],
                'value' => (int) $player['value'],
                'age' => $player['age'] ?? null,
                'height' => $player['height'] ?? 'Unknown',
                'weight' => $player['weight'] ?? 'Unknown',
                'foot' => $player['foot'] ?? 'Right',
                'nation' => $player['nation'] ?? null,
                'avatar' => $player['avatar'] ?? null,
                'playablePositions' => $player['playablePositions'] ?? [$player['position']],
                'attributes' => $player['attributes'] ?? null,
                'club' => $player['club'] ?? 'Free Agent',
                'description' => $player['description'] ?? 'Professional football player.',
                'category' => $category,
                'source_file' => $filename
            ];
        }

        error_log("Dream Team: Loaded " . count($playersData) . " players from " . $filename);
    }

    error_log("Dream Team: Total players loaded: " . count($players));
    $cachedPlayers = $players;
    return $cachedPlayers;
}

// Default player database - Loaded from assets/json/players.json
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

// League System Constants
define('FAKE_CLUBS', [
    'Thunder Bay United',
    'Golden Eagles FC',
    'Crystal Wolves',
    'Phoenix Rising',
    'Midnight Strikers',
    'Velocity FC',
    'Storm City FC',
    'Iron Lions',
    'Silver Hawks United',
    'Crimson Tigers',
    'Azure Dragons',
    'Emerald Panthers',
    'Royal Falcons',
    'Shadow Wolves',
    'Lightning Bolts',
    'Fire Phoenixes',
    'Ice Bears United',
    'Wind Runners FC',
    'Earth Guardians'
]);

define('CHAMPIONSHIP_CLUBS', [
    'Titan Rovers',
    'Mystic United',
    'Blazing Stars FC',
    'Ocean Waves',
    'Mountain Lions',
    'Desert Eagles',
    'Forest Rangers FC',
    'Steel Warriors',
    'Cosmic Wanderers',
    'Thunder Wolves',
    'Glacier FC',
    'Sunset Strikers',
    'Neon Knights',
    'Phantom Raiders',
    'Solar Flares FC',
    'Arctic Foxes',
    'Volcanic United',
    'Tornado FC',
    'Lightning Hawks',
    'Storm Breakers',
    'Diamond Crusaders',
    'Emerald City FC',
    'Ruby Rangers',
    'Sapphire United'
]);

// Staff System Constants
define('STAFF_COSTS', [
    'head_coach' => [
        'name' => 'Head Coach',
        'description' => 'Improves team performance and player development',
        'icon' => 'user-check',
        'candidates' => [
            ['name' => 'Marcus Rodriguez', 'specialty' => 'Defensive tactics', 'experience' => 'Elite'],
            ['name' => 'Alessandro Fontana', 'specialty' => 'Possession football', 'experience' => 'Elite'],
            ['name' => 'Viktor Petrov', 'specialty' => 'High-intensity pressing', 'experience' => 'Elite'],
            ['name' => 'Giovanni Rossi', 'specialty' => 'Man management', 'experience' => 'Elite'],
            ['name' => 'Diego Martinez', 'specialty' => 'Tactical discipline', 'experience' => 'Elite'],
            ['name' => 'Carlos Mendoza', 'specialty' => 'Counter-attacking', 'experience' => 'Elite'],
            ['name' => 'Stefan Mueller', 'specialty' => 'Flexible formations', 'experience' => 'Elite'],
            ['name' => 'Laurent Dubois', 'specialty' => 'Player motivation', 'experience' => 'Elite']
        ],
        'levels' => [
            1 => ['cost' => 5000000, 'salary' => 100000, 'bonus' => 'Team gets +2 overall rating'],
            2 => ['cost' => 15000000, 'salary' => 250000, 'bonus' => 'Team gets +4 overall rating'],
            3 => ['cost' => 35000000, 'salary' => 500000, 'bonus' => 'Team gets +6 overall rating'],
            4 => ['cost' => 75000000, 'salary' => 1000000, 'bonus' => 'Team gets +8 overall rating'],
            5 => ['cost' => 150000000, 'salary' => 2000000, 'bonus' => 'Team gets +10 overall rating']
        ]
    ],
    'fitness_coach' => [
        'name' => 'Fitness Coach',
        'description' => 'Reduces player fatigue and injury risk',
        'icon' => 'dumbbell',
        'candidates' => [
            ['name' => 'Roberto Silva', 'specialty' => 'Injury prevention', 'experience' => 'Elite'],
            ['name' => 'Michael Thompson', 'specialty' => 'High-intensity training', 'experience' => 'Elite'],
            ['name' => 'Hans Bergmann', 'specialty' => 'Periodization', 'experience' => 'Elite'],
            ['name' => 'Paulo Santos', 'specialty' => 'Recovery methods', 'experience' => 'Elite'],
            ['name' => 'Andrea Bianchi', 'specialty' => 'Strength conditioning', 'experience' => 'Elite'],
            ['name' => 'Fernando Lopez', 'specialty' => 'Endurance training', 'experience' => 'Elite']
        ],
        'levels' => [
            1 => ['cost' => 3000000, 'salary' => 75000, 'bonus' => 'Players lose 20% less fitness'],
            2 => ['cost' => 8000000, 'salary' => 150000, 'bonus' => 'Players lose 35% less fitness'],
            3 => ['cost' => 20000000, 'salary' => 300000, 'bonus' => 'Players lose 50% less fitness'],
            4 => ['cost' => 45000000, 'salary' => 600000, 'bonus' => 'Players lose 65% less fitness'],
            5 => ['cost' => 100000000, 'salary' => 1200000, 'bonus' => 'Players lose 80% less fitness']
        ]
    ],
    'scout' => [
        'name' => 'Scout',
        'description' => 'Discovers better players and provides detailed reports',
        'icon' => 'search',
        'candidates' => [
            ['name' => 'Eduardo Campos', 'specialty' => 'Young talent identification', 'experience' => 'Elite'],
            ['name' => 'James Mitchell', 'specialty' => 'Data-driven scouting', 'experience' => 'Elite'],
            ['name' => 'Antonio Benitez', 'specialty' => 'Technical players', 'experience' => 'Elite'],
            ['name' => 'Ricardo Montes', 'specialty' => 'Value signings', 'experience' => 'Elite'],
            ['name' => 'David Edwards', 'specialty' => 'Market analysis', 'experience' => 'Elite'],
            ['name' => 'Marco Bertoli', 'specialty' => 'South American talent', 'experience' => 'Elite']
        ],
        'levels' => [
            1 => ['cost' => 2000000, 'salary' => 50000, 'bonus' => 'Reveals basic player stats'],
            2 => ['cost' => 6000000, 'salary' => 100000, 'bonus' => 'Reveals detailed player stats'],
            3 => ['cost' => 15000000, 'salary' => 200000, 'bonus' => 'Finds hidden gem players'],
            4 => ['cost' => 35000000, 'salary' => 400000, 'bonus' => 'Predicts player potential'],
            5 => ['cost' => 80000000, 'salary' => 800000, 'bonus' => 'Discovers world-class talents']
        ]
    ],
    'youth_coach' => [
        'name' => 'Youth Coach',
        'description' => 'Develops young players and academy prospects',
        'icon' => 'graduation-cap',
        'candidates' => [
            ['name' => 'Xavier Hernandez', 'specialty' => 'Technical development', 'experience' => 'Elite'],
            ['name' => 'Frank Lambert', 'specialty' => 'Youth integration', 'experience' => 'Elite'],
            ['name' => 'Miguel Arteta', 'specialty' => 'Tactical education', 'experience' => 'Elite'],
            ['name' => 'Patrick Vieira', 'specialty' => 'Leadership training', 'experience' => 'Elite'],
            ['name' => 'Thierry Henri', 'specialty' => 'Attacking play', 'experience' => 'Elite'],
            ['name' => 'Andrea Pirelli', 'specialty' => 'Vision and creativity', 'experience' => 'Elite']
        ],
        'levels' => [
            1 => ['cost' => 4000000, 'salary' => 80000, 'bonus' => 'Young players develop 25% faster'],
            2 => ['cost' => 10000000, 'salary' => 175000, 'bonus' => 'Young players develop 50% faster'],
            3 => ['cost' => 25000000, 'salary' => 350000, 'bonus' => 'Young players develop 75% faster'],
            4 => ['cost' => 55000000, 'salary' => 700000, 'bonus' => 'Young players develop 100% faster'],
            5 => ['cost' => 120000000, 'salary' => 1400000, 'bonus' => 'Generates academy prospects']
        ]
    ],
    'medical_staff' => [
        'name' => 'Medical Staff',
        'description' => 'Treats injuries and maintains player health',
        'icon' => 'heart',
        'candidates' => [
            ['name' => 'Dr. Ricardo Pruna', 'specialty' => 'Injury prevention', 'experience' => 'Elite'],
            ['name' => 'Dr. Hans Mueller', 'specialty' => 'Sports medicine', 'experience' => 'Elite'],
            ['name' => 'Dr. Eva Carneiro', 'specialty' => 'Match-day medicine', 'experience' => 'Elite'],
            ['name' => 'Dr. Francesco Escola', 'specialty' => 'Rehabilitation', 'experience' => 'Elite'],
            ['name' => 'Dr. Bruno Mazzini', 'specialty' => 'Muscle injuries', 'experience' => 'Elite'],
            ['name' => 'Dr. Jorge Ardevol', 'specialty' => 'Recovery protocols', 'experience' => 'Elite']
        ],
        'levels' => [
            1 => ['cost' => 3500000, 'salary' => 70000, 'bonus' => 'Reduces injury duration by 25%'],
            2 => ['cost' => 9000000, 'salary' => 140000, 'bonus' => 'Reduces injury duration by 40%'],
            3 => ['cost' => 22000000, 'salary' => 280000, 'bonus' => 'Reduces injury duration by 55%'],
            4 => ['cost' => 50000000, 'salary' => 560000, 'bonus' => 'Reduces injury duration by 70%'],
            5 => ['cost' => 110000000, 'salary' => 1120000, 'bonus' => 'Prevents most injuries']
        ]
    ]
]);

// Stadium System Constants
define('STADIUM_LEVELS', [
    1 => [
        'name' => 'Basic Stadium',
        'capacity' => 10000,
        'upgrade_cost' => 5000000, // 5M to upgrade to level 2
        'revenue_multiplier' => 1.0,
        'description' => 'A modest stadium with basic facilities'
    ],
    2 => [
        'name' => 'Community Stadium',
        'capacity' => 20000,
        'upgrade_cost' => 15000000, // 15M to upgrade to level 3
        'revenue_multiplier' => 1.2,
        'description' => 'Improved facilities with better seating and amenities'
    ],
    3 => [
        'name' => 'Professional Stadium',
        'capacity' => 35000,
        'upgrade_cost' => 30000000, // 30M to upgrade to level 4
        'revenue_multiplier' => 1.5,
        'description' => 'Modern stadium with premium facilities and corporate boxes'
    ],
    4 => [
        'name' => 'Elite Stadium',
        'capacity' => 50000,
        'upgrade_cost' => 60000000, // 60M to upgrade to level 5
        'revenue_multiplier' => 1.8,
        'description' => 'State-of-the-art stadium with luxury amenities'
    ],
    5 => [
        'name' => 'Legendary Stadium',
        'capacity' => 75000,
        'upgrade_cost' => null, // Max level
        'revenue_multiplier' => 2.2,
        'description' => 'Iconic stadium that attracts fans from around the world'
    ]
]);

define('STADIUM_FEATURES', [
    1 => ['Basic Seating', 'Concession Stands'],
    2 => ['Improved Seating', 'Food Courts', 'Parking'],
    3 => ['Premium Seating', 'Corporate Boxes', 'VIP Lounges'],
    4 => ['Luxury Suites', 'Media Center', 'Player Facilities'],
    5 => ['World-Class Amenities', 'Museum', 'Training Complex']
]);

// Injury System Constants
define('INJURY_TYPES', [
    'minor_strain' => [
        'name' => 'Minor Muscle Strain',
        'duration_days' => [3, 7], // min, max range
        'fitness_penalty' => [10, 20], // min, max range
        'probability' => 0.8 // 80% of injuries are minor
    ],
    'muscle_injury' => [
        'name' => 'Muscle Injury',
        'duration_days' => [7, 14],
        'fitness_penalty' => [20, 35],
        'probability' => 0.15 // 15% of injuries
    ],
    'serious_injury' => [
        'name' => 'Serious Injury',
        'duration_days' => [14, 28],
        'fitness_penalty' => [35, 50],
        'probability' => 0.05 // 5% of injuries are serious
    ]
]);

// Scouting System Constants
define('SCOUTING_COSTS', [
    'basic' => 100000,    // €100K - Basic report
    'detailed' => 250000, // €250K - Detailed report
    'premium' => 500000   // €500K - Premium report
]);

define('SCOUTING_QUALITY_NAMES', [
    1 => 'Basic',
    2 => 'Detailed',
    3 => 'Premium'
]);

define('POSITION_MAPPING', [
    'GK' => 'GK',
    'CB' => 'DEF',
    'LB' => 'DEF',
    'RB' => 'DEF',
    'LWB' => 'DEF',
    'RWB' => 'DEF',
    'DEF' => 'DEF',
    'CDM' => 'MID',
    'CM' => 'MID',
    'CAM' => 'MID',
    'LM' => 'MID',
    'RM' => 'MID',
    'LW' => 'MID',
    'RW' => 'MID',
    'MID' => 'MID',
    'CF' => 'FWD',
    'ST' => 'FWD',
    'LF' => 'FWD',
    'RF' => 'FWD',
    'FWD' => 'FWD'
]);

define('FORMATION_REQUIREMENTS', [
    '4-4-2' => ['GK' => 1, 'DEF' => 4, 'MID' => 4, 'FWD' => 2],
    '4-3-3' => ['GK' => 1, 'DEF' => 4, 'MID' => 3, 'FWD' => 3],
    '3-5-2' => ['GK' => 1, 'DEF' => 3, 'MID' => 5, 'FWD' => 2],
    '4-5-1' => ['GK' => 1, 'DEF' => 4, 'MID' => 5, 'FWD' => 1],
    '5-3-2' => ['GK' => 1, 'DEF' => 5, 'MID' => 3, 'FWD' => 2],
    '3-4-3' => ['GK' => 1, 'DEF' => 3, 'MID' => 4, 'FWD' => 3],
    '4-2-3-1' => ['GK' => 1, 'DEF' => 4, 'MID' => 5, 'FWD' => 1],
    '5-4-1' => ['GK' => 1, 'DEF' => 5, 'MID' => 4, 'FWD' => 1],
    '3-5-1-1' => ['GK' => 1, 'DEF' => 3, 'MID' => 6, 'FWD' => 1]
]);

// Support System Constants
define('SUPPORT_CATEGORIES', [
    'account' => 'Account Issues',
    'payment' => 'Payment Problems',
    'billing' => 'Billing Questions',
    'technical' => 'Technical Issues',
    'gameplay' => 'Gameplay Problems',
    'purchase' => 'Purchase Issues',
    'refund' => 'Refund Request',
    'subscription' => 'Subscription Issues',
    'data_loss' => 'Data Loss/Recovery',
    'other' => 'Other Support Request'
]);

define('SUPPORT_PRIORITIES', [
    'low' => 'Low Priority',
    'medium' => 'Medium Priority',
    'high' => 'High Priority',
    'urgent' => 'Urgent'
]);

// Helper functions for accessing constants
function getStaffCosts()
{
    return STAFF_COSTS;
}

function getStadiumLevels()
{
    return STADIUM_LEVELS;
}

function getStadiumFeatures()
{
    return STADIUM_FEATURES;
}

function getInjuryTypes()
{
    return INJURY_TYPES;
}

function getScoutingCosts()
{
    return SCOUTING_COSTS;
}

function getPositionMapping()
{
    return POSITION_MAPPING;
}

function getFormationRequirements()
{
    return FORMATION_REQUIREMENTS;
}

function getSupportCategories()
{
    return SUPPORT_CATEGORIES;
}

function getSupportPriorities()
{
    return SUPPORT_PRIORITIES;
}

