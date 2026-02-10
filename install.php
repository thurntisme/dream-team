<?php
// Dream Team Installation Script
require_once 'config/config.php';
require_once 'config/constants.php';

$__ui_errors = [];
$__ui_messages = [];
$__ui_seeded = false;

function __write_env($pairs)
{
    $env = __DIR__ . '/.env';
    $existing = [];
    if (file_exists($env)) {
        $lines = file($env, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $t = ltrim($line);
            if ($t === '' || $t[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $existing[$parts[0]] = $parts[1];
            }
        }
    }
    foreach ($pairs as $k => $v) {
        $existing[$k] = $v;
    }
    $buf = '';
    foreach ($existing as $k => $v) {
        $buf .= $k . '=' . $v . "\n";
    }
    file_put_contents($env, $buf);
}

function __create_tables_simple($db, $driver)
{
    $ok = $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            club_name VARCHAR(255) NULL,
            formation VARCHAR(20) DEFAULT "4-4-2",
            team TEXT,
            budget BIGINT DEFAULT ' . DEFAULT_BUDGET . ',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS shop_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            price BIGINT NOT NULL,
            effect_type VARCHAR(50) NOT NULL,
            effect_value TEXT NOT NULL,
            category VARCHAR(50) NOT NULL,
            icon VARCHAR(50) DEFAULT "package",
            duration INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    return $ok;
}

function __seed_shop_items_simple($db, $driver, &$messages)
{
    $count = 0;
    if ($driver === 'mysql') {
        $res = $db->query('SELECT COUNT(*) as count FROM shop_items');
        if ($res) {
            $row = $res->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['count'])) {
                $count = (int)$row['count'];
            }
        }
    } else {
        $res = $db->query('SELECT COUNT(*) as count FROM shop_items');
        if ($res) {
            $row = $res->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['count'])) {
                $count = (int)$row['count'];
            }
        }
    }
    if ($count > 0) {
        $messages[] = 'Shop items already exist, skipping seeding';
        return true;
    }
    $items = [
        ['Training Camp', 'Boost all players rating by +2 for 7 days', 5000000, 'player_boost', '{"rating": 2}', 'training', 'dumbbell', 7],
        ['Fitness Coach', 'Reduce injury risk by 50% for 14 days', 3000000, 'injury_protection', '{"reduction": 0.5}', 'training', 'heart-pulse', 14],
        ['Sponsorship Deal', 'Increase budget by €10M instantly', 8000000, 'budget_boost', '{"amount": 10000000}', 'financial', 'handshake', 0]
    ];
    if ($driver === 'mysql') {
        foreach ($items as $it) {
            $stmt = $db->prepare('INSERT INTO shop_items (name, description, price, effect_type, effect_value, category, icon, duration) VALUES (:n, :d, :p, :et, :ev, :c, :i, :du)');
            if ($stmt === false) {
                return false;
            }
            $stmt->bindValue(':n', $it[0], SQLITE3_TEXT);
            $stmt->bindValue(':d', $it[1], SQLITE3_TEXT);
            $stmt->bindValue(':p', $it[2], SQLITE3_INTEGER);
            $stmt->bindValue(':et', $it[3], SQLITE3_TEXT);
            $stmt->bindValue(':ev', $it[4], SQLITE3_TEXT);
            $stmt->bindValue(':c', $it[5], SQLITE3_TEXT);
            $stmt->bindValue(':i', $it[6], SQLITE3_TEXT);
            $stmt->bindValue(':du', $it[7], SQLITE3_INTEGER);
            $stmt->execute();
        }
    } else {
        $ins = $db->prepare('INSERT INTO shop_items (name, description, price, effect_type, effect_value, category, icon, duration) VALUES (:n, :d, :p, :et, :ev, :c, :i, :du)');
        if ($ins === false) {
            return false;
        }
        foreach ($items as $it) {
            $ins->bindValue(':n', $it[0], SQLITE3_TEXT);
            $ins->bindValue(':d', $it[1], SQLITE3_TEXT);
            $ins->bindValue(':p', $it[2], SQLITE3_INTEGER);
            $ins->bindValue(':et', $it[3], SQLITE3_TEXT);
            $ins->bindValue(':ev', $it[4], SQLITE3_TEXT);
            $ins->bindValue(':c', $it[5], SQLITE3_TEXT);
            $ins->bindValue(':i', $it[6], SQLITE3_TEXT);
            $ins->bindValue(':du', $it[7], SQLITE3_INTEGER);
            $ins->execute();
        }
    }
    $messages[] = 'Seeded basic shop items (' . count($items) . ')';
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_setup'])) {
    $driver = $_POST['driver'] ?? 'sqlite';
    $seed = !empty($_POST['seed']);
    try {
        if ($driver === 'mysql') {
            $host = trim($_POST['mysql_host'] ?? '127.0.0.1');
            $port = trim($_POST['mysql_port'] ?? '3306');
            $dbName = trim($_POST['mysql_db'] ?? '');
            $user = trim($_POST['mysql_user'] ?? '');
            $pass = trim($_POST['mysql_password'] ?? '');
            if ($dbName === '' || $user === '') {
                throw new Exception('MySQL database and user are required');
            }
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            __write_env([
                'DB_DRIVER' => 'mysql',
                'MYSQL_HOST' => $host,
                'MYSQL_PORT' => $port,
                'MYSQL_DB' => $dbName,
                'MYSQL_USER' => $user,
                'MYSQL_PASSWORD' => $pass
            ]);
            $adapterConfig = [
                'db_file' => '',
                'mysql_host' => $host,
                'mysql_port' => $port,
                'mysql_db' => $dbName,
                'mysql_user' => $user,
                'mysql_password' => $pass
            ];
            $db = new DBAdapter('mysql', $adapterConfig);
        } else {
            $file = trim($_POST['sqlite_file'] ?? (__DIR__ . '/database/dreamteam.db'));
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            __write_env([
                'DB_DRIVER' => 'sqlite',
                'DB_FILE' => $file
            ]);
            $adapterConfig = [
                'db_file' => $file,
                'mysql_host' => '',
                'mysql_port' => '',
                'mysql_db' => '',
                'mysql_user' => '',
                'mysql_password' => ''
            ];
            $db = new DBAdapter('sqlite', $adapterConfig);
        }
        if (__create_tables_simple($db, $driver)) {
            $__ui_messages[] = 'Create tables successfully';
        } else {
            $__ui_errors[] = 'Failed creating tables';
        }
        $adminName = trim($_POST['admin_name'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        if ($adminName !== '' && $adminEmail !== '' && $adminPassword !== '') {
            $stmt = $db->prepare('INSERT INTO users (name, email, password, club_name, formation, team, budget) VALUES (:name, :email, :password, :club, :form, :team, :budget)');
            if ($stmt === false) {
                $__ui_errors[] = 'Failed to prepare admin insert';
            } else {
                $stmt->bindValue(':name', $adminName, SQLITE3_TEXT);
                $stmt->bindValue(':email', $adminEmail, SQLITE3_TEXT);
                $stmt->bindValue(':password', password_hash($adminPassword, PASSWORD_DEFAULT), SQLITE3_TEXT);
                $stmt->bindValue(':club', $adminName . ' FC', SQLITE3_TEXT);
                $stmt->bindValue(':form', '4-4-2', SQLITE3_TEXT);
                $stmt->bindValue(':team', '[]', SQLITE3_TEXT);
                $stmt->bindValue(':budget', DEFAULT_BUDGET, SQLITE3_INTEGER);
                $exec = $stmt->execute();
                if ($exec) {
                    $__ui_messages[] = 'Admin user created successfully';
                } else {
                    $__ui_errors[] = 'Failed to create admin user';
                }
            }
        } else {
            $__ui_messages[] = 'Admin inputs empty, skipping admin creation';
        }
        if ($seed) {
            if (__seed_shop_items_simple($db, $driver, $__ui_messages)) {
                $__ui_seeded = true;
            } else {
                $__ui_errors[] = 'Seeding failed';
            }
        }
        $db->close();
    } catch (Throwable $e) {
        $__ui_errors[] = $e->getMessage();
    }
}

if (false) {
?>
    <!doctype html>
    <html>

    <head>
        <meta charset="utf-8">
        <title>Installer</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body {
                font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
                background: #f8fafc;
                margin: 0;
            }

            .container {
                max-width: 800px;
                margin: 40px auto;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 24px;
            }

            h1 {
                margin: 0 0 16px;
                font-size: 24px;
            }

            fieldset {
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                margin-bottom: 16px;
            }

            legend {
                padding: 0 8px;
                font-weight: 600;
            }

            label {
                display: block;
                font-size: 14px;
                margin: 8px 0 4px;
            }

            input[type=text],
            input[type=password],
            input[type=email] {
                width: 100%;
                padding: 10px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
            }

            .row {
                display: flex;
                gap: 12px;
            }

            .row>div {
                flex: 1;
            }

            .actions {
                display: flex;
                gap: 12px;
                align-items: center;
                margin-top: 16px;
            }

            .btn {
                background: #2563eb;
                color: #fff;
                border: none;
                padding: 10px 16px;
                border-radius: 8px;
                cursor: pointer;
            }

            .btn:disabled {
                opacity: .6;
                cursor: not-allowed;
            }

            .note {
                color: #64748b;
                font-size: 13px;
            }

            .list {
                margin: 12px 0;
                padding: 0;
                list-style: none;
            }

            .list li {
                margin: 4px 0;
                font-size: 14px;
            }

            .error {
                color: #b91c1c;
            }

            .ok {
                color: #166534;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <h1>Installer</h1>
            <?php if (!empty($__ui_errors)): ?>
                <ul class="list">
                    <?php foreach ($__ui_errors as $m): ?>
                        <li class="error">• <?php echo htmlspecialchars($m); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($__ui_messages)): ?>
                <ul class="list">
                    <?php foreach ($__ui_messages as $m): ?>
                        <li class="ok">• <?php echo htmlspecialchars($m); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="start_setup" value="1">
                <fieldset>
                    <legend>Database Configuration</legend>
                    <label>Driver</label>
                    <div class="row">
                        <div>
                            <label><input type="radio" name="driver" value="sqlite" <?php echo (($_POST['driver'] ?? 'sqlite') === 'sqlite') ? 'checked' : '' ?>> SQLite</label>
                        </div>
                        <div>
                            <label><input type="radio" name="driver" value="mysql" <?php echo (($_POST['driver'] ?? 'sqlite') === 'mysql') ? 'checked' : '' ?>> MySQL</label>
                        </div>
                    </div>
                    <div id="sqliteFields" style="margin-top:8px; <?php echo (($_POST['driver'] ?? 'sqlite') === 'mysql') ? 'display:none' : '' ?>">
                        <label>SQLite file path</label>
                        <input type="text" name="sqlite_file" value="<?php echo htmlspecialchars($_POST['sqlite_file'] ?? (__DIR__ . '/database/dreamteam.db')); ?>">
                        <div class="note">Example: d:/code/dream-team/database/dreamteam.db</div>
                    </div>
                    <div id="mysqlFields" style="margin-top:8px; <?php echo (($_POST['driver'] ?? 'sqlite') === 'mysql') ? '' : 'display:none' ?>">
                        <div class="row">
                            <div>
                                <label>Host</label>
                                <input type="text" name="mysql_host" value="<?php echo htmlspecialchars($_POST['mysql_host'] ?? '127.0.0.1'); ?>">
                            </div>
                            <div>
                                <label>Port</label>
                                <input type="text" name="mysql_port" value="<?php echo htmlspecialchars($_POST['mysql_port'] ?? '3306'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div>
                                <label>Database</label>
                                <input type="text" name="mysql_db" value="<?php echo htmlspecialchars($_POST['mysql_db'] ?? 'dreamteam'); ?>">
                            </div>
                            <div>
                                <label>User</label>
                                <input type="text" name="mysql_user" value="<?php echo htmlspecialchars($_POST['mysql_user'] ?? 'root'); ?>">
                            </div>
                        </div>
                        <label>Password</label>
                        <input type="password" name="mysql_password" value="<?php echo htmlspecialchars($_POST['mysql_password'] ?? ''); ?>">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Admin User</legend>
                    <div class="row">
                        <div>
                            <label>Name</label>
                            <input type="text" name="admin_name" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                        </div>
                    </div>
                    <label>Password</label>
                    <input type="password" name="admin_password" value="<?php echo htmlspecialchars($_POST['admin_password'] ?? ''); ?>">
                </fieldset>

                <div class="actions">
                    <label><input type="checkbox" name="seed" <?php echo !empty($_POST['seed']) ? 'checked' : '' ?>> Seed data</label>
                    <button type="submit" class="btn">Start</button>
                </div>
            </form>
        </div>
        <script>
            const sqliteRadio = document.querySelector('input[name="driver"][value="sqlite"]');
            const mysqlRadio = document.querySelector('input[name="driver"][value="mysql"]');
            const sqliteFields = document.getElementById('sqliteFields');
            const mysqlFields = document.getElementById('mysqlFields');

            function sync() {
                if (sqliteRadio.checked) {
                    sqliteFields.style.display = '';
                    mysqlFields.style.display = 'none';
                } else {
                    sqliteFields.style.display = 'none';
                    mysqlFields.style.display = '';
                }
            }
            sqliteRadio.addEventListener('change', sync);
            mysqlRadio.addEventListener('change', sync);
            sync();
        </script>
    </body>

    </html>
<?php
    exit;
}

$errors = [];
$success = [];

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    $errors[] = 'PHP 7.4 or higher is required. Current version: ' . PHP_VERSION;
}

