<?php

declare(strict_types=1);

namespace Grant\Repository;

use PDO;

final class OfficerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByDiscordId(string $discordId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM officers WHERE discord_id = :discord_id LIMIT 1');
        $stmt->execute(['discord_id' => $discordId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function register(string $discordId, string $discordUsername): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO officers (discord_id, discord_username) VALUES (:discord_id, :discord_username)
             ON DUPLICATE KEY UPDATE discord_username = VALUES(discord_username), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'discord_id' => $discordId,
            'discord_username' => $discordUsername,
        ]);
    }

    public function updateMarks(string $discordId, int $newMarks): void
    {
        $stmt = $this->pdo->prepare('UPDATE officers SET marks = :marks, updated_at = CURRENT_TIMESTAMP WHERE discord_id = :discord_id');
        $stmt->execute([
            'marks' => $newMarks,
            'discord_id' => $discordId,
        ]);
    }

    public function remove(string $discordId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM officers WHERE discord_id = :discord_id');
        $stmt->execute(['discord_id' => $discordId]);

        return $stmt->rowCount() > 0;
    }

    public function setRank(string $discordId, string $rank): void
    {
        $stmt = $this->pdo->prepare('UPDATE officers SET rank = :rank, updated_at = CURRENT_TIMESTAMP WHERE discord_id = :discord_id');
        $stmt->execute([
            'rank' => $rank,
            'discord_id' => $discordId,
        ]);
    }

    public function setBlacklisted(string $discordId, bool $blacklisted): void
    {
        $stmt = $this->pdo->prepare('UPDATE officers SET is_blacklisted = :is_blacklisted, updated_at = CURRENT_TIMESTAMP WHERE discord_id = :discord_id');
        $stmt->execute([
            'is_blacklisted' => $blacklisted ? 1 : 0,
            'discord_id' => $discordId,
        ]);
    }

    public function exportOfficers(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT discord_id, discord_username, marks, rank, is_blacklisted FROM officers ORDER BY officer_id ASC LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function importOfficers(array $rows): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO officers (discord_id, discord_username, marks, rank, is_blacklisted)
             VALUES (:discord_id, :discord_username, :marks, :rank, :is_blacklisted)
             ON DUPLICATE KEY UPDATE
               discord_username = VALUES(discord_username),
               marks = VALUES(marks),
               rank = VALUES(rank),
               is_blacklisted = VALUES(is_blacklisted),
               updated_at = CURRENT_TIMESTAMP'
        );

        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['discord_id']) || empty($row['discord_username'])) {
                continue;
            }

            $stmt->execute([
                'discord_id' => (string) $row['discord_id'],
                'discord_username' => (string) $row['discord_username'],
                'marks' => max(0, (int) ($row['marks'] ?? 0)),
                'rank' => isset($row['rank']) && $row['rank'] !== '' ? (string) $row['rank'] : null,
                'is_blacklisted' => !empty($row['is_blacklisted']) ? 1 : 0,
            ]);
            $count += 1;
        }

        return $count;
    }
}
