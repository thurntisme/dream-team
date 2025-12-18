<?php
/**
 * Test script to verify debug logging 404 behavior
 * This file can be deleted after testing
 */

require_once 'includes/debug_logger.php';

echo "<h1>Debug Logging Test</h1>";

$logger = DebugLogger::getInstance();

echo "<h2>Current Status:</h2>";
echo "<p><strong>Debug Enabled:</strong> " . ($logger->isEnabled() ? 'YES' : 'NO') . "</p>";

if ($logger->isEnabled()) {
    echo "<p style='color: green;'>✅ Debug logging is ENABLED</p>";
    echo "<p>The debug logs page should be accessible at: <a href='debug_logs.php'>debug_logs.php</a></p>";
    echo "<p>The navigation should show a 'Debug Logs' link when logged in.</p>";
} else {
    echo "<p style='color: red;'>❌ Debug logging is DISABLED</p>";
    echo "<p>The debug logs page should show a 404 error: <a href='debug_logs.php'>debug_logs.php</a></p>";
    echo "<p>The navigation should NOT show a 'Debug Logs' link.</p>";
}

echo "<h2>Environment Check:</h2>";
$envFile = '.env';
if (file_exists($envFile)) {
    echo "<p>✅ .env file exists</p>";
    $content = file_get_contents($envFile);
    if (strpos($content, 'DEBUG_LOG') !== false) {
        preg_match('/DEBUG_LOG\s*=\s*(.*)/', $content, $matches);
        $value = isset($matches[1]) ? trim($matches[1], '"\'') : 'not found';
        echo "<p><strong>DEBUG_LOG value:</strong> '$value'</p>";
        
        if (strtolower($value) === 'true') {
            echo "<p style='color: green;'>✅ DEBUG_LOG is set to 'true'</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ DEBUG_LOG is set to '$value' (not 'true')</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ DEBUG_LOG not found in .env file</p>";
    }
} else {
    echo "<p style='color: red;'>❌ .env file does not exist</p>";
}

echo "<h2>Test Instructions:</h2>";
echo "<ol>";
echo "<li>To <strong>enable</strong> debug logging: Set <code>DEBUG_LOG=true</code> in .env file</li>";
echo "<li>To <strong>disable</strong> debug logging: Set <code>DEBUG_LOG=false</code> or remove the line</li>";
echo "<li>Test the debug_logs.php page after each change</li>";
echo "<li>Check if the navigation link appears/disappears when logged in</li>";
echo "</ol>";

echo "<h2>Quick Actions:</h2>";
echo "<p><a href='debug_logs.php' target='_blank'>🔗 Test Debug Logs Page</a></p>";
echo "<p><a href='team.php'>🔗 Go to Team Page (to test navigation)</a></p>";

echo "<hr>";
echo "<p><small>This test file can be deleted after testing: test_debug_404.php</small></p>";
?>