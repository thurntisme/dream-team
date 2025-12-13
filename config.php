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

            // Add user_plan column if missing
            if (!in_array('user_plan', $columns)) {
                $db->exec('ALTER TABLE users ADD COLUMN user_plan TEXT DEFAULT "' . DEFAULT_USER_PLAN . '"');
            }

            // Add plan_expires_at column if missing
            if (!in_array('plan_expires_at', $columns)) {
                $db->exec('ALTER TABLE users ADD COLUMN plan_expires_at DATETIME');
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

            // Create young_players table if it doesn't exist
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='young_players'");
            $youngPlayersTableExists = $result->fetchArray() !== false;

            if (!$youngPlayersTableExists) {
                $db->exec('CREATE TABLE young_players (
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
            }

            // Create young_player_bids table if it doesn't exist
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='young_player_bids'");
            $youngPlayerBidsTableExists = $result->fetchArray() !== false;

            if (!$youngPlayerBidsTableExists) {
                $db->exec('CREATE TABLE young_player_bids (
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