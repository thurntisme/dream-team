<?php
session_start();

require_once 'config/config.php';
require_once 'includes/debug_logger.php';
require_once 'partials/layout.php';

// Check if user is admin or has permission to view logs
// For now, just check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$logger = DebugLogger::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_logs':
                $logger->clearLogs();
                $_SESSION['log_message'] = 'All logs cleared successfully';
                header('Location: debug_logs.php');
                exit;
        }
    }
}

// Get log files
$logFiles = $logger->getLogFiles();
$stats = $logger->getStats();

// Get selected log file
$selectedFile = $_GET['file'] ?? ($logFiles[0] ?? null);

// Read log content
$logContent = '';
$logLines = [];
if ($selectedFile && file_exists($selectedFile)) {
    $logContent = file_get_contents($selectedFile);
    $logLines = array_reverse(file($selectedFile, FILE_IGNORE_NEW_LINES));
}

// Filter logs
$filterLevel = $_GET['level'] ?? 'all';
$filterSearch = $_GET['search'] ?? '';

if ($filterLevel !== 'all' || !empty($filterSearch)) {
    $logLines = array_filter($logLines, function($line) use ($filterLevel, $filterSearch) {
        $matchLevel = $filterLevel === 'all' || stripos($line, '[' . strtoupper($filterLevel) . ']') !== false;
        $matchSearch = empty($filterSearch) || stripos($line, $filterSearch) !== false;
        return $matchLevel && $matchSearch;
    });
}

// Pagination
$perPage = 100;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$totalLines = count($logLines);
$totalPages = ceil($totalLines / $perPage);
$offset = ($page - 1) * $perPage;
$logLines = array_slice($logLines, $offset, $perPage);

startContent();
?>

<div class="container mx-auto pt-6 pb-10">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="file-text" class="w-6 h-6"></i>
                    Debug Logs
                </h1>
                <p class="text-gray-600 mt-1">View and manage application debug logs</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $stats['enabled'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $stats['enabled'] ? 'Enabled' : 'Disabled'; ?>
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <div class="text-2xl font-bold text-blue-600"><?php echo $stats['files']; ?></div>
                <div class="text-sm text-blue-700">Log Files</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <div class="text-2xl font-bold text-green-600"><?php echo $stats['total_size']; ?></div>
                <div class="text-sm text-green-700">Total Size</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                <div class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['total_lines']); ?></div>
                <div class="text-sm text-purple-700">Total Lines</div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['log_message'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
            <?php echo htmlspecialchars($_SESSION['log_message']); ?>
            <?php unset($_SESSION['log_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$stats['enabled']): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                <div>
                    <strong>Debug logging is disabled.</strong> Set <code class="bg-yellow-100 px-2 py-1 rounded">DEBUG_LOG=true</code> in your <code>.env</code> file to enable logging.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Controls -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- File Selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Log File</label>
                <select id="fileSelector" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($logFiles as $file): ?>
                        <option value="<?php echo htmlspecialchars(basename($file)); ?>" <?php echo $file === $selectedFile ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(basename($file)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Level Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                <select id="levelFilter" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo $filterLevel === 'all' ? 'selected' : ''; ?>>All Levels</option>
                    <option value="debug" <?php echo $filterLevel === 'debug' ? 'selected' : ''; ?>>Debug</option>
                    <option value="info" <?php echo $filterLevel === 'info' ? 'selected' : ''; ?>>Info</option>
                    <option value="warning" <?php echo $filterLevel === 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="error" <?php echo $filterLevel === 'error' ? 'selected' : ''; ?>>Error</option>
                    <option value="sql" <?php echo $filterLevel === 'sql' ? 'selected' : ''; ?>>SQL</option>
                    <option value="performance" <?php echo $filterLevel === 'performance' ? 'selected' : ''; ?>>Performance</option>
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" id="searchInput" value="<?php echo htmlspecialchars($filterSearch); ?>" 
                    placeholder="Search logs..." 
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Actions -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Actions</label>
                <div class="flex gap-2">
                    <button onclick="refreshLogs()" class="flex-1 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Refresh
                    </button>
                    <button onclick="clearLogs()" class="flex-1 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center justify-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Content -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">
                Log Entries (<?php echo number_format($totalLines); ?> total)
            </h2>
            <?php if ($totalPages > 1): ?>
                <div class="text-sm text-gray-600">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($logLines)): ?>
            <div class="text-center py-8 text-gray-500">
                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 text-gray-400"></i>
                <p>No log entries found</p>
            </div>
        <?php else: ?>
            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                <pre class="text-xs text-green-400 font-mono"><?php
                    foreach ($logLines as $line) {
                        // Color code by level
                        $colorClass = 'text-green-400';
                        if (stripos($line, '[ERROR]') !== false) {
                            $colorClass = 'text-red-400';
                        } elseif (stripos($line, '[WARNING]') !== false) {
                            $colorClass = 'text-yellow-400';
                        } elseif (stripos($line, '[INFO]') !== false) {
                            $colorClass = 'text-blue-400';
                        } elseif (stripos($line, '[SQL]') !== false) {
                            $colorClass = 'text-purple-400';
                        } elseif (stripos($line, '[PERFORMANCE]') !== false) {
                            $colorClass = 'text-cyan-400';
                        }
                        
                        echo '<span class="' . $colorClass . '">' . htmlspecialchars($line) . '</span>' . "\n";
                    }
                ?></pre>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-center gap-2 mt-4">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&level=<?php echo $filterLevel; ?>&search=<?php echo urlencode($filterSearch); ?>&file=<?php echo urlencode(basename($selectedFile)); ?>" 
                            class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="px-4 py-2 text-gray-700">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&level=<?php echo $filterLevel; ?>&search=<?php echo urlencode($filterSearch); ?>&file=<?php echo urlencode(basename($selectedFile)); ?>" 
                            class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // File selector change
    $('#fileSelector').change(function() {
        const file = $(this).val();
        window.location.href = `?file=${encodeURIComponent(file)}&level=<?php echo $filterLevel; ?>&search=<?php echo urlencode($filterSearch); ?>`;
    });

    // Level filter change
    $('#levelFilter').change(function() {
        const level = $(this).val();
        window.location.href = `?level=${level}&search=<?php echo urlencode($filterSearch); ?>&file=<?php echo urlencode(basename($selectedFile)); ?>`;
    });

    // Search input
    let searchTimeout;
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        const search = $(this).val();
        searchTimeout = setTimeout(() => {
            window.location.href = `?search=${encodeURIComponent(search)}&level=<?php echo $filterLevel; ?>&file=<?php echo urlencode(basename($selectedFile)); ?>`;
        }, 500);
    });

    // Refresh logs
    function refreshLogs() {
        window.location.reload();
    }

    // Clear logs
    function clearLogs() {
        Swal.fire({
            title: 'Clear All Logs?',
            text: 'This will permanently delete all debug log files. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Clear Logs',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="clear_logs">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>

<?php
endContent('Debug Logs', 'debug_logs', true, false, false, true);
?>
