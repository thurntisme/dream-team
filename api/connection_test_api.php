<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$response = [
    'ok' => false,
    'driver' => DB_DRIVER
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

    $db = new DBAdapter(DB_DRIVER, $adapterConfig);
    $res = $db->query('SELECT 1');
    $response['ok'] = $res !== false;

    if (DB_DRIVER === 'mysql') {
        $response['details'] = [
            'host' => MYSQL_HOST,
            'port' => MYSQL_PORT,
            'db' => MYSQL_DB,
            'user' => MYSQL_USER
        ];
    } else {
        $response['details'] = [
            'db_file' => DB_FILE
        ];
    }

    $db->close();
} catch (Throwable $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
