<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'constants.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'redirect' => 'install.php']);
    exit;
}

try {
    $db = getDbConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'redirect' => 'install.php']);
    exit;
}

$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    club_name TEXT,
    formation TEXT,
    team TEXT,
    budget INTEGER DEFAULT ' . DEFAULT_BUDGET . '
)');

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $password, SQLITE3_TEXT);

    if ($stmt->execute()) {
        $_SESSION['user_id'] = $db->lastInsertRowID();
        $_SESSION['user_name'] = $name;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
    }
} elseif ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['club_name'] = $user['club_name'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}
?>