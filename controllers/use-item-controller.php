<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/player_functions.php';

class UseItemController
{
    private $db;
    private $userUuid;

    public function __construct($userUuid)
    {
        $this->db = getDbConnection();
        $this->userUuid = $userUuid;
    }

    public function openPack(int $inventoryId): array
    {
        $this->db->exec('START TRANSACTION');

        $stmt = $this->db->prepare('SELECT ui.id, ui.quantity, ui.item_id, si.effect_type, si.effect_value FROM user_inventory ui JOIN shop_items si ON ui.item_id = si.id WHERE ui.id = :id AND ui.user_uuid = :user_uuid AND ui.quantity > 0');
        if ($stmt === false) {
            throw new Exception('Failed to prepare inventory lookup');
        }
        $stmt->bindValue(':id', $inventoryId, SQLITE3_INTEGER);
        $stmt->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
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
        $positionsFilter = [];
        if (isset($effect['positions']) && is_array($effect['positions'])) {
            $positionsFilter = array_values(array_filter($effect['positions'], fn($x) => is_string($x) && $x !== ''));
        }

        $players = getDefaultPlayers();
        $eligible = array_values(array_filter($players, function ($p) use ($min, $max, $positionsFilter) {
            $r = (int)($p['rating'] ?? 0);
            if (!($r >= $min && $r <= $max)) {
                return false;
            }
            if (!empty($positionsFilter)) {
                $pos = $p['position'] ?? '';
                if (!in_array($pos, $positionsFilter, true)) {
                    return false;
                }
            }
            return true;
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

        $autoAssignTiers = ['standard', 'elite', 'superstar', 'legend', 'gk', 'defender', 'midfielder', 'forward'];
        $isAutoAssignPack = in_array($tier, $autoAssignTiers, true) || ($min === 80 && $max === 89);

        $assignedTo = 'inventory';
        if ($isAutoAssignPack) {
            $stmtClubTeam = $this->db->prepare('SELECT team, max_players FROM user_club WHERE user_uuid = :user_uuid');
            if ($stmtClubTeam === false) {
                throw new Exception('Failed to load squad');
            }
            $stmtClubTeam->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
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
                $stmtUpdTeam = $this->db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = :user_uuid');
                if ($stmtUpdTeam === false) {
                    throw new Exception('Failed to update squad');
                }
                $stmtUpdTeam->bindValue(':team', json_encode($team), SQLITE3_TEXT);
                $stmtUpdTeam->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                if (!$stmtUpdTeam->execute()) {
                    throw new Exception('Failed to assign player to squad');
                }
                $assignedTo = 'squad';
            }
        }

        if ($assignedTo !== 'squad') {
            $inserted = false;
            $newInventoryId = null;

            // Prefer club inventory first
            $stmtClub = $this->db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :uuid');
            if ($stmtClub !== false) {
                $stmtClub->bindValue(':uuid', $this->userUuid, SQLITE3_TEXT);
                $resClub = $stmtClub->execute();
                $rowClub = $resClub ? $resClub->fetchArray(SQLITE3_ASSOC) : null;
                $clubUuidVal = $rowClub['club_uuid'] ?? '';
                if ($clubUuidVal === '' || $clubUuidVal === null) {
                    $clubUuidVal = generateUUID();
                    $stmtSetClub = $this->db->prepare('UPDATE user_club SET club_uuid = :club_uuid WHERE user_uuid = :user_uuid');
                    if ($stmtSetClub !== false) {
                        $stmtSetClub->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
                        $stmtSetClub->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                        $stmtSetClub->execute();
                    }
                }
                $stmtIns2 = $this->db->prepare('INSERT INTO player_inventory (club_uuid, player_uuid, player_data, purchase_price) VALUES (:club_uuid, :player_uuid, :player_data, 0)');
                if ($stmtIns2 !== false) {
                    $stmtIns2->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
                    $stmtIns2->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                    $stmtIns2->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
                    $inserted = $stmtIns2->execute() ? true : false;
                    if ($inserted) {
                        $q = $this->db->prepare('SELECT id FROM player_inventory WHERE club_uuid = :club_uuid AND player_uuid = :player_uuid ORDER BY id DESC LIMIT 1');
                        if ($q) {
                            $q->bindValue(':club_uuid', $clubUuidVal, SQLITE3_TEXT);
                            $q->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                            $r = $q->execute();
                            $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
                            if ($row && isset($row['id'])) {
                                $newInventoryId = (int)$row['id'];
                            }
                        }
                    }
                }
            }

            // Fallback to user inventory
            if (!$inserted) {
                $stmtIns = $this->db->prepare('INSERT INTO player_inventory (user_uuid, player_uuid, player_data, purchase_price) VALUES (:user_uuid, :player_uuid, :player_data, 0)');
                if ($stmtIns !== false) {
                    $stmtIns->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                    $stmtIns->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                    $stmtIns->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
                    $inserted = $stmtIns->execute() ? true : false;
                    if ($inserted) {
                        $q = $this->db->prepare('SELECT id FROM player_inventory WHERE user_uuid = :user_uuid AND player_uuid = :player_uuid ORDER BY id DESC LIMIT 1');
                        if ($q) {
                            $q->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                            $q->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                            $r = $q->execute();
                            $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
                            if ($row && isset($row['id'])) {
                                $newInventoryId = (int)$row['id'];
                            }
                        }
                    }
                }
            }

            // Auto-migrate: add club_uuid and retry club insert
            if (!$inserted) {
                @$this->db->exec('ALTER TABLE player_inventory ADD COLUMN club_uuid CHAR(16) NOT NULL DEFAULT ""');
                @$this->db->exec('CREATE INDEX idx_player_inventory_club_uuid ON player_inventory (club_uuid)');
                $stmtClub2 = $this->db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :uuid');
                if ($stmtClub2 !== false) {
                    $stmtClub2->bindValue(':uuid', $this->userUuid, SQLITE3_TEXT);
                    $resClub2 = $stmtClub2->execute();
                    $rowClub2 = $resClub2 ? $resClub2->fetchArray(SQLITE3_ASSOC) : null;
                    $clubUuidVal2 = $rowClub2['club_uuid'] ?? '';
                    if ($clubUuidVal2 === '' || $clubUuidVal2 === null) {
                        $clubUuidVal2 = generateUUID();
                        $stmtSetClub2 = $this->db->prepare('UPDATE user_club SET club_uuid = :club_uuid WHERE user_uuid = :user_uuid');
                        if ($stmtSetClub2 !== false) {
                            $stmtSetClub2->bindValue(':club_uuid', $clubUuidVal2, SQLITE3_TEXT);
                            $stmtSetClub2->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                            $stmtSetClub2->execute();
                        }
                    }
                    $stmtInsClubFix = $this->db->prepare('INSERT INTO player_inventory (club_uuid, player_uuid, player_data, purchase_price) VALUES (:club_uuid, :player_uuid, :player_data, 0)');
                    if ($stmtInsClubFix !== false) {
                        $stmtInsClubFix->bindValue(':club_uuid', $clubUuidVal2, SQLITE3_TEXT);
                        $stmtInsClubFix->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                        $stmtInsClubFix->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
                        $inserted = $stmtInsClubFix->execute() ? true : false;
                        if ($inserted) {
                            $q = $this->db->prepare('SELECT id FROM player_inventory WHERE club_uuid = :club_uuid AND player_uuid = :player_uuid ORDER BY id DESC LIMIT 1');
                            if ($q) {
                                $q->bindValue(':club_uuid', $clubUuidVal2, SQLITE3_TEXT);
                                $q->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                                $r = $q->execute();
                                $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
                                if ($row && isset($row['id'])) {
                                    $newInventoryId = (int)$row['id'];
                                }
                            }
                        }
                    }
                }
            }

            // Final fallback: add user_uuid and try user insert once more
            if (!$inserted) {
                @$this->db->exec('ALTER TABLE player_inventory ADD COLUMN user_uuid CHAR(16) NOT NULL DEFAULT ""');
                @$this->db->exec('CREATE INDEX idx_player_inventory_user_uuid ON player_inventory (user_uuid)');
                $stmtInsFix = $this->db->prepare('INSERT INTO player_inventory (user_uuid, player_uuid, player_data, purchase_price) VALUES (:user_uuid, :player_uuid, :player_data, 0)');
                if ($stmtInsFix !== false) {
                    $stmtInsFix->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                    $stmtInsFix->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                    $stmtInsFix->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
                    $inserted = $stmtInsFix->execute() ? true : false;
                    if ($inserted) {
                        $q = $this->db->prepare('SELECT id FROM player_inventory WHERE user_uuid = :user_uuid AND player_uuid = :player_uuid ORDER BY id DESC LIMIT 1');
                        if ($q) {
                            $q->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
                            $q->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                            $r = $q->execute();
                            $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
                            if ($row && isset($row['id'])) {
                                $newInventoryId = (int)$row['id'];
                            }
                        }
                    }
                }
            }

            if (!$inserted) {
                throw new Exception('Unsupported inventory schema');
            }
        }

        $stmtDec = $this->db->prepare('UPDATE user_inventory SET quantity = quantity - 1 WHERE id = :id');
        if ($stmtDec === false) {
            throw new Exception('Failed to prepare inventory update');
        }
        $stmtDec->bindValue(':id', $inv['id'], SQLITE3_INTEGER);
        if (!$stmtDec->execute()) {
            throw new Exception('Failed to update item quantity');
        }

        $this->db->exec('COMMIT');

        return [
            'success' => true,
            'message' => $assignedTo === 'squad' ? 'Pack opened: player assigned to your squad' : 'Pack opened: player added to your inventory',
            'player' => [
                'name' => $picked['name'] ?? 'Unknown',
                'position' => $picked['position'] ?? 'CM',
                'rating' => $picked['rating'] ?? 0,
                'inventory_id' => isset($newInventoryId) ? $newInventoryId : null
            ]
        ];
    }
}
