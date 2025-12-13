<?php
// Database Connection Test Script
echo "<h2>Database Connection Test</h2>";

// Check PHP version
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";

// Check SQLite3 extension
if (extension_loaded('sqlite3')) {
    echo "<p><strong>SQLite3 Extension:</strong> ✅ Loaded</p>";
} else {
    echo "<p><strong>SQLite3 Extension:</strong> ❌ Not loaded</p>";
    exit;
}

// Test database path
$db_path = __DIR__ . '/database/dreamteam.db';
echo "<p><strong>Database Path:</strong> " . $db_path . "</p>";

// Check if database directory exists
$db_dir = dirname($db_path);
if (is_dir($db_dir)) {
    echo "<p><strong>Database Directory:</strong> ✅ Exists</p>";
} else {
    echo "<p><strong>Database Directory:</strong> ❌ Does not exist</p>";
    // Try to create it
    if (mkdir($db_dir, 0755, true)) {
        echo "<p><strong>Database Directory:</strong> ✅ Created successfully</p>";
    } else {
        echo "<p><strong>Database Directory:</strong> ❌ Failed to create</p>";
        exit;
    }
}

// Check directory permissions
if (is_writable($db_dir)) {
    echo "<p><strong>Directory Writable:</strong> ✅ Yes</p>";
} else {
    echo "<p><strong>Directory Writable:</strong> ❌ No</p>";
}

// Check if database file exists
if (file_exists($db_path)) {
    echo "<p><strong>Database File:</strong> ✅ Exists</p>";
    echo "<p><strong>File Size:</strong> " . filesize($db_path) . " bytes</p>";
    echo "<p><strong>File Permissions:</strong> " . substr(sprintf('%o', fileperms($db_path)), -4) . "</p>";
} else {
    echo "<p><strong>Database File:</strong> ❌ Does not exist</p>";
}

// Test database connection
try {
    echo "<h3>Testing Database Connection...</h3>";

    $db = new SQLite3($db_path);
    echo "<p><strong>Connection:</strong> ✅ Success</p>";

    // Test basic query
    $result = $db->query("SELECT sqlite_version()");
    if ($result) {
        $row = $result->fetchArray();
        echo "<p><strong>SQLite Version:</strong> " . $row[0] . "</p>";
    }

    // Check if users table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($result && $result->fetchArray()) {
        echo "<p><strong>Users Table:</strong> ✅ Exists</p>";

        // Count users
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetchArray();
            echo "<p><strong>User Count:</strong> " . $row['count'] . "</p>";
        }
    } else {
        echo "<p><strong>Users Table:</strong> ❌ Does not exist</p>";
    }

    // Test creating a simple table
    echo "<h3>Testing Table Creation...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT)";
    if ($db->exec($sql)) {
        echo "<p><strong>Test Table Creation:</strong> ✅ Success</p>";

        // Test insert
        $stmt = $db->prepare("INSERT INTO test_table (name) VALUES (?)");
        $stmt->bindValue(1, 'test_value', SQLITE3_TEXT);
        if ($stmt->execute()) {
            echo "<p><strong>Test Insert:</strong> ✅ Success</p>";
        } else {
            echo "<p><strong>Test Insert:</strong> ❌ Failed - " . $db->lastErrorMsg() . "</p>";
        }

        // Clean up test table
        $db->exec("DROP TABLE test_table");
    } else {
        echo "<p><strong>Test Table Creation:</strong> ❌ Failed - " . $db->lastErrorMsg() . "</p>";
    }

    $db->close();

} catch (Exception $e) {
    echo "<p><strong>Connection Error:</strong> ❌ " . $e->getMessage() . "</p>";
}

// Test config loading
echo "<h3>Testing Configuration...</h3>";
require_once 'config/config.php';

echo "<p><strong>DB_FILE Constant:</strong> " . DB_FILE . "</p>";

try {
    $db = getDbConnection();
    echo "<p><strong>getDbConnection():</strong> ✅ Success</p>";
    $db->close();
} catch (Exception $e) {
    echo "<p><strong>getDbConnection():</strong> ❌ " . $e->getMessage() . "</p>";
}

if (isDatabaseAvailable()) {
    echo "<p><strong>isDatabaseAvailable():</strong> ✅ True</p>";
} else {
    echo "<p><strong>isDatabaseAvailable():</strong> ❌ False</p>";
}

echo "<h3>Recommendations:</h3>";
echo "<ul>";
echo "<li>If database file doesn't exist, run install.php to create it</li>";
echo "<li>If directory is not writable, check file permissions (chmod 755 or 777)</li>";
echo "<li>If SQLite3 extension is not loaded, install php-sqlite3 package</li>";
echo "<li>If connection fails, check file paths and permissions</li>";
echo "</ul>";

echo "<p><a href='install.php'>Go to Install Page</a> | <a href='index.php'>Go to Login</a></p>";
?>