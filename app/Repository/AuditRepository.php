<?php

declare(strict_types=1);

namespace Grant\Repository;

use PDO;

final class AuditRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(string $action, string $actorDiscordId, ?string $targetDiscordId, array $metadata = []): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO officer_audit_logs (action, actor_discord_id, target_discord_id, metadata_json) VALUES (:action, :actor, :target, :metadata)'
        );

        $stmt->execute([
            'action' => $action,
            'actor' => $actorDiscordId,
            'target' => $targetDiscordId,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
