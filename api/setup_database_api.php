<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/league_functions.php';
require_once __DIR__ . '/../database/seed.php';

header('Content-Type: application/json');

$logs = [];
$ok = true;

try {
    if (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';charset=utf8mb4';
        $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        if (!empty(MYSQL_DB)) {
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . MYSQL_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    }
    $db = getDbConnection();

    // Create core tables needed for seeding
    $coreOk = true;
    if (DB_DRIVER === 'mysql') {
        $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            club_name VARCHAR(255) NULL,
            formation VARCHAR(20) DEFAULT "4-4-2",
            team TEXT,
            budget BIGINT DEFAULT ' . DEFAULT_BUDGET . ',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    } else {
        $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            club_name TEXT,
            formation TEXT DEFAULT "4-4-2",
            team TEXT,
            budget INTEGER DEFAULT ' . DEFAULT_BUDGET . ',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS shop_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            price INTEGER NOT NULL,
            effect_type TEXT NOT NULL,
            effect_value TEXT NOT NULL,
            category TEXT NOT NULL,
            icon TEXT DEFAULT "package",
            duration INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $coreOk = $coreOk && $db->exec('CREATE TABLE IF NOT EXISTS young_players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            club_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            age INTEGER NOT NULL,
            position TEXT NOT NULL,
            potential_rating INTEGER NOT NULL,
            current_rating INTEGER NOT NULL,
            development_stage TEXT DEFAULT "academy",
            contract_years INTEGER DEFAULT 3,
            value INTEGER NOT NULL,
            training_focus TEXT DEFAULT "balanced",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }
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

    // Seed shop items
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

    // Seed demo clubs
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

    $db->close();
} catch (Throwable $e) {
    $ok = false;
    $logs[] = ['type' => 'error', 'message' => 'Setup failed: ' . $e->getMessage()];
}

echo json_encode(['ok' => $ok, 'logs' => $logs]);
