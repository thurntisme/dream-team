<?php
// Dream Team Installation Script
require_once 'config.php';
require_once 'constants.php';

$errors = [];
$success = [];

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    $errors[] = 'PHP 7.4 or higher is required. Current version: ' . PHP_VERSION;
}

// Check SQLite extension
if (!extension_loaded('sqlite3')) {
    $errors[] = 'SQLite3 extension is not loaded';
}

// Get current configuration
$config = loadConfig();
$db_file = $config['db_file'] ?? 'dreamteam.db';
$app_name = $config['app_name'] ?? 'Dream Team';

// Check database status
$db_exists = file_exists($db_file);
$table_exists = false;
$has_users = false;
$is_ready = false;

if ($db_exists) {
    try {
        $db = new SQLite3($db_file);

        // Check if users table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $table_exists = $result->fetchArray() !== false;

        if ($table_exists) {
            // Check if there are any users
            $result = $db->query("SELECT COUNT(*) as count FROM users");
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $has_users = $row['count'] > 0;
        }

        $db->close();
        $is_ready = $db_exists && $table_exists && $has_users;
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Handle configuration save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $new_config = [
        'db_file' => $_POST['db_file'] ?? 'dreamteam.db',
        'app_name' => $_POST['app_name'] ?? 'Dream Team'
    ];

    if (saveConfig($new_config)) {
        $success[] = 'Configuration saved successfully';
        $config = $new_config;
        $db_file = $config['db_file'];
        $app_name = $config['app_name'];

        // Recheck database status with new config
        $db_exists = file_exists($db_file);
        $table_exists = false;
        $has_users = false;
        $is_ready = false;

        if ($db_exists) {
            try {
                $db = new SQLite3($db_file);
                $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
                $table_exists = $result->fetchArray() !== false;

                if ($table_exists) {
                    $result = $db->query("SELECT COUNT(*) as count FROM users");
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $has_users = $row['count'] > 0;
                }

                $db->close();
                $is_ready = $db_exists && $table_exists && $has_users;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'Failed to save configuration';
    }
}

// Handle installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // Create/connect to database
        $db = new SQLite3($db_file);

        // Create users table
        $sql = 'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            club_name TEXT,
            formation TEXT DEFAULT "4-4-2",
            team TEXT DEFAULT "[]",
            substitutes TEXT DEFAULT "[]",
            budget INTEGER DEFAULT ' . DEFAULT_BUDGET . ',
            max_players INTEGER DEFAULT 23,
            fans INTEGER DEFAULT 5000,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )';

        if ($db->exec($sql)) {
            $success[] = 'Database and users table created successfully';

            // Create additional tables

            // Transfer system tables
            $db->exec('CREATE TABLE IF NOT EXISTS transfer_bids (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bidder_id INTEGER NOT NULL,
                owner_id INTEGER NOT NULL,
                player_index INTEGER NOT NULL,
                player_uuid TEXT NOT NULL,
                bid_amount INTEGER NOT NULL,
                status TEXT DEFAULT "pending",
                bid_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                response_time DATETIME,
                FOREIGN KEY (bidder_id) REFERENCES users(id),
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )');

            $db->exec('CREATE TABLE IF NOT EXISTS player_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                player_uuid TEXT NOT NULL,
                player_data TEXT NOT NULL,
                purchase_price INTEGER NOT NULL,
                purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TEXT DEFAULT "available",
                FOREIGN KEY (user_id) REFERENCES users(id)
            )');

            // Migration: Handle column changes and add missing columns
            try {
                // Check existing columns in player_inventory
                $result = $db->query("PRAGMA table_info(player_inventory)");
                $columns = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $columns[] = $row['name'];
                }

                // Add player_uuid column if it doesn't exist
                if (!in_array('player_uuid', $columns)) {
                    $db->exec('ALTER TABLE player_inventory ADD COLUMN player_uuid TEXT DEFAULT ""');
                }

                // Add purchase_price column if it doesn't exist
                if (!in_array('purchase_price', $columns)) {
                    $db->exec('ALTER TABLE player_inventory ADD COLUMN purchase_price INTEGER DEFAULT 0');
                }

                // Migrate data from player_name to player_uuid if needed
                if (in_array('player_name', $columns) && in_array('player_uuid', $columns)) {
                    $stmt = $db->prepare('SELECT id, player_name, player_data FROM player_inventory WHERE player_uuid = "" AND player_name != ""');
                    $result = $stmt->execute();

                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $player_data = json_decode($row['player_data'], true);
                        if ($player_data && isset($player_data['uuid'])) {
                            $update_stmt = $db->prepare('UPDATE player_inventory SET player_uuid = :uuid WHERE id = :id');
                            $update_stmt->bindValue(':uuid', $player_data['uuid'], SQLITE3_TEXT);
                            $update_stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                            $update_stmt->execute();
                        }
                    }
                }

                // Check transfer_bids table
                $result = $db->query("PRAGMA table_info(transfer_bids)");
                $bid_columns = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $bid_columns[] = $row['name'];
                }

                // Add player_uuid column to transfer_bids if it doesn't exist
                if (!in_array('player_uuid', $bid_columns)) {
                    $db->exec('ALTER TABLE transfer_bids ADD COLUMN player_uuid TEXT DEFAULT ""');
                }

                // Migrate transfer_bids data from player_name to player_uuid if needed
                if (in_array('player_name', $bid_columns) && in_array('player_uuid', $bid_columns)) {
                    $stmt = $db->prepare('SELECT id, player_name, player_data FROM transfer_bids WHERE player_uuid = "" AND player_name != ""');
                    $result = $stmt->execute();

                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $player_data = json_decode($row['player_data'], true);
                        if ($player_data && isset($player_data['uuid'])) {
                            $update_stmt = $db->prepare('UPDATE transfer_bids SET player_uuid = :uuid WHERE id = :id');
                            $update_stmt->bindValue(':uuid', $player_data['uuid'], SQLITE3_TEXT);
                            $update_stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                            $update_stmt->execute();
                        }
                    }
                }
            } catch (Exception $e) {
                // Migration failed, but continue - table might be new
            }

            // Shop system tables
            $db->exec('CREATE TABLE IF NOT EXISTS user_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                item_id TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )');

            // League tables
            require_once 'league_functions.php';
            createLeagueTables($db);

            $success[] = 'All database tables created successfully';

            // Create admin user if requested
            if (!empty($_POST['admin_name']) && !empty($_POST['admin_email']) && !empty($_POST['admin_password'])) {
                $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
                $stmt->bindValue(':name', $_POST['admin_name'], SQLITE3_TEXT);
                $stmt->bindValue(':email', $_POST['admin_email'], SQLITE3_TEXT);
                $stmt->bindValue(':password', password_hash($_POST['admin_password'], PASSWORD_DEFAULT), SQLITE3_TEXT);

                if ($stmt->execute()) {
                    $success[] = 'Admin user created successfully';
                } else {
                    $errors[] = 'Failed to create admin user: ' . $db->lastErrorMsg();
                }
            }

            // Set proper permissions
            chmod($db_file, 0666);

            // Update status
            $db_exists = true;
            $table_exists = true;
            $has_users = !empty($_POST['admin_name']);
            $is_ready = $db_exists && $table_exists && $has_users;

        } else {
            $errors[] = 'Failed to create database table: ' . $db->lastErrorMsg();
        }

        $db->close();

    } catch (Exception $e) {
        $errors[] = 'Installation failed: ' . $e->getMessage();
    }
}

