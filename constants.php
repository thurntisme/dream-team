<?php
// Dream Team Constants - Formations and Player Data

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

// Default player database
define('DEFAULT_PLAYERS', [
    // Goalkeepers
    ['name' => 'Alisson', 'position' => 'GK', 'rating' => 89, 'value' => 65000000],
    ['name' => 'Ederson', 'position' => 'GK', 'rating' => 88, 'value' => 40000000],
    ['name' => 'Courtois', 'position' => 'GK', 'rating' => 87, 'value' => 35000000],
    ['name' => 'Neuer', 'position' => 'GK', 'rating' => 86, 'value' => 15000000],

    // Centre Backs
    ['name' => 'Van Dijk', 'position' => 'CB', 'rating' => 90, 'value' => 70000000],
    ['name' => 'Ramos', 'position' => 'CB', 'rating' => 88, 'value' => 25000000],
    ['name' => 'Dias', 'position' => 'CB', 'rating' => 87, 'value' => 80000000],
    ['name' => 'Marquinhos', 'position' => 'CB', 'rating' => 86, 'value' => 60000000],
    ['name' => 'Koulibaly', 'position' => 'CB', 'rating' => 85, 'value' => 40000000],
    ['name' => 'Varane', 'position' => 'CB', 'rating' => 84, 'value' => 35000000],
    ['name' => 'Laporte', 'position' => 'CB', 'rating' => 83, 'value' => 50000000],

    // Full Backs
    ['name' => 'Robertson', 'position' => 'LB', 'rating' => 85, 'value' => 45000000],
    ['name' => 'Cancelo', 'position' => 'RB', 'rating' => 84, 'value' => 50000000],
    ['name' => 'Davies', 'position' => 'LB', 'rating' => 83, 'value' => 70000000],
    ['name' => 'Walker', 'position' => 'RB', 'rating' => 82, 'value' => 30000000],
    ['name' => 'Mendy', 'position' => 'LB', 'rating' => 81, 'value' => 35000000],
    ['name' => 'Hakimi', 'position' => 'RB', 'rating' => 84, 'value' => 60000000],

    // Defensive Midfielders
    ['name' => 'Casemiro', 'position' => 'CDM', 'rating' => 85, 'value' => 40000000],
    ['name' => 'Kante', 'position' => 'CDM', 'rating' => 87, 'value' => 35000000],
    ['name' => 'Fabinho', 'position' => 'CDM', 'rating' => 84, 'value' => 45000000],
    ['name' => 'Rodri', 'position' => 'CDM', 'rating' => 85, 'value' => 80000000],

    // Central Midfielders
    ['name' => 'Modric', 'position' => 'CM', 'rating' => 88, 'value' => 20000000],
    ['name' => 'Kroos', 'position' => 'CM', 'rating' => 86, 'value' => 15000000],
    ['name' => 'Pedri', 'position' => 'CM', 'rating' => 84, 'value' => 90000000],
    ['name' => 'Bellingham', 'position' => 'CM', 'rating' => 85, 'value' => 120000000],
    ['name' => 'Gavi', 'position' => 'CM', 'rating' => 82, 'value' => 80000000],
    ['name' => 'Verratti', 'position' => 'CM', 'rating' => 85, 'value' => 55000000],

    // Attacking Midfielders
    ['name' => 'De Bruyne', 'position' => 'CAM', 'rating' => 91, 'value' => 100000000],
    ['name' => 'Bruno Fernandes', 'position' => 'CAM', 'rating' => 86, 'value' => 75000000],
    ['name' => 'Muller', 'position' => 'CAM', 'rating' => 85, 'value' => 25000000],
    ['name' => 'Odegaard', 'position' => 'CAM', 'rating' => 83, 'value' => 70000000],

    // Wingers
    ['name' => 'Vinicius Jr', 'position' => 'LW', 'rating' => 84, 'value' => 120000000],
    ['name' => 'Salah', 'position' => 'RW', 'rating' => 87, 'value' => 65000000],
    ['name' => 'Mane', 'position' => 'LW', 'rating' => 86, 'value' => 40000000],
    ['name' => 'Mahrez', 'position' => 'RW', 'rating' => 84, 'value' => 30000000],
    ['name' => 'Neymar', 'position' => 'LW', 'rating' => 85, 'value' => 90000000],
    ['name' => 'Saka', 'position' => 'RW', 'rating' => 83, 'value' => 90000000],

    // Strikers
    ['name' => 'Mbappe', 'position' => 'ST', 'rating' => 92, 'value' => 180000000],
    ['name' => 'Haaland', 'position' => 'ST', 'rating' => 91, 'value' => 170000000],
    ['name' => 'Benzema', 'position' => 'ST', 'rating' => 89, 'value' => 35000000],
    ['name' => 'Lewandowski', 'position' => 'ST', 'rating' => 88, 'value' => 45000000],
    ['name' => 'Kane', 'position' => 'ST', 'rating' => 87, 'value' => 100000000],

    // Centre Forwards
    ['name' => 'Osimhen', 'position' => 'CF', 'rating' => 85, 'value' => 120000000],
    ['name' => 'Vlahovic', 'position' => 'CF', 'rating' => 84, 'value' => 70000000],
    ['name' => 'Nunez', 'position' => 'CF', 'rating' => 83, 'value' => 75000000],

    // Wing-Backs
    ['name' => 'Theo Hernandez', 'position' => 'LWB', 'rating' => 84, 'value' => 60000000],
    ['name' => 'Chilwell', 'position' => 'LWB', 'rating' => 82, 'value' => 45000000],
    ['name' => 'Gosens', 'position' => 'LWB', 'rating' => 81, 'value' => 25000000],
    ['name' => 'Reece James', 'position' => 'RWB', 'rating' => 84, 'value' => 65000000],
    ['name' => 'Dumfries', 'position' => 'RWB', 'rating' => 82, 'value' => 40000000],
    ['name' => 'Perisic', 'position' => 'LWB', 'rating' => 83, 'value' => 20000000],

    // Side Midfielders
    ['name' => 'Kostic', 'position' => 'LM', 'rating' => 82, 'value' => 25000000],
    ['name' => 'Cuadrado', 'position' => 'RM', 'rating' => 83, 'value' => 15000000],
    ['name' => 'Spinazzola', 'position' => 'LM', 'rating' => 81, 'value' => 30000000],
    ['name' => 'Berardi', 'position' => 'RM', 'rating' => 82, 'value' => 35000000],
    ['name' => 'Chiesa', 'position' => 'LM', 'rating' => 84, 'value' => 70000000],
    ['name' => 'Di Maria', 'position' => 'RM', 'rating' => 85, 'value' => 20000000]
]);

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