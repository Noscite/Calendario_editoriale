<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BrandAudit extends Model
{
    protected $fillable = [
        'brand_id',
        'standalone_data',
        'prospect_name',
        'prospect_sector',
        'share_token',
        'status',
        'website_url',
        'score_overall',
        'score_neuromarketing',
        'score_seo_geo',
        'score_geo',
        'score_social_coherence',
        'score_gdpr',
        'score_accessibility',
        'score_performance',
        'raw_data',
        'analyzer_results',
        'executive_summary',
        'critical_issues',
        'recommendations',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'           => AuditStatus::class,
            'standalone_data'  => 'json',
            'raw_data'         => 'json',
            'analyzer_results' => 'json',
            'critical_issues'  => 'json',
            'recommendations'  => 'json',
            'completed_at'     => 'datetime',
            'share_token'      => 'string',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Nome visualizzabile: brand name se esiste, poi standalone_data,
     * poi prospect_name (legacy), poi URL.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->brand) {
            return $this->brand->name;
        }
        return $this->standalone_data['company_name']
            ?? $this->prospect_name
            ?? $this->website_url
            ?? 'Prospect';
    }

    /**
     * Settore: dal brand se esiste, poi standalone_data, poi prospect_sector.
     */
    public function getDisplaySectorAttribute(): string
    {
        if ($this->brand) {
            return $this->brand->sector ?? '';
        }
        return $this->standalone_data['sector']
            ?? $this->prospect_sector
            ?? '';
    }

    public function isStandalone(): bool
    {
        return $this->brand_id === null;
    }

    /**
     * Genera (o restituisce) l'URL condivisibile per questo audit.
     * - Standalone/prospect → pagina pubblica senza login (/audit/share/{token})
     * - Brand → URL della dashboard che richiede login (/brand/{brandId})
     */
    public function getShareUrl(): string
    {
        if ($this->isStandalone()) {
            if (! $this->share_token) {
                $this->share_token = Str::random(32);
                $this->save();
            }
            return url("/audit/share/{$this->share_token}");
        }

        // Brand audit: link alla dashboard del brand (richiede login)
        return url("/brand/{$this->brand_id}");
    }

    public function isCompleted(): bool
    {
        return $this->status === AuditStatus::Completed;
    }

    public function getScoreLabelAttribute(): string
    {
        return match(true) {
            $this->score_overall >= 80 => 'Ottimo',
            $this->score_overall >= 60 => 'Buono',
            $this->score_overall >= 40 => 'Da migliorare',
            default                    => 'Critico',
        };
    }

    public function getScoreColorAttribute(): string
    {
        return match(true) {
            $this->score_overall >= 80 => 'green',
            $this->score_overall >= 60 => 'yellow',
            $this->score_overall >= 40 => 'orange',
            default                    => 'red',
        };
    }
}
