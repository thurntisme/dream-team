<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';

// Check if user is logged in and has club name
requireClubName('player_stats');

$db = getDbConnection();
$userId = $_SESSION['user_id'];

// Create player_stats table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS player_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    player_id TEXT NOT NULL,
    player_name TEXT NOT NULL,
    position TEXT NOT NULL,
    matches_played INTEGER DEFAULT 0,
    goals INTEGER DEFAULT 0,
    assists INTEGER DEFAULT 0,
    yellow_cards INTEGER DEFAULT 0,
    red_cards INTEGER DEFAULT 0,
    total_rating REAL DEFAULT 0,
    avg_rating REAL DEFAULT 0,
    clean_sheets INTEGER DEFAULT 0,
    saves INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id),
    UNIQUE(user_id, player_id)
)');

// Migrate existing table to use REAL for total_rating if needed
try {
    $db->exec('ALTER TABLE player_stats ADD COLUMN total_rating_new REAL DEFAULT 0');
    $db->exec('UPDATE player_stats SET total_rating_new = CAST(total_rating AS REAL) / 10.0 WHERE total_rating > 0');
    $db->exec('ALTER TABLE player_stats DROP COLUMN total_rating');
    $db->exec('ALTER TABLE player_stats RENAME COLUMN total_rating_new TO total_rating');
} catch (Exception $e) {
    // Migration already done or table structure is correct
}

// Get user data
$stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

// Get current team and substitutes
$team = json_decode($user['team'] ?? '[]', true) ?: [];
$substitutes = json_decode($user['substitutes'] ?? '[]', true) ?: [];
$allPlayers = array_merge(array_filter($team), array_filter($substitutes));

// Initialize player stats for new players
foreach ($allPlayers as $player) {
    if (!$player || !isset($player['name']))
        continue;

    $playerId = $player['id'] ?? $player['name'];
    $stmt = $db->prepare('INSERT OR IGNORE INTO player_stats (user_id, player_id, player_name, position) VALUES (:user_id, :player_id, :player_name, :position)');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':player_id', $playerId, SQLITE3_TEXT);
    $stmt->bindValue(':player_name', $player['name'], SQLITE3_TEXT);
    $stmt->bindValue(':position', $player['position'] ?? 'Unknown', SQLITE3_TEXT);
    $stmt->execute();
}

// Get player statistics
$stmt = $db->prepare('
    SELECT ps.*, 
           CASE WHEN ps.matches_played > 0 THEN ROUND(CAST(ps.total_rating AS REAL) / ps.matches_played, 1) ELSE 0 END as calculated_avg_rating
    FROM player_stats ps 
    WHERE ps.user_id = :user_id 
    ORDER BY ps.matches_played DESC, ps.goals DESC, ps.assists DESC
');
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$playerStats = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $playerStats[] = $row;
}

// Calculate team totals
$teamTotals = [
    'matches_played' => 0,
    'goals' => 0,
    'assists' => 0,
    'yellow_cards' => 0,
    'red_cards' => 0,
    'clean_sheets' => 0,
    'saves' => 0,
    'total_players' => count($playerStats)
];

foreach ($playerStats as $stat) {
    $teamTotals['matches_played'] += $stat['matches_played'];
    $teamTotals['goals'] += $stat['goals'];
    $teamTotals['assists'] += $stat['assists'];
    $teamTotals['yellow_cards'] += $stat['yellow_cards'];
    $teamTotals['red_cards'] += $stat['red_cards'];
    $teamTotals['clean_sheets'] += $stat['clean_sheets'];
    $teamTotals['saves'] += $stat['saves'];
}

$db->close();

