<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'organization_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_name',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'details'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function log(
        int $userId,
        int $organizationId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityName = null,
        array $details = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): self {
        return self::create([
            'user_id'         => $userId,
            'organization_id' => $organizationId,
            'action'          => $action,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'entity_name'     => $entityName,
            'details'         => $details ?: null,
            'ip_address'      => $ip,
            'user_agent'      => $userAgent,
        ]);
    }
}
