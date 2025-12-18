# Debug Logging System

## Overview
The Debug Logging System provides comprehensive logging capabilities for development and debugging purposes. It logs application events, SQL queries, performance metrics, user actions, and errors to files when enabled.

## Features

### 1. **Environment-Based Activation**
- Controlled via `.env` file
- Set `DEBUG_LOG=true` to enable logging
- Set `DEBUG_LOG=false` to disable logging
- No code changes required to toggle logging

### 2. **Multiple Log Levels**
- **DEBUG**: Detailed debugging information
- **INFO**: General informational messages
- **WARNING**: Warning messages for potential issues
- **ERROR**: Error messages for failures
- **SQL**: Database query logging
- **PERFORMANCE**: Performance metrics and timing
- **USER_ACTION**: User activity tracking

### 3. **Rich Log Information**
Each log entry includes:
- Timestamp with microseconds
- Log level
- File, line number, and function name
- Memory usage (current and peak)
- Message and context data
- JSON-encoded context for structured data

### 4. **Automatic File Management**
- Daily log files (`debug_YYYY-MM-DD.log`)
- Automatic file rotation when size exceeds 10MB
- Keeps up to 5 rotated files per day
- Old files automatically deleted

### 5. **Web-Based Log Viewer**
- View logs in browser at `/debug_logs.php`
- Filter by log level
- Search functionality
- Pagination for large log files
- Color-coded log levels
- Real-time refresh
- Clear all logs functionality

## Setup

### 1. Enable Debug Logging

Edit your `.env` file:
```env
DEBUG_LOG=true
```

### 2. Ensure Logs Directory Exists
The system automatically creates the `logs/` directory, but you can create it manually:
```bash
mkdir logs
chmod 755 logs
```

### 3. Access Log Viewer
Navigate to: `http://yourdomain.com/debug_logs.php`

## Usage

### Basic Logging

```php
// Debug level
debug_log("This is a debug message");
debug_log("User data loaded", ['user_id' => 123, 'username' => 'john']);

// Info level
debug_info("Application started");
debug_info("User logged in", ['user_id' => 123]);

// Warning level
debug_warning("Low memory warning");
debug_warning("API rate limit approaching", ['remaining' => 10]);

// Error level
debug_error("Database connection failed");
debug_error("Payment processing error", ['order_id' => 456, 'error' => 'timeout']);
```

### SQL Query Logging

```php
$query = "SELECT * FROM users WHERE id = :id";
$params = ['id' => 123];
$startTime = microtime(true);

// Execute query...

$executionTime = (microtime(true) - $startTime) * 1000; // Convert to ms
debug_sql($query, $params, $executionTime);
```

### Performance Tracking

```php
$startTime = microtime(true);

// Perform operation...

debug_performance("Team data processing", $startTime, [
    'user_id' => 123,
    'team_value' => 50000000,
    'player_count' => 11
]);
```

### User Action Tracking

```php
debug_user_action($userId, "Purchased player", [
    'player_name' => 'John Doe',
    'cost' => 5000000,
    'position' => 'ST'
]);

debug_user_action($userId, "Generated recommendations", [
    'cost' => 2000000,
    'recommendations' => 10
]);
```

### Using the Logger Class Directly

```php
$logger = DebugLogger::getInstance();

// Check if logging is enabled
if ($logger->isEnabled()) {
    $logger->info("Custom log message", ['key' => 'value']);
}

// Get log file path
$logFile = $logger->getLogFile();

// Get statistics
$stats = $logger->getStats();
print_r($stats);
// Output:
// Array (
//     [files] => 2
//     [total_size] => "1.5 MB"
//     [total_lines] => 1234
//     [enabled] => true
// )
```

## Log Format

### Standard Log Entry
```
[2025-12-18 10:30:45.123] [INFO] [team.php:45 processTeamData()] [Memory: 2.5 MB/3.2 MB] User logged in | Context: {"user_id":123,"username":"john"}
```

