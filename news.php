<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = getDbConnection();
$userId = $_SESSION['user_id'];

// Create news table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS news (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    priority TEXT NOT NULL DEFAULT "normal",
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    player_data TEXT,
    actions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id)
)');

// Get user data
$stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

// Clean up expired news and generate new items
$newsItems = manageNewsItems($db, $userId);

$db->close();

startContent();
?>

<div class="container mx-auto py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="newspaper" class="w-8 h-8 text-blue-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Football News</h1>
                <p class="text-gray-600">Latest transfer rumors, injuries, and club updates</p>
            </div>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-500">Last Updated</div>
            <div class="text-lg font-medium text-gray-900"><?php echo date('M j, Y H:i'); ?></div>
        </div>
    </div>

    <!-- News Categories -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
            <i data-lucide="trending-up" class="w-8 h-8 text-red-600 mx-auto mb-2"></i>
            <h3 class="font-semibold text-red-900">Hot Transfers</h3>
            <p class="text-sm text-red-700">Latest market moves</p>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
            <i data-lucide="user-x" class="w-8 h-8 text-orange-600 mx-auto mb-2"></i>
            <h3 class="font-semibold text-orange-900">Departure Requests</h3>
            <p class="text-sm text-orange-700">Players wanting to leave</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
            <i data-lucide="user-plus" class="w-8 h-8 text-green-600 mx-auto mb-2"></i>
            <h3 class="font-semibold text-green-900">Interest</h3>
            <p class="text-sm text-green-700">Players wanting to join</p>
        </div>
    </div>

    <!-- News Feed -->
    <div class="space-y-6">
        <?php if (empty($newsItems)): ?>
            <div class="text-center py-12">
                <i data-lucide="newspaper" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No News Available</h3>
                <p class="text-gray-600">Check back later for the latest football news and updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($newsItems as $news): ?>
                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden"
                    data-news-id="<?php echo $news['id']; ?>">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center <?php echo getNewsCategoryStyle($news['category'])['bg']; ?>">
                                    <i data-lucide="<?php echo getNewsCategoryStyle($news['category'])['icon']; ?>"
                                        class="w-5 h-5 <?php echo getNewsCategoryStyle($news['category'])['text']; ?>"></i>
                                </div>
                                <div>
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo getNewsCategoryStyle($news['category'])['badge']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $news['category'])); ?>
                                    </span>
                                    <div class="text-sm text-gray-500 mt-1"><?php echo $news['time_ago']; ?></div>
                                </div>
                            </div>
                            <?php if ($news['priority'] === 'high'): ?>
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i data-lucide="alert-circle" class="w-3 h-3 mr-1"></i>
                                    Breaking
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($news['title']); ?>
                        </h3>
                        <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($news['content']); ?></p>

                        <?php if (isset($news['player_data'])): ?>
                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                            <span class="text-white font-bold"><?php echo $news['player_data']['rating']; ?></span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold"><?php echo htmlspecialchars($news['player_data']['name']); ?>
                                            </h4>
                                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                                <span
                                                    class="px-2 py-1 bg-gray-200 rounded"><?php echo $news['player_data']['position']; ?></span>
                                                <span>Age: <?php echo $news['player_data']['age']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-green-600">
                                            <?php echo formatMarketValue($news['player_data']['value']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">Market Value</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($news['actions']) && !empty($news['actions'])): ?>
                            <div class="flex gap-3 pt-4 border-t border-gray-200">
                                <?php foreach ($news['actions'] as $action): ?>
                                    <button onclick="handleNewsAction('<?php echo $action['type']; ?>', '<?php echo $news['id']; ?>')"
                                        class="px-4 py-2 rounded-lg font-medium transition-colors <?php echo $action['style']; ?>">
                                        <i data-lucide="<?php echo $action['icon']; ?>" class="w-4 h-4 inline mr-1"></i>
                                        <?php echo $action['label']; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    lucide.createIcons();

    function handleNewsAction(actionType, newsId) {
        switch (actionType) {
            case 'negotiate':
                Swal.fire({
                    title: 'Start Negotiations?',
                    text: 'This will open transfer negotiations with the player.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, negotiate',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Handle negotiation logic
                        Swal.fire('Success!', 'Negotiations started successfully.', 'success');
                    }
                });
                break;

            case 'offer_contract':
                Swal.fire({
                    title: 'Offer Contract?',
                    text: 'Make a contract offer to this player.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Make Offer',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Handle contract offer logic
                        Swal.fire('Success!', 'Contract offer sent successfully.', 'success');
                    }
                });
                break;



            case 'dismiss':
            case 'not_interested':
                const actionText = actionType === 'dismiss' ? 'dismiss this news' : 'mark as not interested';
                const confirmText = actionType === 'dismiss' ? 'Yes, dismiss' : 'Not interested';

                Swal.fire({
                    title: 'Are you sure?',
                    text: `Do you want to ${actionText}? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Delete news item from database
                        fetch('api/delete_news_api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                news_id: newsId
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Remove the news item from the page
                                    const newsElement = document.querySelector(`[data-news-id="${newsId}"]`);
                                    if (newsElement) {
                                        newsElement.remove();
                                    } else {
                                        // Fallback: reload the page if element not found
                                        location.reload();
                                    }

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Removed!',
                                        text: 'News item has been removed.',
                                        timer: 2000,
                                        showConfirmButton: false,
                                        toast: true,
                                        position: 'top-end'
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message || 'Failed to remove news item.',
                                        confirmButtonColor: '#ef4444'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Connection Error',
                                    text: 'Failed to connect to server. Please try again.',
                                    confirmButtonColor: '#ef4444'
                                });
                            });
                    }
                });
                break;
        }
    }
</script>

<?php
endContent('Football News', 'news');
?>