// Handle database reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    if (file_exists($db_file)) {
        if (unlink($db_file)) {
            $success[] = 'Database reset successfully';
            $db_exists = false;
            $table_exists = false;
            $has_users = false;
            $is_ready = false;
        } else {
            $errors[] = 'Failed to delete database file';
        }
    }
}

require_once 'layout.php';

// Start content capture
startContent();
?>
<div class="flex items-center justify-center min-h-[calc(100vh-200px)] p-4">
    <div class="w-full max-w-2xl bg-white rounded-lg shadow p-8">
        <div class="flex items-center justify-center mb-8">
            <i data-lucide="trophy" class="w-16 h-16 text-blue-600"></i>
        </div>

        <h1 class="text-3xl font-bold text-center mb-8"><?php echo htmlspecialchars($app_name); ?> Installation</h1>

        <!-- System Requirements -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4">System Requirements</h2>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <?php if (version_compare(PHP_VERSION, '7.4.0') >= 0): ?>
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <span class="text-green-600">PHP <?php echo PHP_VERSION; ?> ✓</span>
                    <?php else: ?>
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                        <span class="text-red-600">PHP <?php echo PHP_VERSION; ?> (7.4+ required)</span>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <?php if (extension_loaded('sqlite3')): ?>
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <span class="text-green-600">SQLite3 Extension ✓</span>
                    <?php else: ?>
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                        <span class="text-red-600">SQLite3 Extension (not loaded)</span>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <?php if (is_writable('.')): ?>
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <span class="text-green-600">Directory Writable ✓</span>
                    <?php else: ?>
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                        <span class="text-red-600">Directory Not Writable</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                    <span class="font-semibold text-red-800">Errors:</span>
                </div>
                <ul class="list-disc list-inside text-red-700 space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                    <span class="font-semibold text-green-800">Success:</span>
                </div>
                <ul class="list-disc list-inside text-green-700 space-y-1">
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Configuration Form -->
        <?php if (empty($errors) && !$is_ready): ?>
            <form method="POST" class="mb-8">
                <div class="border-t pt-6">
                    <h2 class="text-xl font-semibold mb-4">Configuration</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium mb-1">Application Name</label>
                            <input type="text" name="app_name" value="<?php echo htmlspecialchars($app_name); ?>"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Dream Team">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Database File</label>
                            <input type="text" name="db_file" value="<?php echo htmlspecialchars($db_file); ?>"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="dreamteam.db">
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <!-- Database Status -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4">Database Status</h2>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <?php if ($db_exists): ?>
                        <i data-lucide="database" class="w-5 h-5 text-blue-600"></i>
                        <span class="text-blue-600">Database "<?php echo htmlspecialchars($db_file); ?>" exists ✓</span>
                    <?php else: ?>
                        <i data-lucide="database" class="w-5 h-5 text-gray-400"></i>
                        <span class="text-gray-600">Database "<?php echo htmlspecialchars($db_file); ?>" not found</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($table_exists): ?>
                        <i data-lucide="table" class="w-5 h-5 text-blue-600"></i>
                        <span class="text-blue-600">Users table exists ✓</span>
                    <?php else: ?>
                        <i data-lucide="table" class="w-5 h-5 text-gray-400"></i>
                        <span class="text-gray-600">Users table not found</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($has_users): ?>
                        <i data-lucide="users" class="w-5 h-5 text-blue-600"></i>
                        <span class="text-blue-600">User accounts exist ✓</span>
                    <?php else: ?>
                        <i data-lucide="users" class="w-5 h-5 text-gray-400"></i>
                        <span class="text-gray-600">No user accounts found</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ready to Use or Installation Form -->
        <?php if (empty($errors)): ?>
            <?php if ($is_ready): ?>
                <!-- System is ready -->
                <div class="border-t pt-6">
                    <div class="text-center">
                        <div class="mb-6">
                            <i data-lucide="check-circle" class="w-16 h-16 text-green-600 mx-auto mb-4"></i>
                            <h2 class="text-2xl font-bold text-green-800 mb-2">System Ready!</h2>
                            <p class="text-gray-600"><?php echo htmlspecialchars($app_name); ?> is installed and ready to use.
                            </p>
                        </div>

                        <div class="flex justify-center gap-3 flex-wrap">
                            <a href="index.php"
                                class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-semibold">
                                <i data-lucide="play" class="w-5 h-5"></i>
                                Go to <?php echo htmlspecialchars($app_name); ?>
                            </a>

                            <button type="button" id="seedClubsBtn"
                                class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
                                <i data-lucide="users" class="w-5 h-5"></i>
                                Seed Demo Clubs
                            </button>

                            <form method="POST" class="inline">
                                <button type="button" id="resetSystemBtn"
                                    class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700">
                                    Reset System
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Installation form -->
                <form method="POST" class="space-y-6">
                    <div class="border-t pt-6">
                        <h2 class="text-xl font-semibold mb-4">
                            <?php echo ($db_exists && $table_exists) ? 'Complete Setup' : 'Install Database'; ?>
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium mb-1">Admin Name
                                    <?php echo !$has_users ? '(Required)' : '(Optional)'; ?>
                                </label>
                                <input type="text" name="admin_name" <?php echo !$has_users ? 'required' : ''; ?> class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2
                            focus:ring-blue-500" placeholder="Admin User">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Admin Email
                                    <?php echo !$has_users ? '(Required)' : '(Optional)'; ?>
                                </label>
                                <input type="email" name="admin_email" <?php echo !$has_users ? 'required' : ''; ?> class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2
                            focus:ring-blue-500" placeholder="admin@example.com">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">Admin Password
                                    <?php echo !$has_users ? '(Required)' : '(Optional)'; ?>
                                </label>
                                <input type="password" name="admin_password" <?php echo !$has_users ? 'required' : ''; ?> class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2
                            focus:ring-blue-500" placeholder="Password">
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" name="install"
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <?php echo ($db_exists && $table_exists) ? 'Complete Setup' : 'Install'; ?>
                            </button> <?php if ($db_exists && $table_exists): ?>
                                <button type="button" id="resetDatabaseBtn"
                                    class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">
                                    Reset Database
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Instructions -->
        <div class="border-t pt-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">Instructions</h2>
            <div class="text-sm text-gray-600 space-y-2">
                <p>1. Ensure your web server has PHP 7.4+ with SQLite3 extension</p>
                <p>2. Configure application name and database file above</p>
                <p>3. Run this installer to set up the database and tables</p>
                <p>4. Create an admin user during installation (required for first setup)</p>
                <p>5. Delete this install.php file after installation for security</p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // SweetAlert for reset system button
        document.getElementById('resetSystemBtn')?.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Reset System?',
                text: 'This will delete all data and users! This action cannot be undone.',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Reset System',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="reset" value="1">';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // SweetAlert for reset database button
        document.getElementById('resetDatabaseBtn')?.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Reset Database?',
                text: 'This will delete all data! This action cannot be undone.',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Reset Database',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="reset" value="1">';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // SweetAlert for seed clubs button
        document.getElementById('seedClubsBtn')?.addEventListener('click', function () {
            Swal.fire({
                icon: 'question',
                title: 'Seed Demo Clubs?',
                html: `
                    <p>This will create <?php echo count(DEMO_CLUBS); ?> demo clubs with realistic teams:</p>
                    <ul class="text-left mt-3 space-y-1">
                        <?php foreach (DEMO_CLUBS as $club): ?>
                        <li>• <?php echo htmlspecialchars($club['name']); ?> (<?php echo htmlspecialchars($club['formation']); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mt-3 text-sm text-gray-600">Each club will have a complete team with €1B+ budget and login credentials.</p>
                `,
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Create Demo Clubs',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('seed.php?seed=clubs')
                        .then(response => response.text())
                        .then(data => {
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Demo Clubs Created!',
                        html: `
                            <div class="text-left">
                                <p class="mb-3"><?php echo count(DEMO_CLUBS); ?> demo clubs have been created successfully!</p>
                                <div class="bg-gray-50 p-3 rounded text-sm">
                                    <strong>Login Credentials:</strong><br>
                                    <?php foreach (DEMO_CREDENTIALS as $email => $password): ?>
                                    • <?php echo htmlspecialchars($email); ?> / <?php echo htmlspecialchars($password); ?><br>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        `,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Go to Login'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                }
            });
        });
    </script>
</div>
</div>

<?php
// End content capture and render layout
endContent($app_name . ' - Installation', '', false);
?>