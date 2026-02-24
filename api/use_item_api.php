<?php
session_start();
require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../includes/player_functions.php';

header('Content-Type: application/json');

try {
    if (!isDatabaseAvailable()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database not available']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $action = $payload['action'] ?? '';
    $inventory_id = isset($payload['inventory_id']) ? (int)$payload['inventory_id'] : 0;
    $user_uuid = $_SESSION['user_uuid'] ?? null;

    if ($action !== 'open_pack' || $inventory_id <= 0 || !$user_uuid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $db = getDbConnection();
    $db->exec('START TRANSACTION');

    $stmt = $db->prepare('SELECT ui.id, ui.quantity, ui.item_id, si.effect_type, si.effect_value FROM user_inventory ui JOIN shop_items si ON ui.item_id = si.id WHERE ui.id = :id AND ui.user_uuid = :user_uuid AND ui.quantity > 0');
    if ($stmt === false) {
        throw new Exception('Failed to prepare inventory lookup');
    }
    $stmt->bindValue(':id', $inventory_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $res = $stmt->execute();
    $inv = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;

    if (!$inv) {
        throw new Exception('Item not found in your inventory');
    }
    if ($inv['effect_type'] !== 'player_pack') {
        throw new Exception('This item cannot be opened');
    }

    $effect = json_decode($inv['effect_value'], true) ?: [];
    $min = isset($effect['min_rating']) ? (int)$effect['min_rating'] : 60;
    $max = isset($effect['max_rating']) ? (int)$effect['max_rating'] : 90;
    $tier = isset($effect['tier']) ? strtolower((string)$effect['tier']) : '';

    $players = getDefaultPlayers();
    $eligible = array_values(array_filter($players, function ($p) use ($min, $max) {
        $r = (int)($p['rating'] ?? 0);
        return $r >= $min && $r <= $max;
    }));
    if (empty($eligible)) {
        throw new Exception('No eligible players found for this pack');
    }
    $picked = $eligible[array_rand($eligible)];
    if (!isset($picked['uuid']) || !$picked['uuid']) {
        $picked['uuid'] = uniqid('player_');
    }
    if (!isset($picked['value'])) {
        $picked['value'] = calculatePlayerValue($picked);
    }
    $picked = initializePlayerCondition($picked);

    // Determine if this pack should auto-assign to squad (elite/superstar/legend tiers)
    $autoAssignTiers = ['elite', 'superstar', 'legend'];
    $isAutoAssignPack = in_array($tier, $autoAssignTiers, true) || ($min === 80 && $max === 89);

    $assignedTo = 'inventory';
    if ($isAutoAssignPack) {
        // Try assign directly to user's club squad
        $stmtClubTeam = $db->prepare('SELECT team, max_players FROM user_club WHERE user_uuid = :user_uuid');
        if ($stmtClubTeam === false) {
            throw new Exception('Failed to load squad');
        }
        $stmtClubTeam->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $resTeam = $stmtClubTeam->execute();
        $clubInfo = $resTeam ? $resTeam->fetchArray(SQLITE3_ASSOC) : null;
        $team = json_decode($clubInfo['team'] ?? '[]', true);
        if (!is_array($team)) {
            $team = [];
        }
        $maxPlayers = (int)($clubInfo['max_players'] ?? DEFAULT_MAX_PLAYERS);
        $currentCount = 0;
        foreach ($team as $tp) {
            if ($tp !== null) {
                $currentCount++;
            }
        }
        if ($currentCount < $maxPlayers) {
            $team[] = $picked;
            $stmtUpdTeam = $db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = :user_uuid');
            if ($stmtUpdTeam === false) {
                throw new Exception('Failed to update squad');
            }
            $stmtUpdTeam->bindValue(':team', json_encode($team), SQLITE3_TEXT);
            $stmtUpdTeam->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            if (!$stmtUpdTeam->execute()) {
                throw new Exception('Failed to assign player to squad');
            }
            $assignedTo = 'squad';
        }
        // else: fall through to inventory if squad is full
    }

    // If not assigned to squad, add to player inventory
    if ($assignedTo !== 'squad') {
        // Detect player_inventory schema columns (user_uuid vs club_uuid)
        $piColumns = [];
        if (DB_DRIVER === 'mysql') {
            $resCols = $db->query('SHOW COLUMNS FROM player_inventory');
            while ($row = $resCols && $resCols->fetchArray(SQLITE3_ASSOC)) {
                if (isset($row['Field'])) $piColumns[] = $row['Field'];
                elseif (isset($row['name'])) $piColumns[] = $row['name'];
            }
        } else {
            $resCols = $db->query('PRAGMA table_info(player_inventory)');
            while ($row = $resCols && $resCols->fetchArray(SQLITE3_ASSOC)) {
                if (isset($row['name'])) $piColumns[] = $row['name'];
            }
        }
        $usesUserUuid = in_array('user_uuid', $piColumns, true);
        $usesClubUuid = in_array('club_uuid', $piColumns, true);

        if ($usesUserUuid) {
            $stmtIns = $db->prepare('INSERT INTO player_inventory (user_uuid, player_uuid, player_data, purchase_price) VALUES (:user_uuid, :player_uuid, :player_data, 0)');
            if ($stmtIns === false) {
                throw new Exception('Failed to prepare player insert');
            }
            $stmtIns->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $stmtIns->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
            $stmtIns->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
            if (!$stmtIns->execute()) {
                throw new Exception('Failed to add player to inventory');
            }
        } elseif ($usesClubUuid) {
            // Resolve or initialize club_uuid when needed
            $stmtClub = $db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :uuid');
            if ($stmtClub === false) {
                throw new Exception('Failed to resolve club');
            }
            $stmtClub->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
            $resClub = $stmtClub->execute();
            $rowClub = $resClub ? $resClub->fetchArray(SQLITE3_ASSOC) : null;
            $clubUuidVal = $rowClub['club_uuid'] ?? '';
            if ($clubUuidVal === '' || $clubUuidVal === null) {
                $clubUuidVal = generateUUID();
                $stmtSetClub = $db->prepare('UPDATE user_club SET club_uuid = :club_uuid WHERE user_uuid = :user_uuid');
                if ($stmtSetClub === false) {
                    throw new Exception('Failed to initialize club');
                }
                $stmtSetClub->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
                $stmtSetClub->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
                if (!$stmtSetClub->execute()) {
                    throw new Exception('Failed to initialize club');
                }
            }

            $stmtIns = $db->prepare('INSERT INTO player_inventory (club_uuid, player_uuid, player_data, purchase_price) VALUES (:club_uuid, :player_uuid, :player_data, 0)');
            if ($stmtIns === false) {
                throw new Exception('Failed to prepare player insert');
            }
            $stmtIns->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
            $stmtIns->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
            $stmtIns->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
            if (!$stmtIns->execute()) {
                throw new Exception('Failed to add player to inventory');
            }
        } else {
            throw new Exception('Unsupported inventory schema');
        }
    }

    $stmtDec = $db->prepare('UPDATE user_inventory SET quantity = quantity - 1 WHERE id = :id');
    if ($stmtDec === false) {
        throw new Exception('Failed to prepare inventory update');
    }
    $stmtDec->bindValue(':id', $inv['id'], SQLITE3_INTEGER);
    if (!$stmtDec->execute()) {
        throw new Exception('Failed to update item quantity');
    }

    $db->exec('COMMIT');
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => $assignedTo === 'squad' ? 'Pack opened: player assigned to your squad' : 'Pack opened: player added to your inventory',
        'player' => [
            'name' => $picked['name'] ?? 'Unknown',
            'position' => $picked['position'] ?? 'CM',
            'rating' => $picked['rating'] ?? 0
        ]
    ]);
} catch (Exception $e) {
    if (isset($db)) {
        $db->exec('ROLLBACK');
        $db->close();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
