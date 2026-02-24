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

    public function openPack(int $inventoryId, ?int $shopId = null): array
    {
        $this->db->exec('START TRANSACTION');

        try {
            $stmtInv = $this->db->prepare('SELECT id, quantity, item_id FROM user_inventory WHERE id = :id AND user_uuid = :user_uuid AND quantity > 0');
            if ($stmtInv === false) {
                throw new Exception('Failed to prepare inventory lookup');
            }
            $stmtInv->bindValue(':id', $inventoryId, SQLITE3_INTEGER);
            $stmtInv->bindValue(':user_uuid', $this->userUuid, SQLITE3_TEXT);
            $resInv = $stmtInv->execute();
            $inv = $resInv ? $resInv->fetchArray(SQLITE3_ASSOC) : null;

            if (!$inv) {
                throw new Exception('Item not found in your inventory');
            }

            $resolvedShopId = $shopId && $shopId > 0 ? (int)$shopId : (int)($inv['item_id'] ?? 0);
            if ($resolvedShopId <= 0) {
                throw new Exception('Invalid shop item reference');
            }

            $stmtShop = $this->db->prepare('SELECT id, effect_type, effect_value FROM shop_items WHERE id = :id');
            if ($stmtShop === false) {
                throw new Exception('Failed to load shop item');
            }
            $stmtShop->bindValue(':id', $resolvedShopId, SQLITE3_INTEGER);
            $resShop = $stmtShop->execute();
            $shopItem = $resShop ? $resShop->fetchArray(SQLITE3_ASSOC) : null;
            if (!$shopItem || ($shopItem['effect_type'] ?? '') !== 'player_pack') {
                throw new Exception('This item cannot be opened');
            }

            $effect = json_decode($shopItem['effect_value'] ?? '[]', true);
            if (!is_array($effect)) {
                $effect = [];
            }
            $min = isset($effect['min_rating']) ? (int)$effect['min_rating'] : 60;
            $max = isset($effect['max_rating']) ? (int)$effect['max_rating'] : 90;
            $tier = isset($effect['tier']) ? strtolower((string)$effect['tier']) : '';
            $positionsFilter = [];
            if (isset($effect['positions']) && is_array($effect['positions'])) {
                $positionsFilter = array_values(array_filter($effect['positions'], function ($x) {
                    return is_string($x) && $x !== '';
                }));
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
            $picked = initializePlayerCondition($picked);

            $reveal = [];
            $decoys = [];
            $pool = $eligible;
            $filteredPool = [];
            foreach ($pool as $pl) {
                if (($pl['uuid'] ?? '') !== ($picked['uuid'] ?? '')) {
                    $filteredPool[] = $pl;
                }
            }
            if (count($filteredPool) > 0) {
                shuffle($filteredPool);
                $decoys = array_slice($filteredPool, 0, min(9, count($filteredPool)));
            }
            foreach ($decoys as $dc) {
                $reveal[] = [
                    'name' => $dc['name'] ?? 'Unknown',
                    'position' => $dc['position'] ?? 'CM',
                    'rating' => (int)($dc['rating'] ?? 0)
                ];
            }
            $reveal[] = [
                'name' => $picked['name'] ?? 'Unknown',
                'position' => $picked['position'] ?? 'CM',
                'rating' => (int)($picked['rating'] ?? 0)
            ];

            $inserted = false;
            $newInventoryId = null;

            $stmtClub = $this->db->prepare('SELECT club_uuid FROM user_club WHERE user_uuid = :uuid');
            if ($stmtClub !== false) {
                $stmtClub->bindValue(':uuid', $this->userUuid, SQLITE3_TEXT);
                $resClub = $stmtClub->execute();
                $rowClub = $resClub ? $resClub->fetchArray(SQLITE3_ASSOC) : null;
                if (!$rowClub || !isset($rowClub['club_uuid'])) {
                    throw new Exception('User not associated with a club');
                }
                $stmtIns2 = $this->db->prepare('INSERT INTO player_inventory (club_uuid, player_uuid, player_data, purchase_price) VALUES (:club_uuid, :player_uuid, :player_data, 10)');
                if ($stmtIns2 !== false) {
                    $stmtIns2->bindValue(':club_uuid', $rowClub['club_uuid'], SQLITE3_TEXT);
                    $stmtIns2->bindValue(':player_uuid', $picked['uuid'], SQLITE3_TEXT);
                    $stmtIns2->bindValue(':player_data', json_encode($picked), SQLITE3_TEXT);
                    $inserted = $stmtIns2->execute();
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
                'message' => 'Pack opened: player added to your inventory',
                'player' => [
                    'name' => $picked['name'] ?? 'Unknown',
                    'position' => $picked['position'] ?? 'CM',
                    'rating' => $picked['rating'] ?? 0,
                ],
                'reveal' => $reveal
            ];
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }
}