### Components
1. **Timestamp**: `[2025-12-18 10:30:45.123]`
2. **Level**: `[INFO]`
3. **Location**: `[team.php:45 processTeamData()]`
4. **Memory**: `[Memory: 2.5 MB/3.2 MB]` (current/peak)
5. **Message**: `User logged in`
6. **Context**: `| Context: {"user_id":123,"username":"john"}`

## Log Viewer Features

### Filtering
- **By Level**: Filter logs by DEBUG, INFO, WARNING, ERROR, SQL, PERFORMANCE
- **By Search**: Search for specific text in log entries
- **By File**: View different log files (current day and rotated files)

### Display
- **Color Coding**:
  - 🟢 Green: DEBUG
  - 🔵 Blue: INFO
  - 🟡 Yellow: WARNING
  - 🔴 Red: ERROR
  - 🟣 Purple: SQL
  - 🔷 Cyan: PERFORMANCE

### Actions
- **Refresh**: Reload current logs
- **Clear**: Delete all log files
- **Auto-refresh**: Automatically refreshes every 30 seconds

### Statistics
- Total log files
- Total size of all logs
- Total number of log entries
- Current status (enabled/disabled)

## Best Practices

### 1. Use Appropriate Log Levels
```php
// ✅ Good
debug_info("User logged in successfully");
debug_warning("API rate limit at 80%");
debug_error("Payment gateway timeout");

// ❌ Bad
debug_error("User logged in"); // Not an error
debug_info("Critical database failure"); // Should be error
```

### 2. Include Relevant Context
```php
// ✅ Good
debug_error("Payment failed", [
    'user_id' => $userId,
    'amount' => $amount,
    'gateway' => 'stripe',
    'error_code' => $errorCode
]);

// ❌ Bad
debug_error("Payment failed"); // No context
```

### 3. Log Performance-Critical Operations
```php
$startTime = microtime(true);

// Complex operation
$result = processLargeDataset($data);

debug_performance("Large dataset processing", $startTime, [
    'records' => count($data),
    'result_count' => count($result)
]);
```

### 4. Track User Actions
```php
// Track important user actions
debug_user_action($userId, "Team saved", [
    'formation' => '4-4-2',
    'players' => 11,
    'budget_used' => 45000000
]);
```

### 5. Don't Log Sensitive Data
```php
// ❌ Bad - Logs password
debug_info("User login attempt", [
    'username' => $username,
    'password' => $password // NEVER LOG PASSWORDS
]);

// ✅ Good
debug_info("User login attempt", [
    'username' => $username,
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);
```

## Performance Considerations

### Impact When Enabled
- **Minimal CPU overhead**: ~1-2% for typical operations
- **Disk I/O**: Writes are buffered and use `FILE_APPEND`
- **Memory**: ~100KB for logger instance
- **File locking**: Uses `LOCK_EX` to prevent corruption

### Impact When Disabled
- **Zero overhead**: All logging functions return immediately
- **No file operations**: No disk I/O when disabled
- **No memory allocation**: Logger not initialized

### Optimization Tips
1. **Disable in production**: Set `DEBUG_LOG=false` in production
2. **Use context wisely**: Don't log huge arrays or objects
3. **Rotate regularly**: Old logs are automatically cleaned up
4. **Monitor disk space**: Check logs directory size periodically

## File Management

### Automatic Rotation
- Files rotate when they exceed 10MB
- Up to 5 rotated files kept per day
- Format: `debug_YYYY-MM-DD.N.log` (N = 1-5)
- Oldest files automatically deleted

### Manual Cleanup
```php
$logger = DebugLogger::getInstance();
$logger->clearLogs(); // Delete all log files
```

Or via web interface:
1. Go to `/debug_logs.php`
2. Click "Clear" button
3. Confirm deletion

### Log File Locations
```
logs/
├── debug_2025-12-18.log       (Current log)
├── debug_2025-12-18.1.log     (Rotated)
├── debug_2025-12-18.2.log     (Rotated)
├── debug_2025-12-17.log       (Previous day)
└── debug_2025-12-17.1.log     (Previous day rotated)
```

