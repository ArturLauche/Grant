<?php

declare(strict_types=1);

namespace Grant\Discord;

use Grant\Repository\AuditRepository;
use Grant\Repository\OfficerRepository;
use Grant\Service\RoleGate;
use Throwable;

final class InteractionHandler
{
    /** @var string[] */
    private array $developerIds;

    public function __construct(
        private OfficerRepository $officers,
        private AuditRepository $audits,
        private RoleGate $gate
    ) {
        $this->developerIds = array_filter(array_map('trim', explode(',', getenv('DEVELOPER_USER_IDS') ?: '')));
    }

    public function handle(array $interaction): array
    {
        if (($interaction['type'] ?? null) === 1) {
            return ['type' => 1];
        }

        if (($interaction['type'] ?? null) !== 2) {
            return $this->message('Unsupported interaction type.');
        }

        $name = $interaction['data']['name'] ?? '';
        return match ($name) {
            'ping' => $this->message('Pong!'),
            'echo' => $this->message((string) ($this->optionValue($interaction, 'input') ?? '')),
            'marks' => $this->handleMarks($interaction),
            'officer' => $this->handleOfficer($interaction),
            'command' => $this->handleDeveloperCommand($interaction),
            default => $this->message('Unknown command.'),
        };
    }

    private function handleDeveloperCommand(array $interaction): array
    {
        $sub = $interaction['data']['options'][0]['name'] ?? '';
        $options = $interaction['data']['options'][0]['options'] ?? [];
        $actor = $interaction['member']['user'] ?? ['id' => '', 'username' => 'unknown'];

        if (!$this->isDeveloper($actor['id'])) {
            return $this->message('Permission denied: developer only command.');
        }

        if ($sub === 'export') {
            $limit = (int) ($this->optionValueFrom($options, 'limit') ?? 50);
            $offset = (int) ($this->optionValueFrom($options, 'offset') ?? 0);

            $limit = max(1, min($limit, 500));
            $offset = max(0, $offset);

            $rows = $this->officers->exportOfficers($limit, $offset);
            $count = count($rows);

            $payload = [
                'version' => 1,
                'created_at' => gmdate('c'),
                'type' => 'officers_export',
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => $count,
                    'next_offset' => $offset + $count,
                    'has_more_possible' => $count === $limit,
                ],
                'rows' => $rows,
            ];

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($jsonPayload === false) {
                return $this->message('Export encoding failed: ' . json_last_error_msg());
            }

            $encoded = base64_encode($jsonPayload);
            if (strlen($encoded) > 1700) {
                return $this->message('Export too large for one Discord message. Re-run with a lower limit.');
            }

            $this->audits->log('developer_export', $actor['id'], null, ['rows' => $count, 'limit' => $limit, 'offset' => $offset, 'next_offset' => $offset + $count]);
            return $this->message("Export payload:\n{$encoded}");
        }

        if ($sub === 'import') {
            $encoded = (string) ($this->optionValueFrom($options, 'payload') ?? '');
            if ($encoded === '') {
                return $this->message('Import payload is required.');
            }

            $json = base64_decode($encoded, true);
            if ($json === false) {
                return $this->message('Invalid payload: not valid base64.');
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
                return $this->message('Invalid payload: JSON format is incorrect.');
            }

            try {
                $count = $this->officers->importOfficers($decoded['rows']);
            } catch (Throwable $e) {
                return $this->message('Import failed and was rolled back. Check server logs for details.');
            }

            $this->audits->log('developer_import', $actor['id'], null, ['imported_rows' => $count]);
            return $this->message("Import successful. Rows processed: {$count}.");
        }

