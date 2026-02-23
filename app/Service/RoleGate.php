<?php

declare(strict_types=1);

namespace Grant\Service;

final class RoleGate
{
    public function __construct(private array $rankRoles)
    {
    }

    public function isAtLeast(array $memberRoleIds, string $minimumRank): bool
    {
        $eligibleRoles = $this->rankRoles[$minimumRank] ?? [];
        foreach ($memberRoleIds as $roleId) {
            if (in_array($roleId, $eligibleRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
