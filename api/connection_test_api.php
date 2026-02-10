<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$response = [
    'ok' => false
];

try {
    $adapterConfig = [
        'db_file' => DB_FILE,
        'mysql_host' => MYSQL_HOST,
        'mysql_port' => MYSQL_PORT,
        'mysql_db' => MYSQL_DB,
        'mysql_user' => MYSQL_USER,
        'mysql_password' => MYSQL_PASSWORD
    ];

    $db = new DBAdapter('mysql', $adapterConfig);
    $res = $db->query('SELECT 1');
    $response['ok'] = $res !== false;

    $response['details'] = [
        'host' => MYSQL_HOST,
        'port' => MYSQL_PORT,
        'db' => MYSQL_DB,
        'user' => MYSQL_USER
    ];

    $db->close();
} catch (Throwable $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
