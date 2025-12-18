<?php
/**
 * Debug Logger System
 * Handles debug logging when DEBUG_LOG=true in .env file
 */

class DebugLogger
{
    private static $instance = null;
    private $isEnabled = false;
    private $logFile = '';
    private $logDir = '';
    private $maxFileSize = 10485760; // 10MB
    private $maxFiles = 5;

    private function __construct()
    {
        $this->loadEnvironment();
        $this->setupLogDirectory();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnvironment()
    {
        $envFile = __DIR__ . '/../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    
                    if ($key === 'DEBUG_LOG') {
                        $this->isEnabled = strtolower($value) === 'true';
                    }
                }
            }
        }
    }

    /**
     * Setup log directory and file
     */
    private function setupLogDirectory()
    {
        $this->logDir = __DIR__ . '/../logs';
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $this->logFile = $this->logDir . '/debug_' . date('Y-m-d') . '.log';
    }

    /**
     * Check if debug logging is enabled
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Log debug information
     */
    public function log($level, $message, $context = [])
    {
        if (!$this->isEnabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $microtime = sprintf('%.3f', microtime(true) - floor(microtime(true)));
        $memory = $this->formatBytes(memory_get_usage(true));
        $peakMemory = $this->formatBytes(memory_get_peak_usage(true));
        
        // Get caller information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $this->getCallerInfo($backtrace);
        
        // Format context data
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        
        // Create log entry
        $logEntry = sprintf(
            "[%s%s] [%s] [%s] [Memory: %s/%s] %s%s\n",
            $timestamp,
            $microtime,
            strtoupper($level),
            $caller,
            $memory,
            $peakMemory,
            $message,
            $contextStr
        );
        
        $this->writeToFile($logEntry);
    }

    /**
     * Get caller information from backtrace
     */
    private function getCallerInfo($backtrace)
    {
        // Skip the log method itself
        $caller = $backtrace[1] ?? $backtrace[0];
        
        $file = basename($caller['file'] ?? 'unknown');
        $line = $caller['line'] ?? 0;
        $function = $caller['function'] ?? 'unknown';
        
        if (isset($caller['class'])) {
            $function = $caller['class'] . '::' . $function;
        }
        
        return "{$file}:{$line} {$function}()";
    }

    /**
     * Write log entry to file
     */
    private function writeToFile($logEntry)
    {
        // Check file size and rotate if necessary
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateLogFile();
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log files when they get too large
     */
    private function rotateLogFile()
    {
        $baseFile = $this->logDir . '/debug_' . date('Y-m-d');
        
        // Move existing numbered files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $baseFile . '.' . $i . '.log';
            $newFile = $baseFile . '.' . ($i + 1) . '.log';
            
            if (file_exists($oldFile)) {
                if ($i + 1 > $this->maxFiles) {
                    unlink($oldFile); // Delete oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current file to .1
        if (file_exists($this->logFile)) {
            rename($this->logFile, $baseFile . '.1.log');
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Log info level message
     */
    public function info($message, $context = [])
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log debug level message
     */
    public function debug($message, $context = [])
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log warning level message
     */
    public function warning($message, $context = [])
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error level message
     */
    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log SQL queries
     */
    public function sql($query, $params = [], $executionTime = null)
    {
        if (!$this->isEnabled) {
            return;
        }

        $context = [];
        if (!empty($params)) {
            $context['params'] = $params;
        }
        if ($executionTime !== null) {
            $context['execution_time'] = $executionTime . 'ms';
        }

        $this->log('sql', $query, $context);
    }

    /**
     * Log HTTP requests
     */
    public function request($method, $url, $data = [], $responseCode = null)
    {
        if (!$this->isEnabled) {
            return;
        }

        $context = [
            'method' => $method,
            'url' => $url
        ];
        
        if (!empty($data)) {
            $context['data'] = $data;
        }
        
        if ($responseCode !== null) {
            $context['response_code'] = $responseCode;
        }

        $this->log('request', "HTTP Request: {$method} {$url}", $context);
    }

    /**
     * Log performance metrics
     */
    public function performance($operation, $startTime, $context = [])
    {
        if (!$this->isEnabled) {
            return;
        }

        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $context['execution_time'] = round($executionTime, 2) . 'ms';
        
        $this->log('performance', "Performance: {$operation}", $context);
    }

    /**
     * Log user actions
     */
    public function userAction($userId, $action, $context = [])
    {
        if (!$this->isEnabled) {
            return;
        }

        $context['user_id'] = $userId;
        $this->log('user_action', "User Action: {$action}", $context);
    }

    /**
     * Get log file path
     */
    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * Get all log files for today
     */
    public function getLogFiles()
    {
        $pattern = $this->logDir . '/debug_' . date('Y-m-d') . '*.log';
        $files = glob($pattern);
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files;
    }

    /**
     * Clear all log files
     */
    public function clearLogs()
    {
        $files = glob($this->logDir . '/debug_*.log');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get log statistics
     */
    public function getStats()
    {
        $files = $this->getLogFiles();
        $totalSize = 0;
        $totalLines = 0;
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $totalSize += filesize($file);
                $totalLines += count(file($file));
            }
        }
        
        return [
            'files' => count($files),
            'total_size' => $this->formatBytes($totalSize),
            'total_lines' => $totalLines,
            'enabled' => $this->isEnabled
        ];
    }
}

// Global helper functions
if (!function_exists('debug_log')) {
    function debug_log($message, $context = []) {
        DebugLogger::getInstance()->debug($message, $context);
    }
}

if (!function_exists('debug_info')) {
    function debug_info($message, $context = []) {
        DebugLogger::getInstance()->info($message, $context);
    }
}

if (!function_exists('debug_warning')) {
    function debug_warning($message, $context = []) {
        DebugLogger::getInstance()->warning($message, $context);
    }
}

if (!function_exists('debug_error')) {
    function debug_error($message, $context = []) {
        DebugLogger::getInstance()->error($message, $context);
    }
}

if (!function_exists('debug_sql')) {
    function debug_sql($query, $params = [], $executionTime = null) {
        DebugLogger::getInstance()->sql($query, $params, $executionTime);
    }
}

if (!function_exists('debug_performance')) {
    function debug_performance($operation, $startTime, $context = []) {
        DebugLogger::getInstance()->performance($operation, $startTime, $context);
    }
}

if (!function_exists('debug_user_action')) {
    function debug_user_action($userId, $action, $context = []) {
        DebugLogger::getInstance()->userAction($userId, $action, $context);
    }
}