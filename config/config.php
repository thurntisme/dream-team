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

// Database configuration
define('DB_FILE', $config['db_file'] ?? __DIR__ . '/../database/dreamteam.db');
define('APP_NAME', $config['app_name'] ?? 'Dream Team');

// Database connection function
function getDbConnection()
{
    try {
        // Check if database is available first
        if (!isDatabaseAvailable()) {
            // Only redirect if we're not already on install.php or in an API call
            $current_script = basename($_SERVER['SCRIPT_NAME']);
            $is_api_call = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
            
            if ($current_script !== 'install.php' && !$is_api_call) {
                header('Location: install.php');
                exit;
            } else if ($is_api_call) {
                throw new Exception("Database not initialized");
            }
        }
        
        $db = new SQLite3(DB_FILE);
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Check if database is available and properly set up
function isDatabaseAvailable()
{
    try {
        if (!file_exists(DB_FILE)) {
            return false;
        }

        $db = new SQLite3(DB_FILE);

        // Check if users table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $tableExists = $result->fetchArray() !== false;

        if ($tableExists) {
            // Check and add missing columns for existing databases
            $result = $db->query("PRAGMA table_info(users)");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }

            // Add substitutes column if missing
            if (!in_array('substitutes', $columns)) {
                $db->exec('ALTER TABLE users ADD COLUMN substitutes TEXT DEFAULT "[]"');
            }

            // Add max_players column if missing
            if (!in_array('max_players', $columns)) {
                $db->exec('ALTER TABLE users ADD COLUMN max_players INTEGER DEFAULT 23');
            }

            // Add user_plan column if missing
            if (!in_array('user_plan', $columns)) {
                $db->exec('ALTER TABLE users ADD COLUMN user_plan TEXT DEFAULT "free"');
            }

            // Add plan_expires_at column if missing
            if (!in_array('plan_expires_at', $columns)) {
                $db->exec('ALTER TABLE users ADD COLUMN plan_expires_at DATETIME');
            }

            // All table creation is now handled in install.php
        }

        $db->close();
        return $tableExists;
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