startContent();
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="bar-chart-3" class="w-8 h-8 text-blue-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Player Statistics</h1>
                <p class="text-gray-600">Detailed performance statistics for your squad</p>
            </div>
        </div>
        <div class="text-right">
            <a href="team.php"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition-colors flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Team
            </a>
        </div>
    </div>

    <!-- Team Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo $teamTotals['total_players']; ?></div>
                <div class="text-sm text-gray-600">Players</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?php echo $teamTotals['goals']; ?></div>
                <div class="text-sm text-gray-600">Goals</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600"><?php echo $teamTotals['assists']; ?></div>
                <div class="text-sm text-gray-600">Assists</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-600"><?php echo $teamTotals['matches_played']; ?></div>
                <div class="text-sm text-gray-600">Total Matches</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600"><?php echo $teamTotals['yellow_cards']; ?></div>
                <div class="text-sm text-gray-600">Yellow Cards</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600"><?php echo $teamTotals['red_cards']; ?></div>
                <div class="text-sm text-gray-600">Red Cards</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-cyan-600"><?php echo $teamTotals['clean_sheets']; ?></div>
                <div class="text-sm text-gray-600">Clean Sheets</div>
            </div>
        </div>
    </div>

    <!-- Player Statistics Table -->
    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-gray-600"></i>
                Individual Player Statistics
            </h3>
        </div>

        <?php if (empty($playerStats)): ?>
            <div class="p-8 text-center">
                <i data-lucide="bar-chart-3" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Statistics Available</h3>
                <p class="text-gray-600">Player statistics will appear here after playing matches.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Player</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Position</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Matches</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Avg
                                Rating</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Goals</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Assists</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i data-lucide="square" class="w-4 h-4 text-yellow-500 inline"></i>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i data-lucide="square" class="w-4 h-4 text-red-500 inline"></i>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Clean Sheets</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Saves</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($playerStats as $stat): ?>
                            <?php
                            // Find current player data for additional info
                            $currentPlayer = null;
                            foreach ($allPlayers as $player) {
                                if (($player['id'] ?? $player['name']) === $stat['player_id']) {
                                    $currentPlayer = $player;
                                    break;
                                }
                            }
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                                            <span class="text-white font-bold text-sm">
                                                <?php echo $currentPlayer ? $currentPlayer['rating'] : '?'; ?>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($stat['player_name']); ?>
                                            </div>
                                            <?php if ($currentPlayer): ?>
                                                <div class="text-sm text-gray-500">
                                                    Age: <?php echo $currentPlayer['age'] ?? 'Unknown'; ?> •
                                                    Value: <?php echo formatMarketValue($currentPlayer['value'] ?? 0); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <?php echo htmlspecialchars($stat['position']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center font-medium"><?php echo $stat['matches_played']; ?></td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($stat['matches_played'] > 0): ?>
                                        <span
                                            class="font-medium <?php echo $stat['calculated_avg_rating'] >= 7.5 ? 'text-green-600' : ($stat['calculated_avg_rating'] >= 6.5 ? 'text-blue-600' : 'text-gray-600'); ?>">
                                            <?php echo number_format($stat['calculated_avg_rating'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="font-medium text-green-600"><?php echo $stat['goals']; ?></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="font-medium text-purple-600"><?php echo $stat['assists']; ?></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($stat['yellow_cards'] > 0): ?>
                                        <span class="font-medium text-yellow-600"><?php echo $stat['yellow_cards']; ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($stat['red_cards'] > 0): ?>
                                        <span class="font-medium text-red-600"><?php echo $stat['red_cards']; ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($stat['position'] === 'GK' && $stat['clean_sheets'] > 0): ?>
                                        <span class="font-medium text-cyan-600"><?php echo $stat['clean_sheets']; ?></span>
                                    <?php elseif ($stat['position'] === 'GK'): ?>
                                        <span class="text-gray-400">0</span>
                                    <?php else: ?>
                                        <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if ($stat['position'] === 'GK' && $stat['saves'] > 0): ?>
                                        <span class="font-medium text-blue-600"><?php echo $stat['saves']; ?></span>
                                    <?php elseif ($stat['position'] === 'GK'): ?>
                                        <span class="text-gray-400">0</span>
                                    <?php else: ?>
                                        <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Legend -->
    <div class="mt-6 bg-gray-50 rounded-lg p-4">
        <h4 class="font-semibold text-gray-900 mb-3">Statistics Legend</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center gap-2">
                <i data-lucide="target" class="w-4 h-4 text-green-600"></i>
                <span><strong>Goals:</strong> Total goals scored by the player</span>
            </div>
            <div class="flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-purple-600"></i>
                <span><strong>Assists:</strong> Goals assisted by the player</span>
            </div>
            <div class="flex items-center gap-2">
                <i data-lucide="star" class="w-4 h-4 text-blue-600"></i>
                <span><strong>Avg Rating:</strong> Average match performance rating</span>
            </div>
            <div class="flex items-center gap-2">
                <i data-lucide="square" class="w-4 h-4 text-yellow-500"></i>
                <span><strong>Yellow Cards:</strong> Disciplinary warnings received</span>
            </div>
            <div class="flex items-center gap-2">
                <i data-lucide="square" class="w-4 h-4 text-red-500"></i>
                <span><strong>Red Cards:</strong> Ejections from matches</span>
            </div>
            <div class="flex items-center gap-2">
                <i data-lucide="shield" class="w-4 h-4 text-cyan-600"></i>
                <span><strong>Clean Sheets:</strong> Matches without conceding (GK only)</span>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

<?php
endContent('Player Statistics', 'player_stats');
?>