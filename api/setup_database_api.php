<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/league_functions.php';
require_once __DIR__ . '/../database/seed.php';

header('Content-Type: application/json');

$logs = [];
$ok = true;
$mode = $_POST['mode'] ?? 'all';
$logs[] = ['type' => 'detail', 'message' => 'Seed mode: ' . $mode];

try {
    $logs[] = ['type' => 'detail', 'message' => 'Checking MySQL connection'];
    $dsn = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';charset=utf8mb4';
    $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $logs[] = ['type' => 'info', 'message' => 'MySQL connection OK'];
    if (!empty(MYSQL_DB)) {
        $logs[] = ['type' => 'detail', 'message' => 'Ensuring database "' . MYSQL_DB . '" exists'];
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . MYSQL_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $logs[] = ['type' => 'info', 'message' => 'Database ensured: ' . MYSQL_DB];
    }
    $db = getDbConnection();
    $logs[] = ['type' => 'info', 'message' => 'Database adapter initialized'];

    // Create core tables needed for seeding
    $coreOk = true;
    $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            uuid CHAR(16) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS user_club (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_uuid CHAR(16) NOT NULL,
            club_name VARCHAR(255) NULL,
            formation VARCHAR(20) DEFAULT "4-4-2",
            team TEXT,
            budget BIGINT DEFAULT ' . DEFAULT_BUDGET . ',
            max_players INT DEFAULT 23,
            fans INT DEFAULT 5000,
            club_exp INT DEFAULT 0,
            club_level INT DEFAULT 1,
            user_plan VARCHAR(20) DEFAULT "free",
            plan_expires_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_club_user_uuid (user_uuid)
        )');
    $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS shop_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            price BIGINT NOT NULL,
            effect_type VARCHAR(50) NOT NULL,
            effect_value TEXT NOT NULL,
            category VARCHAR(50) NOT NULL,
            icon VARCHAR(50) DEFAULT "package",
            duration INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS young_players (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            age INT NOT NULL,
            position VARCHAR(10) NOT NULL,
            potential_rating INT NOT NULL,
            current_rating INT NOT NULL,
            development_stage VARCHAR(20) DEFAULT "academy",
            contract_years INT DEFAULT 3,
            value BIGINT NOT NULL,
            training_focus VARCHAR(50) DEFAULT "balanced",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    if ($coreOk) {
        $logs[] = ['type' => 'info', 'message' => 'Create tables successfully'];
    } else {
        $ok = false;
        $logs[] = ['type' => 'error', 'message' => 'Failed creating core tables: ' . $db->lastErrorMsg()];
    }

    // League tables (indexes/relations)
    try {
        createLeagueTables($db);
        $logs[] = ['type' => 'info', 'message' => 'League tables ensured'];
    } catch (Throwable $e) {
        $ok = false;
        $logs[] = ['type' => 'error', 'message' => 'League tables error: ' . $e->getMessage()];
    }

    // Create admin if provided
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    if ($adminName !== '' || $adminEmail !== '' || $adminPassword !== '') {
        if ($adminName === '' || $adminEmail === '' || $adminPassword === '') {
            $ok = false;
            $logs[] = ['type' => 'error', 'message' => 'Admin name, email, and password are required'];
        } else {
            try {
                $check = $db->prepare('SELECT COUNT(*) as c FROM users WHERE email = :email');
                if ($check) {
                    $check->bindValue(':email', $adminEmail, SQLITE3_TEXT);
                    $res = $check->execute();
                    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                    if ((int)($row['c'] ?? 0) > 0) {
                        $logs[] = ['type' => 'detail', 'message' => 'Admin already exists: ' . $adminEmail];
                    } else {
                        $stmt = $db->prepare('INSERT INTO users (name, email, password, uuid) VALUES (:name, :email, :password, :uuid)');
                        if ($stmt) {
                            $stmt->bindValue(':name', $adminName, SQLITE3_TEXT);
                            $stmt->bindValue(':email', $adminEmail, SQLITE3_TEXT);
                            $stmt->bindValue(':password', password_hash($adminPassword, PASSWORD_DEFAULT), SQLITE3_TEXT);
                            $stmt->bindValue(':uuid', generateUUID(), SQLITE3_TEXT);
                            $ins = $stmt->execute();
                            if ($ins) {
                                $adminId = $db->lastInsertRowID();
                                $stmtUuid = $db->prepare('SELECT uuid FROM users WHERE id = :id');
                                $stmtUuid->bindValue(':id', (int)$adminId, SQLITE3_INTEGER);
                                $resUuid = $stmtUuid->execute();
                                $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
                                $uuidVal = $rowUuid['uuid'] ?? null;
                                $stmtClub = $db->prepare('INSERT INTO user_club (user_uuid, club_name, formation, team, budget, max_players) VALUES (:user_uuid, :club, :form, :team, :budget, 23)');
                                if ($stmtClub) {
                                    $stmtClub->bindValue(':user_uuid', $uuidVal, SQLITE3_TEXT);
                                    $stmtClub->bindValue(':club', $adminName . ' FC', SQLITE3_TEXT);
                                    $stmtClub->bindValue(':form', '4-4-2', SQLITE3_TEXT);
                                    $stmtClub->bindValue(':team', '[]', SQLITE3_TEXT);
                                    $stmtClub->bindValue(':budget', DEFAULT_BUDGET, SQLITE3_INTEGER);
                                    $stmtClub->execute();
                                }
                                $logs[] = ['type' => 'info', 'message' => 'Admin user created'];
                            } else {
                                $ok = false;
                                $logs[] = ['type' => 'error', 'message' => 'Failed to create admin user: ' . $db->lastErrorMsg()];
                            }
                        } else {
                            $ok = false;
                            $logs[] = ['type' => 'error', 'message' => 'Failed to prepare admin insert: ' . $db->lastErrorMsg()];
                        }
                    }
                } else {
                    $ok = false;
                    $logs[] = ['type' => 'error', 'message' => 'Failed to prepare admin uniqueness check: ' . $db->lastErrorMsg()];
                }
            } catch (Throwable $e) {
                $ok = false;
                $logs[] = ['type' => 'error', 'message' => 'Admin creation error: ' . $e->getMessage()];
            }
        }
    } else {
        $logs[] = ['type' => 'detail', 'message' => 'Admin inputs not provided; skipping admin creation'];
    }

    // Seed shop items
    if ($mode === 'shop' || $mode === 'all') {
        try {
            ob_start();
            $seedShopOk = seedShopItems();
            $out = trim(ob_get_clean());
            if ($seedShopOk) {
                $logs[] = ['type' => 'info', 'message' => 'Insert shop items successfully'];
            } else {
                // Could already exist; still log output
                $logs[] = ['type' => 'info', 'message' => 'Shop items seeding skipped or failed'];
            }
            if ($out !== '') {
                foreach (explode("\n", $out) as $line) {
                    $logs[] = ['type' => 'detail', 'message' => $line];
                }
            }
        } catch (Throwable $e) {
            $ok = false;
            $logs[] = ['type' => 'error', 'message' => 'Shop items error: ' . $e->getMessage()];
        }
    }

    // Seed demo clubs
    if ($mode === 'clubs' || $mode === 'all') {
        try {
            ob_start();
            $seedClubsOk = seedFakeClubs();
            $out = trim(ob_get_clean());
            if ($seedClubsOk) {
                $logs[] = ['type' => 'info', 'message' => 'Create clubs successfully'];
            } else {
                $logs[] = ['type' => 'info', 'message' => 'Club seeding skipped or failed'];
            }
            if ($out !== '') {
                foreach (explode("\n", $out) as $line) {
                    $logs[] = ['type' => 'detail', 'message' => $line];
                }
            }
        } catch (Throwable $e) {
            $ok = false;
            $logs[] = ['type' => 'error', 'message' => 'Clubs error: ' . $e->getMessage()];
        }
    }

    $db->close();
} catch (Throwable $e) {
    $ok = false;
    $logs[] = ['type' => 'error', 'message' => 'Setup failed: ' . $e->getMessage()];
}

echo json_encode(['ok' => $ok, 'logs' => $logs]);
