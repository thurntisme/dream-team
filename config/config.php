<?php
// Dream Team Configuration

// Load configuration from config file or use defaults
function loadConfig()
{
    $configFile = __DIR__ . '/config.json';

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return $config ?: [];
    }

    return [];
}

// Save configuration to file
function saveConfig($config)
{
    $configFile = __DIR__ . '/config.json';
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

// Get configuration with defaults
$config = loadConfig();

// Load environment file if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}

// Database configuration
define('APP_NAME', getenv('APP_NAME') ?: 'Dream Team');
define('DB_DRIVER', 'mysql');
define('DB_FILE', __DIR__ . '/../database/dreamteam.db');
define('MYSQL_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
define('MYSQL_PORT', getenv('MYSQL_PORT') ?: '3306');
define('MYSQL_DB', getenv('MYSQL_DB') ?: '');
define('MYSQL_USER', getenv('MYSQL_USER') ?: '');
define('MYSQL_PASSWORD', getenv('MYSQL_PASSWORD') ?: '');
require_once __DIR__ . '/../includes/db_adapter.php';

// Database connection function
function getDbConnection()
{
    try {
        // Check if database is available first
        if (!isDatabaseAvailable()) {
            $current_script = basename($_SERVER['SCRIPT_NAME']);
            $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
            $allowed_api = in_array($current_script, ['connection_test_api.php', 'setup_database_api.php'], true);
            if ($current_script !== 'install.php' && !$is_api_call) {
                header('Location: install.php');
                exit;
            } else if ($is_api_call && !$allowed_api) {
                throw new Exception("Database not initialized");
            }
        }
        
        $adapterConfig = [
            'db_file' => DB_FILE,
            'mysql_host' => MYSQL_HOST,
            'mysql_port' => MYSQL_PORT,
            'mysql_db' => MYSQL_DB,
            'mysql_user' => MYSQL_USER,
            'mysql_password' => MYSQL_PASSWORD
        ];
        $db = new DBAdapter(DB_DRIVER, $adapterConfig);
        return $db;
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Check if database is available and properly set up
function isDatabaseAvailable()
{
    try {
        if (empty(MYSQL_DB) || empty(MYSQL_USER)) {
            return false;
        }
        $adapterConfig = [
            'db_file' => DB_FILE,
            'mysql_host' => MYSQL_HOST,
            'mysql_port' => MYSQL_PORT,
            'mysql_db' => MYSQL_DB,
            'mysql_user' => MYSQL_USER,
            'mysql_password' => MYSQL_PASSWORD
        ];
        try {
            $db = new DBAdapter(DB_DRIVER, $adapterConfig);
        } catch (Throwable $e) {
            return false;
        }
        $exists = false;
        try {
            $res = $db->query('SELECT 1 FROM users LIMIT 1');
            $exists = $res !== false;
        } catch (Throwable $e) {
            $exists = false;
        }
        if ($exists) {
            try {
                // Ensure users has uuid column and populate it (16-char, no hyphens)
                $db->exec('ALTER TABLE users ADD COLUMN uuid CHAR(16) NULL');
                $db->exec('UPDATE users SET uuid = SUBSTR(REPLACE(UUID(), \"-\", \"\"), 1, 16) WHERE uuid IS NULL OR uuid = \"\"');

                // Ensure user_club table exists with user_uuid (16-char) and no substitutes/matches_played
                $db->exec('CREATE TABLE IF NOT EXISTS user_club (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_uuid CHAR(16) NOT NULL,
                    club_uuid CHAR(16) NULL,
                    club_name VARCHAR(255) NULL,
                    formation VARCHAR(20) DEFAULT \"4-4-2\",
                    team TEXT,
                    budget BIGINT DEFAULT ' . DEFAULT_BUDGET . ',
                    max_players INT DEFAULT 23,
                    fans INT DEFAULT 5000,
                    club_exp INT DEFAULT 0,
                    club_level INT DEFAULT 1,
                    user_plan VARCHAR(20) DEFAULT \"free\",
                    plan_expires_at DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_club_user_uuid (user_uuid),
                    INDEX idx_user_club_club_uuid (club_uuid)
                )');
                $db->exec('CREATE TABLE IF NOT EXISTS club_staff (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    club_uuid CHAR(16) NOT NULL,
                    staff_type VARCHAR(50) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    level INT DEFAULT 1,
                    salary BIGINT NOT NULL,
                    contract_weeks INT DEFAULT 52,
                    contract_weeks_remaining INT DEFAULT 52,
                    hired_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    bonus_applied_this_week TINYINT(1) DEFAULT 0,
                    INDEX idx_staff_club_uuid (club_uuid)
                )');

                $cols = [];
                $resCols = $db->query('SHOW COLUMNS FROM users');
                if ($resCols !== false) {
                    while ($row = $resCols->fetchArray(SQLITE3_ASSOC)) {
                        $field = $row['Field'] ?? ($row['field'] ?? null);
                        if ($field !== null) {
                            $cols[] = $field;
                        }
                    }
                }
                // If user_club is empty, migrate core club data from users (if present)
                $hasClubRows = false;
                $resClubCount = $db->query('SELECT COUNT(*) AS c FROM user_club');
                if ($resClubCount !== false) {
                    $rowC = $resClubCount->fetchArray(SQLITE3_ASSOC);
                    $hasClubRows = ((int)($rowC['c'] ?? 0)) > 0;
                }
                if (!$hasClubRows) {
                    $db->exec('INSERT INTO user_club (user_uuid, club_uuid, club_name, formation, team, budget, max_players, fans, club_exp, club_level, user_plan, plan_expires_at, created_at)
                        SELECT uuid,
                               SUBSTR(REPLACE(UUID(), \"-\", \"\"), 1, 16),
                               COALESCE(club_name, NULL), 
                               COALESCE(formation, \"4-4-2\"), 
                               COALESCE(team, \"[]\"), 
                               COALESCE(budget, ' . DEFAULT_BUDGET . '), 
                               COALESCE(max_players, 23), 
                               COALESCE(fans, 5000), 
                               COALESCE(club_exp, 0), 
                               COALESCE(club_level, 1), 
                               COALESCE(user_plan, \"free\"), 
                               plan_expires_at, 
                               created_at
                        FROM users');
                }

                // Migration: if columns were previously CHAR(36), convert existing values to 16-char format
                try {
                    $resColsUsers = $db->query('SHOW COLUMNS FROM users LIKE \"uuid\"');
                    $usersUuidType = null;
                    if ($resColsUsers !== false) {
                        $rowU = $resColsUsers->fetchArray(SQLITE3_ASSOC);
                        $usersUuidType = $rowU['Type'] ?? ($rowU['type'] ?? null);
                    }
                    $resColsClub = $db->query('SHOW COLUMNS FROM user_club LIKE \"user_uuid\"');
                    $clubUuidType = null;
                    if ($resColsClub !== false) {
                        $rowC = $resColsClub->fetchArray(SQLITE3_ASSOC);
                        $clubUuidType = $rowC['Type'] ?? ($rowC['type'] ?? null);
                    }
                    if ($usersUuidType && stripos($usersUuidType, 'char(36)') !== false) {
                        $db->exec('UPDATE users SET uuid = SUBSTR(REPLACE(uuid, \"-\", \"\"), 1, 16) WHERE uuid IS NOT NULL AND uuid != \"\" AND CHAR_LENGTH(uuid) != 16');
                        $db->exec('ALTER TABLE users MODIFY COLUMN uuid CHAR(16) NULL');
                    }
                    if ($clubUuidType && stripos($clubUuidType, 'char(36)') !== false) {
                        $db->exec('UPDATE user_club SET user_uuid = SUBSTR(REPLACE(user_uuid, \"-\", \"\"), 1, 16) WHERE user_uuid IS NOT NULL AND user_uuid != \"\" AND CHAR_LENGTH(user_uuid) != 16');
                        $db->exec('ALTER TABLE user_club MODIFY COLUMN user_uuid CHAR(16) NOT NULL');
                    }
                } catch (Throwable $e) { }

                // Add club_uuid column for existing installations and populate values
                try {
                    $db->exec('ALTER TABLE user_club ADD COLUMN club_uuid CHAR(16) NULL');
                    $db->exec('UPDATE user_club SET club_uuid = SUBSTR(REPLACE(UUID(), \"-\", \"\"), 1, 16) WHERE club_uuid IS NULL OR club_uuid = \"\"');
                    $db->exec('ALTER TABLE user_club MODIFY COLUMN club_uuid CHAR(16) NULL');
                } catch (Throwable $e) { }

                // Migrate club_staff from user_id to club_uuid
                try {
                    $db->exec('ALTER TABLE club_staff ADD COLUMN club_uuid CHAR(16) NULL');
                    $db->exec('UPDATE club_staff cs 
                               JOIN users u ON cs.user_id = u.id 
                               JOIN user_club uc ON uc.user_uuid = u.uuid
                               SET cs.club_uuid = uc.club_uuid
                               WHERE cs.club_uuid IS NULL OR cs.club_uuid = \"\"');
                    $db->exec('ALTER TABLE club_staff DROP COLUMN user_id');
                    $db->exec('ALTER TABLE club_staff MODIFY COLUMN club_uuid CHAR(16) NOT NULL');
                } catch (Throwable $e) { }

                // Ensure player_inventory exists and migrate to club_uuid
                try {
                    $db->exec('CREATE TABLE IF NOT EXISTS player_inventory (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        club_uuid CHAR(16) NOT NULL,
                        player_uuid CHAR(16) NOT NULL,
                        player_data TEXT NOT NULL,
                        purchase_price BIGINT NOT NULL,
                        purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                        status VARCHAR(20) DEFAULT \"available\",
                        INDEX idx_inventory_club_uuid (club_uuid),
                        INDEX idx_inventory_player_uuid (player_uuid),
                        INDEX idx_inventory_status (status)
                    )');
                    $db->exec('ALTER TABLE player_inventory ADD COLUMN club_uuid CHAR(16) NULL');
                    $db->exec('UPDATE player_inventory pi
                               JOIN users u ON pi.user_id = u.id
                               JOIN user_club uc ON uc.user_uuid = u.uuid
                               SET pi.club_uuid = uc.club_uuid
                               WHERE pi.club_uuid IS NULL OR pi.club_uuid = \"\"');
                    $db->exec('ALTER TABLE player_inventory DROP COLUMN user_id');
                    $db->exec('ALTER TABLE player_inventory MODIFY COLUMN club_uuid CHAR(16) NOT NULL');
                } catch (Throwable $e) { }
            } catch (Throwable $e) {
            }
        }
        $db->close();
        return $exists;
    } catch (Exception $e) {
        return false;
    }
}

// Check if database has users
function hasUsers()
{
    try {
        if (!isDatabaseAvailable()) {
            return false;
        }

        $db = getDbConnection();
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $db->close();

        return $row['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}
