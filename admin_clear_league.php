<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';
require_once 'includes/auth_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = getDbConnection();
$user_id = $_SESSION['user_id'];
$current_season = date('Y');

// Get league status
$stmt = $db->prepare('SELECT COUNT(*) as count FROM league_teams WHERE season = :season');
$stmt->bindValue(':season', $current_season, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$league_exists = $row['count'] > 0;

// Get match count
$stmt = $db->prepare('SELECT COUNT(*) as count FROM league_matches WHERE season = :season');
$stmt->bindValue(':season', $current_season, SQLITE3_INTEGER);
$result = $stmt->execute();
$match_row = $result->fetchArray(SQLITE3_ASSOC);
$match_count = $match_row['count'];

$db->close();

startContent();
?>

<div class="container mx-auto py-6 max-w-2xl">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i data-lucide="trash-2" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Clear League (Testing)</h1>
                    <p class="text-red-100">Reset league data for testing purposes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- League Status -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
        <h3 class="font-bold text-lg text-gray-900 mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
            Current League Status
        </h3>
        
        <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-gray-700">Season:</span>
                <span class="font-bold text-gray-900"><?php echo $current_season; ?></span>
            </div>
            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-gray-700">League Status:</span>
                <span class="font-bold <?php echo $league_exists ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $league_exists ? '✓ League Exists' : '✗ No League'; ?>
                </span>
            </div>
            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-gray-700">Teams:</span>
                <span class="font-bold text-gray-900"><?php echo $league_exists ? '40 teams' : '0 teams'; ?></span>
            </div>
            
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="text-gray-700">Matches:</span>
                <span class="font-bold text-gray-900"><?php echo $match_count; ?> matches</span>
            </div>
        </div>
    </div>

    <!-- Clear League Action -->
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
        <h3 class="font-bold text-lg text-gray-900 mb-4 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-orange-600"></i>
            Clear League Data
        </h3>
        
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800 text-sm">
                <strong>Warning:</strong> This will permanently delete all league data for season <?php echo $current_season; ?>, including:
            </p>
            <ul class="text-red-700 text-sm mt-2 ml-4 list-disc">
                <li>All 40 teams (user team + 39 AI teams)</li>
                <li>All <?php echo $match_count; ?> matches and fixtures</li>
                <li>All match results and statistics</li>
            </ul>
            <p class="text-red-800 text-sm mt-3">
                This action cannot be undone. You will need to create a new league to continue.
            </p>
        </div>

        <?php if ($league_exists): ?>
            <button id="clear-league-btn" 
                    class="w-full bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 font-bold transition-colors flex items-center justify-center gap-2">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
                Clear League for Season <?php echo $current_season; ?>
            </button>
        <?php else: ?>
            <div class="bg-gray-100 text-gray-600 px-6 py-3 rounded-lg text-center">
                <i data-lucide="check-circle" class="w-5 h-5 inline mr-2"></i>
                No league to clear. League is already empty.
            </div>
        <?php endif; ?>
    </div>

    <!-- Back Link -->
    <div class="text-center mt-6">
        <a href="league.php" class="text-blue-600 hover:text-blue-800 font-medium flex items-center justify-center gap-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to League
        </a>
    </div>
</div>

<script>
    document.getElementById('clear-league-btn')?.addEventListener('click', function() {
        if (confirm('Are you absolutely sure? This will delete all league data for season <?php echo $current_season; ?>.\n\nThis action cannot be undone.')) {
            const formData = new FormData();
            formData.append('season', <?php echo $current_season; ?>);

            fetch('api/clear_league_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('League cleared successfully!\n\nYou can now create a new league.');
                    window.location.href = 'league.php';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to clear league. Check console for details.');
            });
        }
    });
</script>

<?php
endContent('Clear League - Testing');
?>
