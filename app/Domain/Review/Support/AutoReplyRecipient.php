<?php

declare(strict_types=1);

namespace App\Domain\Review\Support;

use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;

/**
 * Helper unico per risolvere il destinatario delle notifiche auto-reply.
 *
 * Logica:
 *   1. Primo User con role 'owner' nell'organization (se esistesse)
 *   2. Primo User con role 'superuser' o 'admin'
 *   3. Primo User dell'organization
 */
final class AutoReplyRecipient
{
    public static function for(Organization $organization): ?User
    {
        $orgId = $organization->id;

        $owner = User::query()
            ->where('organization_id', $orgId)
            ->where('role', 'owner')
            ->orderBy('id')
            ->first();
        if ($owner !== null) {
            return $owner;
        }

        $admin = User::query()
            ->where('organization_id', $orgId)
            ->whereIn('role', ['superuser', 'admin'])
            ->orderBy('id')
            ->first();
        if ($admin !== null) {
            return $admin;
        }

        return User::query()
            ->where('organization_id', $orgId)
            ->orderBy('id')
            ->first();
    }
}