// MySQL-only installation

// Get current configuration
$config = loadConfig();
$db_file = $config['db_file'] ?? 'database/dreamteam.db';
$app_name = $config['app_name'] ?? 'Dream Team';

// Check database status
$db_exists = true;
$table_exists = false;
$has_users = false;
$is_ready = false;

if ($db_exists) {
    try {
        $db = getDbConnection();
        $check = $db->query("SELECT 1 FROM users LIMIT 1");
        $table_exists = $check !== false;
        if ($table_exists) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            if ($stmt) {
                $row = $stmt->fetchArray(SQLITE3_ASSOC);
                $has_users = $row && isset($row['count']) ? ((int)$row['count'] > 0) : false;
            }
        }
        $db->close();
        $is_ready = $table_exists && $has_users;
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Configuration via environment variables only; no web-based DB config
$step = isset($_POST['step']) ? (int)$_POST['step'] : (isset($_GET['step']) ? (int)$_GET['step'] : 1);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_conn'])) {
    try {
        $db = getDbConnection();
        $r = $db->query('SELECT 1');
        $ok = $r !== false;
        $db->close();
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        }
        if ($ok) $success[] = 'Database connection successful';
        else $errors[] = 'Database connection failed';
    } catch (Exception $e) {
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        } else {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
}

