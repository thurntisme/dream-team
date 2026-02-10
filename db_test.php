<?php
require_once 'config/config.php';
header('Content-Type: text/plain');
try {
    $db = getDbConnection();
    echo "connection=ok\n";
} catch (Throwable $e) {
    echo "connection=fail " . $e->getMessage() . "\n";
    exit;
}
try {
    $res = $db->query('SELECT 1');
    echo "query=ok\n";
} catch (Throwable $e) {
    echo "query=fail " . $e->getMessage() . "\n";
}
try {
    $exists = false;
    if (DB_DRIVER === 'sqlite') {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $exists = $result && $result->fetchArray() !== false;
    } else {
        $result = $db->query("SELECT 1 FROM users LIMIT 1");
        $exists = $result !== false;
    }
    echo "users_table=" . ($exists ? "ok" : "missing") . "\n";
} catch (Throwable $e) {
    echo "users_table=missing\n";
}
try {
    $db->close();
} catch (Throwable $e) {
}
