<?php

declare(strict_types=1);

namespace App\Domain\User\Data;

use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * DTO di output per un User — corrisponde a UserResponse + UserOut (Python).
 */
final class UserData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $full_name,
        public readonly string $role,
        public readonly bool $is_active,
        public readonly ?int $organization_id,
        public readonly ?string $organization_name = null,
        public readonly ?string $phone = null,
        public readonly ?string $company = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        public readonly ?string $vat_number = null,
        public readonly ?string $notes = null,
        public readonly ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            email: $user->email,
            full_name: $user->full_name,
            role: $user->role ?? 'editor',
            is_active: (bool) $user->is_active,
            organization_id: $user->organization_id,
            organization_name: $user->relationLoaded('organization') ? $user->organization?->name : null,
            phone: $user->phone,
            company: $user->company,
            address: $user->address,
            city: $user->city,
            country: $user->country,
            vat_number: $user->vat_number,
            notes: $user->notes,
            created_at: $user->created_at ? CarbonImmutable::instance($user->created_at) : null,
        );
    }
}
