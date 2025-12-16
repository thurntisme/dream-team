<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

// Require user to be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Handle session clearing
$cleared_items = [];
$action_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_performed = true;
    
    if (isset($_POST['clear_all'])) {
        // Store user_id before clearing
        $user_id = $_SESSION['user_id'];
        
        // Clear all session data except user authentication
        session_unset();
        
        // Restore user authentication
        $_SESSION['user_id'] = $user_id;
        
        $cleared_items[] = 'All session data cleared (except login)';
        
    } elseif (isset($_POST['clear_specific'])) {
        $items_to_clear = $_POST['session_items'] ?? [];
        
        foreach ($items_to_clear as $item) {
            if (isset($_SESSION[$item]) && $item !== 'user_id') {
                unset($_SESSION[$item]);
                $cleared_items[] = "Cleared: $item";
            }
        }
        
        if (empty($cleared_items)) {
            $cleared_items[] = 'No valid items selected for clearing';
        }
    }
}

// Get current session data (excluding sensitive info)
$session_data = [];
foreach ($_SESSION as $key => $value) {
    // Skip sensitive data
    if (in_array($key, ['user_id'])) {
        continue;
    }
    
    // Truncate long values for display
    $display_value = $value;
    if (is_string($value) && strlen($value) > 100) {
        $display_value = substr($value, 0, 100) . '...';
    } elseif (is_array($value) || is_object($value)) {
        $display_value = json_encode($value, JSON_PRETTY_PRINT);
        if (strlen($display_value) > 200) {
            $display_value = substr($display_value, 0, 200) . '...';
        }
    }
    
    $session_data[$key] = [
        'value' => $display_value,
        'type' => gettype($value),
        'size' => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 'N/A')
    ];
}

startContent();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i data-lucide="settings" class="w-8 h-8 text-blue-600"></i>
            <div>
                <h1 class="text-2xl font-bold">Session Management</h1>
                <p class="text-gray-600">Clear session data for debugging and testing</p>
            </div>
        </div>
        <a href="settings.php"
            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to Settings
        </a>
    </div>

    <!-- Success Messages -->
    <?php if ($action_performed && !empty($cleared_items)): ?>
        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center gap-2 mb-2">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                <span class="text-green-800 font-semibold">Session Data Cleared</span>
            </div>
            <ul class="text-green-700 text-sm space-y-1">
                <?php foreach ($cleared_items as $item): ?>
                    <li>• <?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Warning Notice -->
    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600 mt-1"></i>
            <div>
                <h3 class="text-yellow-900 font-semibold mb-2">⚠️ Important Notice</h3>
                <div class="text-yellow-800 text-sm space-y-1">
                    <p>• Clearing session data may affect game functionality</p>
                    <p>• Some features may require re-initialization after clearing</p>
                    <p>• Training cooldowns, daily recovery status, and temporary data will be reset</p>
                    <p>• Your login session will be preserved</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="zap" class="w-5 h-5 text-blue-600"></i>
                Quick Actions
            </h3>

            <div class="space-y-4">
                <!-- Clear All -->
                <form method="POST" onsubmit="return confirm('Are you sure you want to clear all session data? This cannot be undone.')">
                    <button type="submit" name="clear_all" 
                        class="w-full bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 flex items-center justify-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Clear All Session Data
                    </button>
                </form>

                <!-- Common Clears -->
                <div class="grid grid-cols-1 gap-2">
                    <form method="POST" class="inline">
                        <input type="hidden" name="session_items[]" value="last_training_<?php echo $_SESSION['user_id']; ?>">
                        <button type="submit" name="clear_specific"
                            class="w-full bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700">
                            Clear Training Cooldown
                        </button>
                    </form>

                    <form method="POST" class="inline">
                        <input type="hidden" name="session_items[]" value="last_daily_recovery_<?php echo $_SESSION['user_id']; ?>">
                        <button type="submit" name="clear_specific"
                            class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700">
                            Clear Daily Recovery Status
                        </button>
                    </form>

                    <form method="POST" class="inline">
                        <input type="hidden" name="session_items[]" value="pending_reward">
                        <input type="hidden" name="session_items[]" value="active_challenge">
                        <button type="submit" name="clear_specific"
                            class="w-full bg-purple-600 text-white px-3 py-2 rounded text-sm hover:bg-purple-700">
                            Clear Match/Challenge Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Current Session Data -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="database" class="w-5 h-5 text-green-600"></i>
                Current Session Data
            </h3>

            <?php if (empty($session_data)): ?>
                <div class="text-center py-8">
                    <i data-lucide="inbox" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                    <p class="text-gray-600">No session data to display</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($session_data as $key => $data): ?>
                        <div class="border border-gray-200 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($key); ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                        <?php echo $data['type']; ?>
                                    </span>
                                    <?php if ($data['size'] !== 'N/A'): ?>
                                        <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded">
                                            <?php echo $data['size']; ?> <?php echo $data['type'] === 'string' ? 'chars' : 'items'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600 bg-gray-50 p-2 rounded font-mono text-xs overflow-x-auto">
                                <?php echo htmlspecialchars($data['value']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Custom Clear Form -->
    <?php if (!empty($session_data)): ?>
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="check-square" class="w-5 h-5 text-orange-600"></i>
                Selective Clear
            </h3>

            <form method="POST" onsubmit="return confirm('Are you sure you want to clear the selected session items?')">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    <?php foreach ($session_data as $key => $data): ?>
                        <label class="flex items-center gap-2 p-2 border border-gray-200 rounded hover:bg-gray-50">
                            <input type="checkbox" name="session_items[]" value="<?php echo htmlspecialchars($key); ?>" 
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($key); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" name="clear_specific"
                        class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 flex items-center gap-2">
                        <i data-lucide="trash" class="w-4 h-4"></i>
                        Clear Selected Items
                    </button>
                    <button type="button" onclick="toggleAllCheckboxes()"
                        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        Toggle All
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleAllCheckboxes() {
        const checkboxes = document.querySelectorAll('input[name="session_items[]"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
    }

    lucide.createIcons();
</script>

<?php
endContent('Session Management - Dream Team', 'settings');
?>