        return $this->message('Unknown command subcommand.');
    }

    private function handleMarks(array $interaction): array
    {
        $sub = $interaction['data']['options'][0]['name'] ?? '';
        $options = $interaction['data']['options'][0]['options'] ?? [];
        $actorId = $interaction['member']['user']['id'] ?? '';
        $roleIds = $interaction['member']['roles'] ?? [];

        if (!$this->gate->isAtLeast($roleIds, 'MR')) {
            return $this->message('Permission denied: MR or higher required.');
        }

        if ($sub === 'get') {
            $target = $this->resolvedUser($interaction, $this->optionValueFrom($options, 'officer'));
            $targetId = $target['id'] ?? $actorId;
            $officer = $this->officers->findByDiscordId($targetId);
            if ($officer === null) {
                return $this->message('Officer not found.');
            }
            return $this->message(sprintf('%s has **%d** marks.', $officer['discord_username'], (int) $officer['marks']));
        }

        $target = $this->resolvedUser($interaction, $this->optionValueFrom($options, 'officer'));
        $amount = (int) ($this->optionValueFrom($options, 'amount') ?? 0);

        if ($target === null || $amount <= 0) {
            return $this->message('Invalid officer or amount.');
        }

        $officer = $this->officers->findByDiscordId($target['id']);
        if ($officer === null) {
            return $this->message('Officer not found.');
        }

        $newMarks = $sub === 'add' ? ((int) $officer['marks'] + $amount) : max(0, (int) $officer['marks'] - $amount);
        $this->officers->updateMarks($target['id'], $newMarks);
        $this->audits->log('marks_' . $sub, $actorId, $target['id'], ['amount' => $amount, 'new_marks' => $newMarks]);

        return $this->message(sprintf('%s now has **%d** marks.', $officer['discord_username'], $newMarks));
    }

    private function handleOfficer(array $interaction): array
    {
        $sub = $interaction['data']['options'][0]['name'] ?? '';
        $options = $interaction['data']['options'][0]['options'] ?? [];
        $actor = $interaction['member']['user'] ?? ['id' => '', 'username' => 'unknown'];
        $roleIds = $interaction['member']['roles'] ?? [];
        $isHR = $this->gate->isAtLeast($roleIds, 'HR');

        if ($sub === 'register') {
            $target = $this->resolvedUser($interaction, $this->optionValueFrom($options, 'user')) ?? $actor;
            $isSelf = ($target['id'] ?? '') === $actor['id'];
            if (!$isSelf && !$isHR) {
                return $this->message('Permission denied: HR required to register another officer.');
            }

            $this->officers->register($target['id'], $target['username']);
            $this->audits->log('register', $actor['id'], $target['id']);
            return $this->message(sprintf('Registered officer: %s.', $target['username']));
        }

        if ($sub === 'info') {
            $target = $this->resolvedUser($interaction, $this->optionValueFrom($options, 'officer')) ?? $actor;
            $isSelf = ($target['id'] ?? '') === $actor['id'];

            if (!$isSelf && !$isHR) {
                return $this->message('Permission denied: HR or higher required.');
            }

            $officer = $this->officers->findByDiscordId($target['id']);
            if ($officer === null) {
                return $this->message('Officer not found.');
            }

            $content = sprintf(
                "Officer: %s\nMarks: %d\nRank: %s",
                $officer['discord_username'],
                (int) $officer['marks'],
                $officer['rank'] ?: 'N/A'
            );

            if ($isHR) {
                $content .= sprintf("\nBlacklisted: %s", ((int) $officer['is_blacklisted']) === 1 ? 'Yes' : 'No');
            }

            return $this->message($content);
        }

        if (!$isHR) {
            return $this->message('Permission denied: HR or higher required.');
        }

        $target = $this->resolvedUser($interaction, $this->optionValueFrom($options, 'officer'));
        if ($target === null) {
            return $this->message('Officer argument is required.');
        }

        if ($sub === 'remove') {
            $removed = $this->officers->remove($target['id']);
            $this->audits->log('remove', $actor['id'], $target['id']);
            return $this->message($removed ? 'Officer removed.' : 'Officer not found.');
        }

        if ($sub === 'promote' || $sub === 'demote') {
            $officer = $this->officers->findByDiscordId($target['id']);
            if ($officer === null) {
                return $this->message('Officer not found.');
            }

            $rank = trim((string) ($this->optionValueFrom($options, 'rank') ?? ''));
            if ($rank === '') {
                return $this->message('Rank is required.');
            }
            $this->officers->setRank($target['id'], $rank);
            $this->audits->log($sub, $actor['id'], $target['id'], ['rank' => $rank]);
            return $this->message(sprintf('%s updated to rank: %s.', $target['username'], $rank));
        }

        if ($sub === 'blacklist') {
            $officer = $this->officers->findByDiscordId($target['id']);
            if ($officer === null) {
                return $this->message('Officer not found.');
            }

            $state = (string) ($this->optionValueFrom($options, 'state') ?? 'off');
            $isBlacklisted = $state === 'on';
            $this->officers->setBlacklisted($target['id'], $isBlacklisted);
            $this->audits->log('blacklist', $actor['id'], $target['id'], ['state' => $state]);
            return $this->message(sprintf('%s blacklist state: %s.', $target['username'], $isBlacklisted ? 'ON' : 'OFF'));
        }

        return $this->message('Unknown officer subcommand.');
    }

    private function isDeveloper(string $userId): bool
    {
        return $userId !== '' && in_array($userId, $this->developerIds, true);
    }

    private function optionValue(array $interaction, string $name): mixed
    {
        $options = $interaction['data']['options'] ?? [];
        return $this->optionValueFrom($options, $name);
    }

    private function optionValueFrom(array $options, string $name): mixed
    {
        foreach ($options as $option) {
            if (($option['name'] ?? '') === $name) {
                return $option['value'] ?? null;
            }
        }

        return null;
    }

    private function resolvedUser(array $interaction, mixed $userId): ?array
    {
        if (!is_string($userId) || $userId === '') {
            return null;
        }

        return $interaction['data']['resolved']['users'][$userId] ?? null;
    }

    private function message(string $content): array
    {
        return [
            'type' => 4,
            'data' => [
                'content' => $content,
                'flags' => 64,
            ],
        ];
    }
}
