<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

class AvatarService
{
    public function getByUserId(string $userId): ?object
    {
        return Database::fetchOne("SELECT * FROM avatars WHERE user_id = ?", [$userId]);
    }

    public function createOrUpdate(string $userId, array $data): object
    {
        $existing = $this->getByUserId($userId);

        if ($existing) {
            Database::update('avatars', $data, ['user_id' => $userId]);
        } else {
            Database::insert('avatars', array_merge(
                ['id' => Uuid::uuid4()->toString(), 'user_id' => $userId],
                $data
            ));
        }

        return $this->getByUserId($userId);
    }

    public function getPresets(?string $category): array
    {
        if ($category) {
            return Database::fetchAll(
                "SELECT * FROM avatar_presets WHERE category = ? AND active = 1",
                [$category]
            );
        }

        return Database::fetchAll("SELECT * FROM avatar_presets WHERE active = 1 ORDER BY category, sort_order");
    }

    public function listAccessories(array $filter): array
    {
        $where  = 'WHERE aa.active = 1';
        $params = [];

        if ($filter['category']) {
            $where   .= ' AND aa.category = ?';
            $params[] = $filter['category'];
        }

        if ($filter['rarity']) {
            $where   .= ' AND aa.rarity = ?';
            $params[] = $filter['rarity'];
        }

        $selectUnlocked = $filter['unlocked']
            ? 'INNER JOIN user_accessories ua ON ua.accessory_id = aa.id AND ua.user_id = ?'
            : 'LEFT JOIN user_accessories ua ON ua.accessory_id = aa.id AND ua.user_id = ?';

        array_unshift($params, $filter['user_id']);

        return Database::fetchAll(
            "SELECT aa.*, (ua.user_id IS NOT NULL) AS is_unlocked
             FROM avatar_accessories aa
             {$selectUnlocked}
             {$where}
             ORDER BY aa.rarity DESC, aa.coin_cost ASC",
            $params
        );
    }

    public function unlockAccessory(string $userId, string $accessoryId): array
    {
        $accessory = Database::fetchOne(
            "SELECT * FROM avatar_accessories WHERE id = ? AND active = 1",
            [$accessoryId]
        );

        if (!$accessory) {
            return ['success' => false, 'message' => 'Accessory not found.'];
        }

        $alreadyOwned = Database::fetchOne(
            "SELECT 1 FROM user_accessories WHERE user_id = ? AND accessory_id = ?",
            [$userId, $accessoryId]
        );

        if ($alreadyOwned) {
            return ['success' => false, 'message' => 'You already own this accessory.'];
        }

        if ($accessory->coin_cost > 0) {
            $deducted = Database::query(
                "UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND balance >= ?",
                [$accessory->coin_cost, $userId, $accessory->coin_cost]
            )->rowCount();

            if (!$deducted) {
                return ['success' => false, 'message' => 'Insufficient coins.'];
            }
        }

        Database::insert('user_accessories', [
            'user_id'       => $userId,
            'accessory_id'  => $accessoryId,
            'unlocked_at'   => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'accessory' => $accessory];
    }

    public function getAvailableExpressions(string $userId): array
    {
        return [
            'standard'  => ['neutral', 'smile', 'laugh', 'wink', 'sad', 'surprised', 'angry', 'blink'],
            'premium'   => [],  // Unlocked via subscription
            'seasonal'  => [],  // Time-limited
        ];
    }
}
