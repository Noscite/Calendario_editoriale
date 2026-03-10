<?php

namespace App\Domain\Shared\Traits;

use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        // Global scope: filtra automaticamente per organization_id dell'utente autenticato
        static::addGlobalScope('organization', function (Builder $builder) {
            if (auth()->check() && auth()->user()->organization_id) {
                $builder->where(
                    $builder->getModel()->getTable() . '.organization_id',
                    auth()->user()->organization_id
                );
            }
        });

        // Auto-assegna organization_id alla creazione
        static::creating(function ($model) {
            if (! $model->organization_id && auth()->check()) {
                $model->organization_id = auth()->user()->organization_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Rimuove il global scope organization per query cross-org (es. admin).
     */
    public function scopeWithoutOrganization(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('organization');
    }
}
