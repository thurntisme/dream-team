<?php
session_start();
header('Content-Type: application/json');

require_once 'config/config.php';
require_once 'config/constants.php';

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

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
    if ($stmt !== false) {
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $password, SQLITE3_TEXT);
    }

    if ($stmt !== false && $stmt->execute()) {
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
    if ($stmt !== false) {
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
    } else {
        $result = false;
    }
    $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;

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

        $stmt = $db->prepare(DB_DRIVER === 'mysql' ? 'UPDATE users SET last_login = NOW() WHERE id = :id' : 'UPDATE users SET last_login = datetime("now") WHERE id = :id');
        if ($stmt !== false) {
            $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
            $stmt->execute();
        }

        echo json_encode([
            'success' => true,
            'session_expires' => $session_expire_time,
            'expires_in' => 24 * 60 * 60 // seconds
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} elseif ($action === 'extend_session') {


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
