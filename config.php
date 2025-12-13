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
define('DB_FILE', $config['db_file'] ?? 'dreamteam.db');
define('APP_NAME', $config['app_name'] ?? 'Dream Team');

// Database connection function
function getDbConnection()
{
    try {
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

            // Create user_settings table if it doesn't exist
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_settings'");
            $settingsTableExists = $result->fetchArray() !== false;

            if (!$settingsTableExists) {
                $db->exec('CREATE TABLE user_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    setting_key TEXT NOT NULL,
                    setting_value TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                    UNIQUE(user_id, setting_key)
                )');
            }
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