## Security Considerations

### 1. Protect Log Files
Add to `.htaccess`:
```apache
<FilesMatch "\.log$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### 2. Restrict Log Viewer Access
Add authentication check to `debug_logs.php`:
```php
// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}
```

### 3. Disable in Production
Always set in production `.env`:
```env
DEBUG_LOG=false
```

### 4. Sanitize Log Data
```php
// Remove sensitive data before logging
$safeContext = [
    'user_id' => $userId,
    'email' => maskEmail($email), // user@example.com -> u***@example.com
    'ip' => $_SERVER['REMOTE_ADDR']
];
debug_info("User action", $safeContext);
```

## Troubleshooting

### Logs Not Appearing

1. **Check .env file**:
   ```env
   DEBUG_LOG=true
   ```

2. **Check logs directory permissions**:
   ```bash
   chmod 755 logs
   ```

3. **Check if logger is loaded**:
   ```php
   var_dump(DebugLogger::getInstance()->isEnabled());
   ```

### Log Viewer Not Working

1. **Check if logged in**: Log viewer requires authentication
2. **Check file permissions**: Ensure web server can read log files
3. **Check browser console**: Look for JavaScript errors

### Performance Issues

1. **Disable verbose logging**: Reduce log level in production
2. **Clear old logs**: Use the clear function regularly
3. **Check disk space**: Ensure sufficient space available

### File Rotation Not Working

1. **Check directory permissions**: Web server needs write access
2. **Check disk space**: Ensure space for rotated files
3. **Check max file size**: Default is 10MB, adjust if needed

## Advanced Configuration

### Customize Log File Size
```php
// In debug_logger.php
private $maxFileSize = 20971520; // 20MB instead of 10MB
```

### Customize Number of Rotated Files
```php
// In debug_logger.php
private $maxFiles = 10; // Keep 10 rotated files instead of 5
```

### Custom Log Directory
```php
// In debug_logger.php setupLogDirectory()
$this->logDir = '/var/log/dreamteam'; // Custom directory
```

### Add Custom Log Levels
```php
// Add to DebugLogger class
public function critical($message, $context = [])
{
    $this->log('critical', $message, $context);
}

// Use it
debug_critical("System failure", ['component' => 'database']);
```

## Integration Examples

### Example 1: API Endpoint
```php
<?php
require_once 'includes/debug_logger.php';

$startTime = microtime(true);
debug_info("API request started", [
    'endpoint' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD']
]);

try {
    // Process request
    $result = processApiRequest();
    
    debug_performance("API request", $startTime, [
        'endpoint' => $_SERVER['REQUEST_URI'],
        'status' => 'success'
    ]);
    
    echo json_encode($result);
} catch (Exception $e) {
    debug_error("API request failed", [
        'endpoint' => $_SERVER['REQUEST_URI'],
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
```

### Example 2: Database Operations
```php
function executeQuery($query, $params = [])
{
    $startTime = microtime(true);
    
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $result = $stmt->execute();
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        debug_sql($query, $params, $executionTime);
        
        return $result;
    } catch (Exception $e) {
        debug_error("SQL query failed", [
            'query' => $query,
            'params' => $params,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
```

### Example 3: User Actions
```php
function saveTeam($userId, $teamData)
{
    $startTime = microtime(true);
    
    debug_user_action($userId, "Saving team", [
        'formation' => $teamData['formation'],
        'players' => count($teamData['players'])
    ]);
    
    try {
        // Save team logic
        $result = saveTeamToDatabase($teamData);
        
        debug_performance("Team save operation", $startTime, [
            'user_id' => $userId,
            'success' => true
        ]);
        
        return $result;
    } catch (Exception $e) {
        debug_error("Team save failed", [
            'user_id' => $userId,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
```

## Support

For issues or questions:
1. Check if `DEBUG_LOG=true` in `.env`
2. Verify logs directory permissions
3. Check log viewer at `/debug_logs.php`
4. Review log files in `logs/` directory

## License

This debug logging system is part of the Dream Team application and follows the same license terms.
