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
    budget INTEGER DEFAULT ' . DEFAULT_BUDGET . ',
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS user_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    setting_key TEXT NOT NULL,
    setting_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE(user_id, setting_key)
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
        // Clear any existing session data
        session_unset();

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session expiration time (24 hours from now)
        $session_expire_time = time() + (24 * 60 * 60);

        // Store user session data
        $_SESSION['user_id'] = $db->lastInsertRowID();
        $_SESSION['user_name'] = $name;
        $_SESSION['club_name'] = null; // New users don't have club names yet
        $_SESSION['login_time'] = time();
        $_SESSION['expire_time'] = $session_expire_time;
        $_SESSION['last_activity'] = time();

        echo json_encode([
            'success' => true,
            'session_expires' => $session_expire_time,
            'expires_in' => 24 * 60 * 60
        ]);
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
        // Clear all existing session data before setting new session
        session_unset();
        session_destroy();

        // Start a new session
        session_start();

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session expiration time (24 hours from now)
        $session_expire_time = time() + (24 * 60 * 60); // 24 hours

        // Store user session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['club_name'] = $user['club_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['expire_time'] = $session_expire_time;
        $_SESSION['last_activity'] = time();

        // Update user's last login time in database
        $stmt = $db->prepare('UPDATE users SET last_login = datetime("now") WHERE id = :id');
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'session_expires' => $session_expire_time,
            'expires_in' => 24 * 60 * 60 // seconds
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} elseif ($action === 'extend_session') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    // Extend session by 24 hours from now
    $new_expire_time = time() + (24 * 60 * 60);
    $_SESSION['expire_time'] = $new_expire_time;
    $_SESSION['last_activity'] = time();

    echo json_encode([
        'success' => true,
        'new_expire_time' => $new_expire_time,
        'message' => 'Session extended successfully'
    ]);
} elseif ($action === 'logout') {
    // Clear all session data
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
}
?>