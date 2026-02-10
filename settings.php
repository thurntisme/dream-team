<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';





$db = getDbConnection();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        try {
            // Get form data
            $notifications = isset($_POST['notifications']) ? '1' : '0';
            $emailUpdates = isset($_POST['email_updates']) ? '1' : '0';
            $theme = $_POST['theme'] ?? 'light';
            $language = $_POST['language'] ?? 'en';
            $timezone = $_POST['timezone'] ?? 'UTC';
            $autoSave = isset($_POST['auto_save']) ? '1' : '0';

            // Settings to update
            $settings = [
                'notifications' => $notifications,
                'email_updates' => $emailUpdates,
                'theme' => $theme,
                'language' => $language,
                'timezone' => $timezone,
                'auto_save' => $autoSave
            ];

            foreach ($settings as $key => $value) {
                if (DB_DRIVER === 'mysql') {
                    $stmt = $db->prepare('INSERT INTO user_settings (user_id, setting_key, setting_value, updated_at) VALUES (:user_id, :key, :value, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
                } else {
                    $stmt = $db->prepare('INSERT OR REPLACE INTO user_settings (user_id, setting_key, setting_value, updated_at) VALUES (:user_id, :key, :value, datetime("now"))');
                }
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->bindValue(':key', $key, SQLITE3_TEXT);
                $stmt->bindValue(':value', $value, SQLITE3_TEXT);
                $stmt->execute();
            }

            $message = 'Settings updated successfully!';
            $messageType = 'success';

        } catch (Exception $e) {
            $message = 'Error updating settings: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current settings
function getUserSetting($db, $userId, $key, $default = '')
{
    $stmt = $db->prepare('SELECT setting_value FROM user_settings WHERE user_id = :user_id AND setting_key = :key');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['setting_value'] : $default;
}

$currentSettings = [
    'notifications' => getUserSetting($db, $userId, 'notifications', '1'),
    'email_updates' => getUserSetting($db, $userId, 'email_updates', '1'),
    'theme' => getUserSetting($db, $userId, 'theme', 'light'),
    'language' => getUserSetting($db, $userId, 'language', 'en'),
    'timezone' => getUserSetting($db, $userId, 'timezone', 'UTC'),
    'auto_save' => getUserSetting($db, $userId, 'auto_save', '1')
];

$db->close();

startContent();
?>

<div class="container mx-auto px-4 max-w-4xl py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <i data-lucide="settings" class="w-8 h-8 text-blue-600"></i>
            <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
        </div>
        <p class="text-gray-600">Manage your account preferences and game settings</p>
    </div>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Settings Form -->
    <form method="POST" class="space-y-8">
        <input type="hidden" name="action" value="update_settings">

        <!-- General Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-6">
                <i data-lucide="user" class="w-6 h-6 text-gray-700"></i>
                <h2 class="text-xl font-semibold text-gray-900">General Settings</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Theme -->
                <div>
                    <label for="theme" class="block text-sm font-medium text-gray-700 mb-2">Theme</label>
                    <select id="theme" name="theme"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="light" <?php echo $currentSettings['theme'] === 'light' ? 'selected' : ''; ?>>Light
                        </option>
                        <option value="dark" <?php echo $currentSettings['theme'] === 'dark' ? 'selected' : ''; ?>>Dark
                        </option>
                        <option value="auto" <?php echo $currentSettings['theme'] === 'auto' ? 'selected' : ''; ?>>Auto
                        </option>
                    </select>
                </div>

                <!-- Language -->
                <div>
                    <label for="language" class="block text-sm font-medium text-gray-700 mb-2">Language</label>
                    <select id="language" name="language"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="en" <?php echo $currentSettings['language'] === 'en' ? 'selected' : ''; ?>>English
                        </option>
                        <option value="es" <?php echo $currentSettings['language'] === 'es' ? 'selected' : ''; ?>>Español
                        </option>
                        <option value="fr" <?php echo $currentSettings['language'] === 'fr' ? 'selected' : ''; ?>>Français
                        </option>
                        <option value="de" <?php echo $currentSettings['language'] === 'de' ? 'selected' : ''; ?>>Deutsch
                        </option>
                    </select>
                </div>

                <!-- Timezone -->
                <div class="md:col-span-2">
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                    <select id="timezone" name="timezone"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="UTC" <?php echo $currentSettings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC
                        </option>
                        <option value="America/New_York" <?php echo $currentSettings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (ET)</option>
                        <option value="America/Chicago" <?php echo $currentSettings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time (CT)</option>
                        <option value="America/Denver" <?php echo $currentSettings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time (MT)</option>
                        <option value="America/Los_Angeles" <?php echo $currentSettings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (PT)
                        </option>
                        <option value="Europe/London" <?php echo $currentSettings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>London (GMT)</option>
                        <option value="Europe/Paris" <?php echo $currentSettings['timezone'] === 'Europe/Paris' ? 'selected' : ''; ?>>Paris (CET)</option>
                        <option value="Asia/Tokyo" <?php echo $currentSettings['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo (JST)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-6">
                <i data-lucide="bell" class="w-6 h-6 text-gray-700"></i>
                <h2 class="text-xl font-semibold text-gray-900">Notifications</h2>
            </div>

            <div class="space-y-4">
                <!-- In-Game Notifications -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">In-Game Notifications</h3>
                        <p class="text-sm text-gray-500">Receive notifications for match results, transfers, and other
                            game events</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="notifications" class="sr-only peer" <?php echo $currentSettings['notifications'] === '1' ? 'checked' : ''; ?>>
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                        </div>
                    </label>
                </div>

                <!-- Email Updates -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Email Updates</h3>
                        <p class="text-sm text-gray-500">Receive weekly summaries and important updates via email</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="email_updates" class="sr-only peer" <?php echo $currentSettings['email_updates'] === '1' ? 'checked' : ''; ?>>
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Game Settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center gap-3 mb-6">
                <i data-lucide="gamepad-2" class="w-6 h-6 text-gray-700"></i>
                <h2 class="text-xl font-semibold text-gray-900">Game Settings</h2>
            </div>

            <div class="space-y-4">
                <!-- Auto Save -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Auto Save</h3>
                        <p class="text-sm text-gray-500">Automatically save your team changes and formations</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="auto_save" class="sr-only peer" <?php echo $currentSettings['auto_save'] === '1' ? 'checked' : ''; ?>>
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit"
                class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors">
                <div class="flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save Settings</span>
                </div>
            </button>
        </div>
    </form>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Auto-save functionality for settings
    function autoSaveSettings() {
        const form = document.querySelector('form');
        const formData = new FormData(form);

        // Show saving indicator
        const saveBtn = document.querySelector('button[type="submit"]');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<div class="flex items-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Saving...</span></div>';
        saveBtn.disabled = true;

        // Re-initialize icons for the new loader icon
        lucide.createIcons();

        fetch('settings_api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success feedback
                    saveBtn.innerHTML = '<div class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500"></i><span>Saved!</span></div>';
                    lucide.createIcons();

                    // Reset button after 2 seconds
                    setTimeout(() => {
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                        lucide.createIcons();
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Failed to save settings');
                }
            })
            .catch(error => {
                console.error('Error saving settings:', error);
                saveBtn.innerHTML = '<div class="flex items-center gap-2"><i data-lucide="x" class="w-4 h-4 text-red-500"></i><span>Error</span></div>';
                lucide.createIcons();

                // Reset button after 3 seconds
                setTimeout(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    lucide.createIcons();
                }, 3000);
            });
    }

    // Add event listeners for real-time saving on toggle switches
    document.addEventListener('DOMContentLoaded', function () {
        const toggles = document.querySelectorAll('input[type="checkbox"]');
        const selects = document.querySelectorAll('select');

        // Auto-save on toggle change
        toggles.forEach(toggle => {
            toggle.addEventListener('change', function () {
                // Small delay to allow UI to update
                setTimeout(autoSaveSettings, 100);
            });
        });

        // Auto-save on select change
        selects.forEach(select => {
            select.addEventListener('change', function () {
                setTimeout(autoSaveSettings, 100);
            });
        });
    });

    // Theme preview functionality
    function previewTheme(theme) {
        const body = document.body;
        body.classList.remove('theme-light', 'theme-dark', 'theme-auto');

        if (theme === 'dark') {
            body.classList.add('theme-dark');
        } else if (theme === 'auto') {
            // Check system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            body.classList.add(prefersDark ? 'theme-dark' : 'theme-light');
        } else {
            body.classList.add('theme-light');
        }
    }

    // Add theme preview on change
    document.addEventListener('DOMContentLoaded', function () {
        const themeSelect = document.getElementById('theme');
        if (themeSelect) {
            themeSelect.addEventListener('change', function () {
                previewTheme(this.value);
            });
        }
    });
</script>

<?php
endContent('Settings', 'settings');
?>