// Handle installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        if (DB_DRIVER === 'mysql') {
            $db = getDbConnection();
            $ensureIdx = function ($table, $index, $columns) use ($db) {
                $stmt = $db->prepare('SELECT COUNT(*) as c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i');
                if ($stmt) {
                    $stmt->bindValue(':t', $table, SQLITE3_TEXT);
                    $stmt->bindValue(':i', $index, SQLITE3_TEXT);
                    $res = $stmt->execute();
                    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                    if ((int)($row['c'] ?? 0) === 0) {
                        $db->exec('CREATE INDEX ' . $index . ' ON ' . $table . ' (' . $columns . ')');
                    }
                }
            };
            $ok = true;
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                club_name VARCHAR(255) NULL,
                formation VARCHAR(20) DEFAULT "4-4-2",
                team TEXT,
                substitutes TEXT,
                budget BIGINT DEFAULT ' . DEFAULT_BUDGET . ',
                max_players INT DEFAULT 23,
                fans INT DEFAULT 5000,
                club_exp INT DEFAULT 0,
                club_level INT DEFAULT 1,
                matches_played INT DEFAULT 0,
                user_plan VARCHAR(50) DEFAULT "free",
                plan_expires_at DATETIME NULL,
                last_login DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_setting (user_uuid, setting_key),
                FOREIGN KEY (user_uuid) REFERENCES users(uuid) ON DELETE CASCADE
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS stadiums (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                name VARCHAR(255) DEFAULT "Home Stadium",
                capacity INT DEFAULT 10000,
                level INT DEFAULT 1,
                facilities TEXT,
                last_upgrade DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS transfer_bids (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bidder_id INT NOT NULL,
                owner_id INT NOT NULL,
                player_index INT NOT NULL,
                player_uuid VARCHAR(64) NOT NULL,
                bid_amount BIGINT NOT NULL,
                status VARCHAR(20) DEFAULT "pending",
                bid_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                response_time DATETIME NULL,
                FOREIGN KEY (bidder_id) REFERENCES users(id),
                FOREIGN KEY (owner_id) REFERENCES users(id)
            )');
            $ensureIdx('transfer_bids', 'idx_transfer_bids_bidder', 'bidder_id');
            $ensureIdx('transfer_bids', 'idx_transfer_bids_owner', 'owner_id');
            $ensureIdx('transfer_bids', 'idx_transfer_bids_uuid', 'player_uuid');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS player_inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                player_uuid VARCHAR(64) NOT NULL,
                player_data TEXT NOT NULL,
                purchase_price BIGINT NOT NULL,
                purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT "available",
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ensureIdx('player_inventory', 'idx_player_inventory_user_uuid', 'user_uuid');
            $ensureIdx('player_inventory', 'idx_player_inventory_status', 'status');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS scouting_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                player_uuid VARCHAR(64) NOT NULL,
                scouted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                report_quality INT DEFAULT 1,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ensureIdx('scouting_reports', 'idx_scouting_reports_user_uuid', 'user_uuid');
            $ensureIdx('scouting_reports', 'idx_scouting_reports_uuid', 'player_uuid');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS shop_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                price BIGINT NOT NULL,
                effect_type VARCHAR(50) NOT NULL,
                effect_value TEXT NOT NULL,
                category VARCHAR(50) NOT NULL,
                icon VARCHAR(50) DEFAULT "package",
                duration INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS user_inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                item_id INT NOT NULL,
                purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                quantity INT DEFAULT 1,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid),
                FOREIGN KEY (item_id) REFERENCES shop_items(id)
            )');
            $ensureIdx('user_inventory', 'idx_user_inventory_user_uuid', 'user_uuid');
            $ensureIdx('user_inventory', 'idx_user_inventory_expires', 'expires_at');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS club_staff (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                staff_type VARCHAR(50) NOT NULL,
                name VARCHAR(255) NOT NULL,
                level INT DEFAULT 1,
                salary BIGINT NOT NULL,
                contract_weeks INT DEFAULT 52,
                contract_weeks_remaining INT DEFAULT 52,
                hired_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                bonus_applied_this_week TINYINT(1) DEFAULT 0,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS young_players (
                id INT AUTO_INCREMENT PRIMARY KEY,
                club_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                age INT NOT NULL,
                position VARCHAR(10) NOT NULL,
                potential_rating INT NOT NULL,
                current_rating INT NOT NULL,
                development_stage VARCHAR(20) DEFAULT "academy",
                contract_years INT DEFAULT 3,
                value BIGINT NOT NULL,
                training_focus VARCHAR(50) DEFAULT "balanced",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                promoted_at DATETIME NULL,
                FOREIGN KEY (club_id) REFERENCES users(id) ON DELETE CASCADE
            )');
            $ensureIdx('young_players', 'idx_young_players_club', 'club_id');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS young_player_bids (
                id INT AUTO_INCREMENT PRIMARY KEY,
                young_player_id INT NOT NULL,
                bidder_club_id INT NOT NULL,
                owner_club_id INT NOT NULL,
                bid_amount BIGINT NOT NULL,
                status VARCHAR(20) DEFAULT "pending",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                FOREIGN KEY (young_player_id) REFERENCES young_players(id) ON DELETE CASCADE,
                FOREIGN KEY (bidder_club_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (owner_club_id) REFERENCES users(id) ON DELETE CASCADE
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS nation_calls (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                called_players TEXT NOT NULL,
                total_reward BIGINT NOT NULL,
                call_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS news (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                category VARCHAR(50) NOT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT "normal",
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                player_data TEXT NULL,
                actions TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_news_user_uuid (user_uuid),
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ensureIdx('news', 'idx_news_expires', 'expires_at');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS player_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                player_id VARCHAR(64) NOT NULL,
                player_name VARCHAR(255) NOT NULL,
                position VARCHAR(10) NOT NULL,
                matches_played INT DEFAULT 0,
                goals INT DEFAULT 0,
                assists INT DEFAULT 0,
                yellow_cards INT DEFAULT 0,
                red_cards INT DEFAULT 0,
                total_rating DOUBLE DEFAULT 0,
                avg_rating DOUBLE DEFAULT 0,
                clean_sheets INT DEFAULT 0,
                saves INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_player (user_uuid, player_id),
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            $ok = $ok && $db->exec('CREATE TABLE IF NOT EXISTS support_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_uuid CHAR(16) NOT NULL,
                ticket_number VARCHAR(64) UNIQUE NOT NULL,
                priority VARCHAR(20) DEFAULT "medium",
                category VARCHAR(50) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) DEFAULT "open",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_response_at DATETIME NULL,
                admin_response TEXT NULL,
                resolution_notes TEXT NULL,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
            // Seed shop items if empty
            $result = $db->query('SELECT COUNT(*) as count FROM shop_items');
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : ['count' => 0];
            $count = isset($row['count']) ? (int)$row['count'] : 0;
            if ($count === 0) {
                $default_items = [
                    ['Training Camp', 'Boost all players rating by +2 for 7 days', 5000000, 'player_boost', '{"rating": 2}', 'training', 'dumbbell', 7],
                    ['Fitness Coach', 'Reduce injury risk by 50% for 14 days', 3000000, 'injury_protection', '{"reduction": 0.5}', 'training', 'heart-pulse', 14],
                    ['Skill Academy', 'Boost specific position players by +3 rating for 5 days', 4000000, 'position_boost', '{"rating": 3}', 'training', 'graduation-cap', 5],
                    ['Sponsorship Deal', 'Increase budget by €10M instantly', 8000000, 'budget_boost', '{"amount": 10000000}', 'financial', 'handshake', 0],
                    ['Stadium Upgrade', 'Generate €500K daily for 30 days', 15000000, 'daily_income', '{"amount": 500000}', 'financial', 'building', 30],
                    ['Merchandise Boost', 'Increase transfer sale prices by 20% for 14 days', 6000000, 'sale_boost', '{"multiplier": 1.2}', 'financial', 'shopping-bag', 14],
                    ['Lucky Charm', 'Increase chance of successful transfers by 25%', 2500000, 'transfer_luck', '{"boost": 0.25}', 'special', 'clover', 10],
                    ['Scout Network', 'Reveal hidden player stats for 7 days', 3500000, 'player_insight', '{"enabled": true}', 'special', 'search', 7],
                    ['Energy Drink', 'Boost team performance by 15% for next 3 matches', 1500000, 'match_boost', '{"performance": 0.15, "matches": 3}', 'special', 'zap', 0],
                    ['Golden Boot', 'Permanently increase striker ratings by +1', 20000000, 'permanent_boost', '{"position": "ST", "rating": 1}', 'premium', 'award', 0],
                    ['Tactical Genius', 'Unlock advanced formations for 30 days', 12000000, 'formation_unlock', '{"advanced": true}', 'premium', 'brain', 30],
                    ['Club Legend', 'Attract better players in transfers for 21 days', 18000000, 'player_attraction', '{"quality_boost": 0.3}', 'premium', 'star', 21],
                    ['Youth Academy', 'Permanently increase squad size by +2 players', 25000000, 'squad_expansion', '{"players": 2}', 'premium', 'users', 0],
                    ['Training Facilities', 'Permanently increase squad size by +3 players', 35000000, 'squad_expansion', '{"players": 3}', 'premium', 'building-2', 0],
                    ['Elite Academy', 'Permanently increase squad size by +5 players', 50000000, 'squad_expansion', '{"players": 5}', 'premium', 'graduation-cap', 0],
                    ['Stadium Name Change', 'Allows you to change your stadium name', 2000000, 'stadium_rename', '{"enabled": true}', 'special', 'edit-3', 0]
                ];
                $ins = $db->prepare('INSERT INTO shop_items (name, description, price, effect_type, effect_value, category, icon, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if ($ins === false) {
                    $errors[] = 'Failed to prepare shop items insert: ' . $db->lastErrorMsg();
                } else {
                    foreach ($default_items as $item) {
                        $ins->bindValue(1, $item[0], SQLITE3_TEXT);
                        $ins->bindValue(2, $item[1], SQLITE3_TEXT);
                        $ins->bindValue(3, $item[2], SQLITE3_INTEGER);
                        $ins->bindValue(4, $item[3], SQLITE3_TEXT);
                        $ins->bindValue(5, $item[4], SQLITE3_TEXT);
                        $ins->bindValue(6, $item[5], SQLITE3_TEXT);
                        $ins->bindValue(7, $item[6], SQLITE3_TEXT);
                        $ins->bindValue(8, $item[7], SQLITE3_INTEGER);
                        $exec = $ins->execute();
                        if ($exec === false) {
                            $errors[] = 'Failed to insert shop item ' . $item[0] . ': ' . $db->lastErrorMsg();
                        }
                    }
                    $success[] = 'MySQL shop items seeded successfully (' . count($default_items) . ' items)';
                }
            }
            // League tables via shared function
            require_once 'includes/league_functions.php';
            createLeagueTables($db);

            if ($ok) {
                $success[] = 'MySQL tables created successfully';
                $table_exists = true;
                $is_ready = $table_exists && $has_users;
            } else {
                $errors[] = 'Failed creating MySQL tables';
            }
            $db->close();
        } else {
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
            club_exp INTEGER DEFAULT 0,
            club_level INTEGER DEFAULT 1,
            matches_played INTEGER DEFAULT 0,
            user_plan TEXT DEFAULT "free",
            plan_expires_at DATETIME,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )';

            if ($db->exec($sql)) {
                $success[] = 'Database and users table created successfully';

                // Add missing columns to existing users table (migration)
                try {
                    $result = $db->query("PRAGMA table_info(users)");
                    $columns = [];
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $columns[] = $row['name'];
                    }

                    // Add club_exp column if missing
                    if (!in_array('club_exp', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN club_exp INTEGER DEFAULT 0');
                        $success[] = 'Added club_exp column to users table';
                    }

                    // Add club_level column if missing
                    if (!in_array('club_level', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN club_level INTEGER DEFAULT 1');
                        $success[] = 'Added club_level column to users table';
                    }

                    // Add user_plan column if missing
                    if (!in_array('user_plan', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN user_plan TEXT DEFAULT "free"');
                        $success[] = 'Added user_plan column to users table';
                    }

                    // Add plan_expires_at column if missing
                    if (!in_array('plan_expires_at', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN plan_expires_at DATETIME');
                        $success[] = 'Added plan_expires_at column to users table';
                    }

                    // Add substitutes column if missing
                    if (!in_array('substitutes', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN substitutes TEXT DEFAULT "[]"');
                        $success[] = 'Added substitutes column to users table';
                    }

                    // Add max_players column if missing
                    if (!in_array('max_players', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN max_players INTEGER DEFAULT 23');
                        $success[] = 'Added max_players column to users table';
                    }

                    // Add fans column if missing
                    if (!in_array('fans', $columns)) {
                        $db->exec('ALTER TABLE users ADD COLUMN fans INTEGER DEFAULT 5000');
                        $success[] = 'Added fans column to users table';
                    }
                } catch (Exception $e) {
                    // Migration failed, but continue - table might be new
                    $errors[] = 'Column migration warning: ' . $e->getMessage();
                }

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
                $db->exec('CREATE INDEX IF NOT EXISTS idx_transfer_bids_bidder ON transfer_bids (bidder_id)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_transfer_bids_owner ON transfer_bids (owner_id)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_transfer_bids_uuid ON transfer_bids (player_uuid)');

                $db->exec('CREATE TABLE IF NOT EXISTS player_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                player_uuid TEXT NOT NULL,
                player_data TEXT NOT NULL,
                purchase_price INTEGER NOT NULL,
                purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TEXT DEFAULT "available",
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_player_inventory_user_uuid ON player_inventory (user_uuid)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_player_inventory_status ON player_inventory (status)');

                // Scouting system table
                $db->exec('CREATE TABLE IF NOT EXISTS scouting_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                player_uuid TEXT NOT NULL,
                scouted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                report_quality INTEGER DEFAULT 1,
                FOREIGN KEY (user_uuid) REFERENCES users (uuid)
            )');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_scouting_reports_user_uuid ON scouting_reports (user_uuid)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_scouting_reports_uuid ON scouting_reports (player_uuid)');

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

                    // Check scouting_reports table
                    $result = $db->query("PRAGMA table_info(scouting_reports)");
                    $scout_columns = [];
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $scout_columns[] = $row['name'];
                    }

                    // Add player_uuid column to scouting_reports if it doesn't exist
                    if (!in_array('player_uuid', $scout_columns)) {
                        $db->exec('ALTER TABLE scouting_reports ADD COLUMN player_uuid TEXT DEFAULT ""');
                    }

                    // Migrate scouting_reports data from player_id to player_uuid if needed
                    if (in_array('player_id', $scout_columns) && in_array('player_uuid', $scout_columns)) {
                        // For scouting reports, player_id is already the UUID, so we can copy it directly
                        $db->exec('UPDATE scouting_reports SET player_uuid = player_id WHERE player_uuid = ""');
                    }
                } catch (Exception $e) {
                    // Migration failed, but continue - table might be new
                }

                // Shop system tables
                $db->exec('CREATE TABLE IF NOT EXISTS user_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                item_id INTEGER NOT NULL,
                purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                quantity INTEGER DEFAULT 1,
                FOREIGN KEY (user_uuid) REFERENCES users (uuid),
                FOREIGN KEY (item_id) REFERENCES shop_items (id)
            )');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_user_inventory_user_uuid ON user_inventory (user_uuid)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_user_inventory_expires ON user_inventory (expires_at)');

                // Staff system table
                $db->exec('CREATE TABLE IF NOT EXISTS club_staff (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                staff_type TEXT NOT NULL,
                name TEXT NOT NULL,
                level INTEGER DEFAULT 1,
                salary INTEGER NOT NULL,
                contract_weeks INTEGER DEFAULT 52,
                contract_weeks_remaining INTEGER DEFAULT 52,
                hired_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                bonus_applied_this_week BOOLEAN DEFAULT 0,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');

                // Shop system tables
                $db->exec('CREATE TABLE IF NOT EXISTS shop_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                price INTEGER NOT NULL,
                effect_type TEXT NOT NULL,
                effect_value TEXT NOT NULL,
                category TEXT NOT NULL,
                icon TEXT DEFAULT "package",
                duration INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

                // Seed shop items if table is empty
                $stmt = $db->prepare('SELECT COUNT(*) as count FROM shop_items');
                $result = $stmt->execute();
                $count = $result->fetchArray(SQLITE3_ASSOC)['count'];

                if ($count == 0) {
                    $default_items = [
                        // Training Items
                        ['Training Camp', 'Boost all players rating by +2 for 7 days', 5000000, 'player_boost', '{"rating": 2}', 'training', 'dumbbell', 7],
                        ['Fitness Coach', 'Reduce injury risk by 50% for 14 days', 3000000, 'injury_protection', '{"reduction": 0.5}', 'training', 'heart-pulse', 14],
                        ['Skill Academy', 'Boost specific position players by +3 rating for 5 days', 4000000, 'position_boost', '{"rating": 3}', 'training', 'graduation-cap', 5],

                        // Financial Items
                        ['Sponsorship Deal', 'Increase budget by €10M instantly', 8000000, 'budget_boost', '{"amount": 10000000}', 'financial', 'handshake', 0],
                        ['Stadium Upgrade', 'Generate €500K daily for 30 days', 15000000, 'daily_income', '{"amount": 500000}', 'financial', 'building', 30],
                        ['Merchandise Boost', 'Increase transfer sale prices by 20% for 14 days', 6000000, 'sale_boost', '{"multiplier": 1.2}', 'financial', 'shopping-bag', 14],

                        // Special Items
                        ['Lucky Charm', 'Increase chance of successful transfers by 25%', 2500000, 'transfer_luck', '{"boost": 0.25}', 'special', 'clover', 10],
                        ['Scout Network', 'Reveal hidden player stats for 7 days', 3500000, 'player_insight', '{"enabled": true}', 'special', 'search', 7],
                        ['Energy Drink', 'Boost team performance by 15% for next 3 matches', 1500000, 'match_boost', '{"performance": 0.15, "matches": 3}', 'special', 'zap', 0],

                        // Premium Items
                        ['Golden Boot', 'Permanently increase striker ratings by +1', 20000000, 'permanent_boost', '{"position": "ST", "rating": 1}', 'premium', 'award', 0],
                        ['Tactical Genius', 'Unlock advanced formations for 30 days', 12000000, 'formation_unlock', '{"advanced": true}', 'premium', 'brain', 30],
                        ['Club Legend', 'Attract better players in transfers for 21 days', 18000000, 'player_attraction', '{"quality_boost": 0.3}', 'premium', 'star', 21],

                        // Squad Expansion Items
                        ['Youth Academy', 'Permanently increase squad size by +2 players', 25000000, 'squad_expansion', '{"players": 2}', 'premium', 'users', 0],
                        ['Training Facilities', 'Permanently increase squad size by +3 players', 35000000, 'squad_expansion', '{"players": 3}', 'premium', 'building-2', 0],
                        ['Elite Academy', 'Permanently increase squad size by +5 players', 50000000, 'squad_expansion', '{"players": 5}', 'premium', 'graduation-cap', 0],

                        // Stadium Items
                        ['Stadium Name Change', 'Allows you to change your stadium name', 2000000, 'stadium_rename', '{"enabled": true}', 'special', 'edit-3', 0]
                    ];

                    $stmt = $db->prepare('INSERT INTO shop_items (name, description, price, effect_type, effect_value, category, icon, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

                    foreach ($default_items as $item) {
                        $stmt->bindValue(1, $item[0], SQLITE3_TEXT);
                        $stmt->bindValue(2, $item[1], SQLITE3_TEXT);
                        $stmt->bindValue(3, $item[2], SQLITE3_INTEGER);
                        $stmt->bindValue(4, $item[3], SQLITE3_TEXT);
                        $stmt->bindValue(5, $item[4], SQLITE3_TEXT);
                        $stmt->bindValue(6, $item[5], SQLITE3_TEXT);
                        $stmt->bindValue(7, $item[6], SQLITE3_TEXT);
                        $stmt->bindValue(8, $item[7], SQLITE3_INTEGER);
                        $stmt->execute();
                    }

                    $success[] = 'Shop items seeded successfully (' . count($default_items) . ' items)';
                } else {
                    $success[] = 'Shop items already exist (' . $count . ' items)';
                }

                // League tables
                require_once 'includes/league_functions.php';
                createLeagueTables($db);

                // Additional system tables

                // User settings table
                $db->exec('CREATE TABLE IF NOT EXISTS user_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                UNIQUE(user_id, setting_key)
            )');

                // Young players table
                $db->exec('CREATE TABLE IF NOT EXISTS young_players (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                age INTEGER NOT NULL,
                position TEXT NOT NULL,
                potential_rating INTEGER NOT NULL,
                current_rating INTEGER NOT NULL,
                development_stage TEXT DEFAULT "academy",
                contract_years INTEGER DEFAULT 3,
                value INTEGER NOT NULL,
                training_focus TEXT DEFAULT "balanced",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                promoted_at DATETIME,
                FOREIGN KEY (club_id) REFERENCES users (id) ON DELETE CASCADE
            )');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_young_players_club ON young_players (club_id)');

                // Young player bids table
                $db->exec('CREATE TABLE IF NOT EXISTS young_player_bids (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                young_player_id INTEGER NOT NULL,
                bidder_club_id INTEGER NOT NULL,
                owner_club_id INTEGER NOT NULL,
                bid_amount INTEGER NOT NULL,
                status TEXT DEFAULT "pending",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                FOREIGN KEY (young_player_id) REFERENCES young_players (id) ON DELETE CASCADE,
                FOREIGN KEY (bidder_club_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (owner_club_id) REFERENCES users (id) ON DELETE CASCADE
            )');

                // Nation calls table
                $db->exec('CREATE TABLE IF NOT EXISTS nation_calls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                called_players TEXT NOT NULL,
                total_reward INTEGER NOT NULL,
                call_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_uuid) REFERENCES users (uuid)
            )');

                // Stadiums table
                $db->exec('CREATE TABLE IF NOT EXISTS stadiums (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                name TEXT DEFAULT "Home Stadium",
                capacity INTEGER DEFAULT 10000,
                level INTEGER DEFAULT 1,
                facilities TEXT DEFAULT "{}",
                last_upgrade DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_uuid) REFERENCES users (uuid)
            )');

                // User feedback table
                $db->exec('CREATE TABLE IF NOT EXISTS user_feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                category TEXT NOT NULL,
                subject TEXT NOT NULL,
                message TEXT NOT NULL,
                status TEXT DEFAULT "pending",
                reward_amount INTEGER DEFAULT 140,
                reward_paid BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                admin_response TEXT,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');

                // News table
                $db->exec('CREATE TABLE IF NOT EXISTS news (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                category TEXT NOT NULL,
                priority TEXT NOT NULL DEFAULT "normal",
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                player_data TEXT,
                actions TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                FOREIGN KEY (user_uuid) REFERENCES users (uuid)
            )');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_news_user_uuid ON news (user_uuid)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_news_expires ON news (expires_at)');

                // Player stats table
                $db->exec('CREATE TABLE IF NOT EXISTS player_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
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
                FOREIGN KEY (user_uuid) REFERENCES users (uuid),
                UNIQUE(user_uuid, player_id)
            )');

                // Support tickets table
                $db->exec('CREATE TABLE IF NOT EXISTS support_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_uuid TEXT NOT NULL,
                ticket_number TEXT UNIQUE NOT NULL,
                priority TEXT DEFAULT "medium",
                category TEXT NOT NULL,
                subject TEXT NOT NULL,
                message TEXT NOT NULL,
                status TEXT DEFAULT "open",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_response_at DATETIME,
                admin_response TEXT,
                resolution_notes TEXT,
                FOREIGN KEY (user_uuid) REFERENCES users(uuid)
            )');

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
        }
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

// Handle database repair
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair'])) {
    try {
        // Ensure database directory exists
        $db_dir = dirname($db_file);
        if (!is_dir($db_dir)) {
            if (mkdir($db_dir, 0755, true)) {
                $success[] = 'Created database directory: ' . $db_dir;
            } else {
                $errors[] = 'Failed to create database directory: ' . $db_dir;
            }
        }

        // Set proper permissions on directory
        if (is_dir($db_dir)) {
            chmod($db_dir, 0755);
            $success[] = 'Set directory permissions to 755';
        }

        // If database file exists, set proper permissions
        if (file_exists($db_file)) {
            chmod($db_file, 0666);
            $success[] = 'Set database file permissions to 666';
        }

        // Test database connection
        $db = new SQLite3($db_file);
        $db->exec('PRAGMA foreign_keys = ON');

        // Test basic functionality
        $result = $db->query("SELECT sqlite_version()");
        if ($result) {
            $row = $result->fetchArray();
            $success[] = 'Database connection test successful (SQLite ' . $row[0] . ')';
        }

        $db->close();

        // Recheck database status
        $db_exists = file_exists($db_file);
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
                $errors[] = 'Database repair check failed: ' . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database repair failed: ' . $e->getMessage();
    }
}

// Handle admin creation (Step 3)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_admin'])) {
    try {
        $name = trim($_POST['admin_name'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        if ($name === '' || $email === '' || $password === '') {
            $errors[] = 'Admin name, email, and password are required';
        } else {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT COUNT(*) as c FROM users WHERE email = :email');
            if ($stmt === false) {
                $errors[] = 'Failed to prepare uniqueness check: ' . $db->lastErrorMsg();
            } else {
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $res = $stmt->execute();
                $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                if ((int)($row['c'] ?? 0) > 0) {
                    $errors[] = 'Email already exists';
                } else {
                    $ins = $db->prepare('INSERT INTO users (name, email, password, club_name, formation, team, budget) VALUES (:name, :email, :password, :club_name, :formation, :team, :budget)');
                    if ($ins === false) {
                        $errors[] = 'Failed to prepare admin insert: ' . $db->lastErrorMsg();
                    } else {
                        $ins->bindValue(':name', $name, SQLITE3_TEXT);
                        $ins->bindValue(':email', $email, SQLITE3_TEXT);
                        $ins->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
                        $ins->bindValue(':club_name', $name . ' FC', SQLITE3_TEXT);
                        $ins->bindValue(':formation', '4-4-2', SQLITE3_TEXT);
                        $ins->bindValue(':team', '[]', SQLITE3_TEXT);
                        $ins->bindValue(':budget', DEFAULT_BUDGET, SQLITE3_INTEGER);
                        $exec = $ins->execute();
                        if ($exec === false) {
                            $errors[] = 'Failed to create admin user: ' . $db->lastErrorMsg();
                        } else {
                            $success[] = 'Admin user created successfully';
                            $has_users = true;
                            $is_ready = $table_exists && $has_users;
                        }
                    }
                }
            }
            $db->close();
        }
    } catch (Exception $e) {
        $errors[] = 'Admin creation error: ' . $e->getMessage();
    }
}

require_once 'partials/layout.php';

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

            <?php if ($step === 1): ?>
                <div class="mb-8 border-t pt-6">
                    <h2 class="text-xl font-semibold mb-4">Environment Configuration</h2>
                    <p class="text-sm text-gray-600 mb-4">Database settings are managed via environment variables (.env). Review the current values:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded border">
                            <div class="text-xs text-gray-500">DB_DRIVER</div>
                            <div class="font-mono text-sm"><?php echo htmlspecialchars(DB_DRIVER); ?></div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded border">
                            <div class="text-xs text-gray-500">MYSQL_HOST</div>
                            <div class="font-mono text-sm"><?php echo htmlspecialchars(MYSQL_HOST); ?></div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded border">
                            <div class="text-xs text-gray-500">MYSQL_PORT</div>
                            <div class="font-mono text-sm"><?php echo htmlspecialchars(MYSQL_PORT); ?></div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded border">
                            <div class="text-xs text-gray-500">MYSQL_DB</div>
                            <div class="font-mono text-sm"><?php echo htmlspecialchars(MYSQL_DB); ?></div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded border">
                            <div class="text-xs text-gray-500">MYSQL_USER</div>
                            <div class="font-mono text-sm"><?php echo htmlspecialchars(MYSQL_USER); ?></div>
                        </div>
                    </div>
                    <div class="mt-4 text-sm text-gray-600">
                        Edit <span class="font-mono">.env</span> and restart the server to change these values.
                    </div>
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-2">Database Inputs</h3>
                        <div class="text-sm text-gray-600 mb-3">Display-only inputs for review.</div>
                        <div id="uiMysql" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">MYSQL_HOST</label>
                                <input type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars(MYSQL_HOST); ?>" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">MYSQL_PORT</label>
                                <input type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars(MYSQL_PORT); ?>" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">MYSQL_DB</label>
                                <input type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars(MYSQL_DB); ?>" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">MYSQL_USER</label>
                                <input type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars(MYSQL_USER); ?>" readonly>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">MYSQL_PASSWORD</label>
                                <input type="password" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars(MYSQL_PASSWORD); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-2">Seed Data Configuration</h3>
                        <p class="text-sm text-gray-600 mb-4">Display-only preview of available demo seed options.</p>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2 p-3 border rounded-lg">
                                    <input type="radio" name="ui_seed_mode" value="none">
                                    <span class="text-sm">None</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border rounded-lg">
                                    <input type="radio" name="ui_seed_mode" value="shop">
                                    <span class="text-sm">Shop Items</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border rounded-lg">
                                    <input type="radio" name="ui_seed_mode" value="clubs">
                                    <span class="text-sm">Demo Clubs</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border rounded-lg md:col-span-3">
                                    <input type="radio" name="ui_seed_mode" value="all">
                                    <span class="text-sm">Everything (Clubs + Shop Items)</span>
                                </label>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="font-semibold text-blue-800 mb-2">Demo Clubs</div>
                                <div class="text-sm text-blue-700">
                                    <div class="mb-2">Total: <?php echo count(DEMO_CLUBS); ?> clubs</div>
                                    <ul class="list-disc list-inside">
                                        <?php foreach (array_slice(DEMO_CLUBS, 0, 5) as $club): ?>
                                            <li><?php echo htmlspecialchars($club['name']); ?> (<?php echo htmlspecialchars($club['formation']); ?>)</li>
                                        <?php endforeach; ?>
                                        <?php if (count(DEMO_CLUBS) > 5): ?>
                                            <li>... and <?php echo count(DEMO_CLUBS) - 5; ?> more</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                                <div class="font-semibold text-purple-800 mb-2">Shop Items</div>
                                <div class="text-sm text-purple-700">
                                    <ul class="list-disc list-inside">
                                        <li>Training Items — player performance boosts</li>
                                        <li>Financial Items — budget and income boosts</li>
                                        <li>Special Items — unique advantages</li>
                                        <li>Premium Items — permanent upgrades</li>
                                        <li>Squad Expansion — increase team size</li>
                                        <li>Stadium Items — customization</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600">
                                Actual seeding is performed in Step 2 or via the Seed buttons when ready.
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-2">Admin Configuration</h3>
                        <p class="text-sm text-gray-600 mb-4">Provide admin details now; they will be used in Step 3.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Admin Name</label>
                                <input id="ui_admin_name" type="text" class="w-full px-3 py-2 border rounded-lg" placeholder="Admin User">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Admin Email</label>
                                <input id="ui_admin_email" type="email" class="w-full px-3 py-2 border rounded-lg" placeholder="admin@example.com">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">Admin Password</label>
                                <input id="ui_admin_password" type="password" class="w-full px-3 py-2 border rounded-lg" placeholder="Password">
                            </div>
                        </div>
                        <div class="text-sm text-gray-600 mt-3">Inputs persist locally until admin creation.</div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-lg font-semibold mb-2">Setup</h3>
                        <p class="text-sm text-gray-600 mb-4">Run database setup with progress logs (connection, database, tables, seed).</p>
                        <form method="POST" id="connTestForm" class="flex gap-3 items-center">
                            <input type="hidden" name="step" value="1">
                            <button type="submit" id="setupBtnStep1" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Setup</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
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

                        <div class="flex justify-center">
                            <a href="index.php" class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-semibold">
                                <i data-lucide="play" class="w-5 h-5"></i>
                                Go to <?php echo htmlspecialchars($app_name); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($step === 2): ?>
                    <form method="POST" class="space-y-6">
                        <div class="border-t pt-6">
                            <h2 class="text-xl font-semibold mb-4">Setup Database (Install + Seed)</h2>
                            <div class="flex gap-3 flex-wrap">
                                <input type="hidden" name="step" value="2">
                                <button type="button" id="runSetupBtn" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Run Setup</button>
                                <a href="?step=1" class="inline-block px-6 py-2 rounded-lg border">Back</a>
                                <a href="?step=3" class="inline-block px-6 py-2 rounded-lg border">Next</a>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-lg font-semibold mb-2">Logs</h3>
                                <ul id="setupLogs" class="text-sm space-y-1 bg-gray-50 p-3 rounded border"></ul>
                            </div>
                        </div>
                    </form>
                <?php elseif ($step === 3): ?>
                    <form method="POST" class="space-y-6">
                        <div class="border-t pt-6">
                            <h2 class="text-xl font-semibold mb-4">Create Admin User</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Admin Name <?php echo !$has_users ? '(Required)' : '(Optional)'; ?></label>
                                    <input type="text" name="admin_name" <?php echo !$has_users ? 'required' : ''; ?> class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Admin User">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Admin Email <?php echo !$has_users ? '(Required)' : '(Optional)'; ?></label>
                                    <input type="email" name="admin_email" <?php echo !$has_users ? 'required' : ''; ?> class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="admin@example.com">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Admin Password <?php echo !$has_users ? '(Required)' : '(Optional)'; ?></label>
                                    <input type="password" name="admin_password" <?php echo !$has_users ? 'required' : ''; ?> class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Password">
                                </div>
                            </div>
                            <div class="flex gap-3 flex-wrap">
                                <input type="hidden" name="step" value="3">
                                <button type="submit" name="complete_admin" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Create Admin</button>
                                <a href="?step=2" class="inline-block px-6 py-2 rounded-lg border">Back</a>
                                <a href="?step=4" class="inline-block px-6 py-2 rounded-lg border">Next</a>
                            </div>
                        </div>
                    </form>
                <?php elseif ($step === 4): ?>
                    <div class="border-t pt-6">
                        <h2 class="text-xl font-semibold mb-4">Finish</h2>
                        <div class="flex justify-center gap-3 flex-wrap">
                            <a href="index.php" class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-semibold">
                                <i data-lucide="play" class="w-5 h-5"></i>
                                Go to <?php echo htmlspecialchars($app_name); ?>
                            </a>
                            <a href="?step=3" class="inline-block px-6 py-2 rounded-lg border">Back</a>
                        </div>
                    </div>
                <?php endif; ?>
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

            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong>Troubleshooting:</strong> If you're having database issues,
                    <a href="db_test.php" class="underline hover:text-blue-900">run the database test</a>
                    to diagnose connection problems.
                </p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // SweetAlert for reset system button
        document.getElementById('resetSystemBtn')?.addEventListener('click', function() {
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
        document.getElementById('resetDatabaseBtn')?.addEventListener('click', function() {
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

        // SweetAlert for seed all data button (combined)
        document.getElementById('seedAllBtn')?.addEventListener('click', function() {
            Swal.fire({
                icon: 'question',
                title: 'Seed Complete Demo Data?',
                html: `
                    <div class="text-left">
                        <p class="mb-3">This will create a complete demo environment with:</p>
                        
                        <div class="bg-blue-50 p-3 rounded mb-3">
                            <h4 class="font-semibold text-blue-800 mb-2">🏆 Demo Clubs (<?php echo count(DEMO_CLUBS); ?>)</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <?php foreach (array_slice(DEMO_CLUBS, 0, 3) as $club): ?>
                                <li>• <?php echo htmlspecialchars($club['name']); ?> (<?php echo htmlspecialchars($club['formation']); ?>)</li>
                                <?php endforeach; ?>
                                <?php if (count(DEMO_CLUBS) > 3): ?>
                                <li>• ... and <?php echo count(DEMO_CLUBS) - 3; ?> more clubs</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="bg-purple-50 p-3 rounded mb-3">
                            <h4 class="font-semibold text-purple-800 mb-2">🛍️ Shop Items (16)</h4>
                            <ul class="text-sm text-purple-700 space-y-1">
                                <li>• Training Items (3) - Boost player performance</li>
                                <li>• Financial Items (3) - Increase budget & income</li>
                                <li>• Special Items (3) - Unique game advantages</li>
                                <li>• Premium Items (3) - Permanent upgrades</li>
                                <li>• Squad Expansion (3) - Increase team size</li>
                                <li>• Stadium Items (1) - Customize your stadium</li>
                            </ul>
                        </div>

                        <p class="text-sm text-gray-600">Perfect for testing and exploring all game features!</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Create Everything',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('database/seed.php?seed=all')
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
                        title: '🎉 Demo Data Created!',
                        html: `
                            <div class="text-left">
                                <p class="mb-4">Complete demo environment has been set up successfully!</p>
                                
                                <div class="bg-green-50 p-3 rounded mb-3">
                                    <h4 class="font-semibold text-green-800 mb-2">✅ What's Ready:</h4>
                                    <ul class="text-sm text-green-700 space-y-1">
                                        <li>• <?php echo count(DEMO_CLUBS); ?> demo clubs with complete teams</li>
                                        <li>• 16 shop items across all categories</li>
                                        <li>• Young players for academy system</li>
                                        <li>• Login credentials for testing</li>
                                    </ul>
                                </div>

                                <div class="bg-gray-50 p-3 rounded text-sm">
                                    <strong>🔑 Login Credentials:</strong><br>
                                    <?php foreach (array_slice(DEMO_CREDENTIALS, 0, 3, true) as $email => $password): ?>
                                    • <?php echo htmlspecialchars($email); ?> / <?php echo htmlspecialchars($password); ?><br>
                                    <?php endforeach; ?>
                                    <?php if (count(DEMO_CREDENTIALS) > 3): ?>
                                    • ... and <?php echo count(DEMO_CREDENTIALS) - 3; ?> more accounts<br>
                                    <?php endif; ?>
                                </div>
                            </div>
                        `,
                        confirmButtonColor: '#16a34a',
                        confirmButtonText: 'Close'
                    });
                }
            });
        });

        // SweetAlert for seed clubs button
        document.getElementById('seedClubsBtn')?.addEventListener('click', function() {
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
                        confirmButtonText: 'Close'
                    });
                }
            });
        });

        // SweetAlert for seed shop items button
        document.getElementById('seedShopBtn')?.addEventListener('click', function() {
            Swal.fire({
                icon: 'question',
                title: 'Seed Shop Items?',
                html: `
                    <p>This will create 16 shop items across different categories:</p>
                    <ul class="text-left mt-3 space-y-1">
                        <li>• <strong>Training Items:</strong> Training Camp, Fitness Coach, Skill Academy</li>
                        <li>• <strong>Financial Items:</strong> Sponsorship Deal, Stadium Upgrade, Merchandise Boost</li>
                        <li>• <strong>Special Items:</strong> Lucky Charm, Scout Network, Energy Drink</li>
                        <li>• <strong>Premium Items:</strong> Golden Boot, Tactical Genius, Club Legend</li>
                        <li>• <strong>Squad Expansion:</strong> Youth Academy, Training Facilities, Elite Academy</li>
                        <li>• <strong>Stadium Items:</strong> Stadium Name Change</li>
                    </ul>
                    <p class="mt-3 text-sm text-gray-600">Items range from €1.5M to €50M with various effects and durations.</p>
                `,
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Create Shop Items',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('database/seed.php?seed=shop')
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
                        title: 'Shop Items Created!',
                        html: `
                            <div class="text-left">
                                <p class="mb-3">16 shop items have been created successfully!</p>
                                <div class="bg-gray-50 p-3 rounded text-sm">
                                    <strong>Categories Created:</strong><br>
                                    • Training Items (3 items)<br>
                                    • Financial Items (3 items)<br>
                                    • Special Items (3 items)<br>
                                    • Premium Items (3 items)<br>
                                    • Squad Expansion (3 items)<br>
                                    • Stadium Items (1 item)
                                </div>
                                <p class="mt-3 text-sm text-gray-600">Visit the shop page to see all available items!</p>
                            </div>
                        `,
                        confirmButtonColor: '#7c3aed',
                        confirmButtonText: 'Close'
                    });
                }
            });
        });

        // Step 1 Setup with popup logs
        (function() {
            const form = document.getElementById('connTestForm');
            const btn = document.getElementById('setupBtnStep1');
            if (!form || !btn) return;
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const name = (document.getElementById('ui_admin_name')?.value || '').trim();
                const email = (document.getElementById('ui_admin_email')?.value || '').trim();
                const password = (document.getElementById('ui_admin_password')?.value || '');
                const emailOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
                if (!name || !email || !password || !emailOk) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Admin Configuration Required',
                        text: !emailOk ? 'Provide a valid admin email' : 'Admin name, email, and password are required',
                        confirmButtonColor: '#ef4444'
                    });
                    return;
                }
                const seedMode = localStorage.getItem('seed_mode') || 'all';
                Swal.fire({
                    title: 'Running Setup',
                    html: '<ul id=\"swLogs\" class=\"text-sm text-left space-y-1\"></ul>',
                    didOpen: () => {
                        const ul = document.getElementById('swLogs');
                        if (ul) {
                            const li = document.createElement('li');
                            li.textContent = 'Starting setup...';
                            li.className = 'text-gray-700';
                            ul.appendChild(li);
                        }
                    },
                    showConfirmButton: false,
                    allowOutsideClick: false
                });
                fetch('api/setup_database_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            mode: seedMode,
                            admin_name: name,
                            admin_email: email,
                            admin_password: password
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const entries = Array.isArray(data.logs) ? data.logs : [];
                        const html = ['<div class=\"text-left\"><ul class=\"text-sm space-y-1\">'];
                        entries.forEach(entry => {
                            const cls = entry.type === 'error' ? 'text-red-600' :
                                entry.type === 'detail' ? 'text-gray-600' :
                                'text-green-700';
                            html.push('<li class=\"' + cls + '\">' + (entry.message || '') + '</li>');
                        });
                        html.push('</ul></div>');
                        Swal.fire({
                            icon: data.ok ? 'success' : 'error',
                            title: data.ok ? 'Setup Completed' : 'Setup Finished With Errors',
                            html: html.join(''),
                            confirmButtonColor: data.ok ? '#16a34a' : '#ef4444',
                            confirmButtonText: data.ok ? 'Start Playing' : 'Close'
                        }).then((result) => {
                            if (data.ok && result.isConfirmed) {
                                window.location.href = 'index.php';
                            }
                        });
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Setup Failed',
                            text: String(err),
                            confirmButtonColor: '#ef4444'
                        });
                    });
            });
        })();
        (function() {
            const n = document.getElementById('ui_admin_name');
            const e = document.getElementById('ui_admin_email');
            const p = document.getElementById('ui_admin_password');
            const apply = () => {
                n.value = localStorage.getItem('admin_name') || '';
                e.value = localStorage.getItem('admin_email') || '';
                p.value = localStorage.getItem('admin_password') || '';
            };
            const save = () => {
                localStorage.setItem('admin_name', n.value || '');
                localStorage.setItem('admin_email', e.value || '');
                localStorage.setItem('admin_password', p.value || '');
            };
            if (n && e && p) {
                apply();
                n.addEventListener('input', save);
                e.addEventListener('input', save);
                p.addEventListener('input', save);
            }
            const nameField = document.querySelector('input[name="admin_name"]');
            const emailField = document.querySelector('input[name="admin_email"]');
            const passField = document.querySelector('input[name="admin_password"]');
            if (nameField || emailField || passField) {
                const ln = localStorage.getItem('admin_name');
                const le = localStorage.getItem('admin_email');
                const lp = localStorage.getItem('admin_password');
                if (nameField && ln) nameField.value = ln;
                if (emailField && le) emailField.value = le;
                if (passField && lp) passField.value = lp;
            }
        })();

        // Setup Database (Install + Seed) with logs
        (function() {
            const btn = document.getElementById('runSetupBtn');
            const logsEl = document.getElementById('setupLogs');
            if (!btn || !logsEl) return;
            btn.addEventListener('click', function() {
                btn.disabled = true;
                logsEl.innerHTML = '';
                const li = (msg, cls) => {
                    const el = document.createElement('li');
                    el.textContent = msg;
                    if (cls) el.className = cls;
                    logsEl.appendChild(el);
                };
                li('Starting setup...', 'text-gray-700');
                const seedMode = localStorage.getItem('seed_mode') || 'all';
                fetch('api/setup_database_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            mode: seedMode
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const entries = Array.isArray(data.logs) ? data.logs : [];
                        entries.forEach(entry => {
                            const cls = entry.type === 'error' ? 'text-red-600' :
                                entry.type === 'detail' ? 'text-gray-600' :
                                'text-green-700';
                            li(entry.message, cls);
                        });
                        if (data.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Setup Completed',
                                text: 'Create tables, shop items, and demo clubs completed successfully.',
                                confirmButtonColor: '#16a34a',
                                confirmButtonText: 'Start Playing'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'index.php';
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Setup Finished With Errors',
                                text: 'Check logs for details.',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(err => {
                        li(String(err), 'text-red-600');
                        Swal.fire({
                            icon: 'error',
                            title: 'Setup Failed',
                            text: String(err),
                            confirmButtonColor: '#ef4444'
                        });
                    })
                    .finally(() => {
                        btn.disabled = false;
                    });
            });
        })();
        (function() {
            const radios = document.querySelectorAll('input[name="ui_seed_mode"]');
            const saved = localStorage.getItem('seed_mode') || 'all';
            let found = false;
            radios.forEach(r => {
                if (r.value === saved) {
                    r.checked = true;
                    found = true;
                }
            });
            if (!found) {
                const all = Array.from(radios).find(r => r.value === 'all');
                if (all) all.checked = true;
            }
            const sync = () => {
                let v = 'all';
                radios.forEach(r => {
                    if (r.checked) v = r.value;
                });
                localStorage.setItem('seed_mode', v);
            };
            radios.forEach(r => r.addEventListener('change', sync));
            sync();
        })();
    </script>
</div>
</div>

<?php
// End content capture and render layout
endContent($app_name . ' - Installation', '', false, true);
?>
