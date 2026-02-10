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
define('DB_FILE', getenv('DB_FILE') ?: __DIR__ . '/../database/dreamteam.db');
define('APP_NAME', getenv('APP_NAME') ?: 'Dream Team');
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');
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
        if (DB_DRIVER === 'sqlite') {
            if (!file_exists(DB_FILE)) {
                return false;
            }

            $db = new SQLite3(DB_FILE);

            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            $tableExists = $result->fetchArray() !== false;

            if ($tableExists) {
                $result = $db->query("PRAGMA table_info(users)");
                $columns = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $columns[] = $row['name'];
                }

                if (!in_array('substitutes', $columns)) {
                    $db->exec('ALTER TABLE users ADD COLUMN substitutes TEXT DEFAULT "[]"' );
                }

                if (!in_array('max_players', $columns)) {
                    $db->exec('ALTER TABLE users ADD COLUMN max_players INTEGER DEFAULT 23');
                }

                if (!in_array('user_plan', $columns)) {
                    $db->exec('ALTER TABLE users ADD COLUMN user_plan TEXT DEFAULT "free"');
                }

                if (!in_array('plan_expires_at', $columns)) {
                    $db->exec('ALTER TABLE users ADD COLUMN plan_expires_at DATETIME');
                }

            }

            $db->close();
            return $tableExists;
        } else {
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
            $db->close();
            return $exists;
        }
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
