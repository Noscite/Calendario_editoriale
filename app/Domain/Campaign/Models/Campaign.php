<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Post\Models\Post;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'brief',
        'status',
        'start_date',
        'end_date',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status'     => CampaignStatus::class,
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    protected static function newFactory(): \Database\Factories\CampaignFactory
    {
        return \Database\Factories\CampaignFactory::new();
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_campaign')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CampaignAttachment::class);
    }

    /**
     * Allegati pronti per essere usati come KB nella generazione AI
     * (extraction_status='completed' con extracted_text non nullo).
     */
    public function attachmentsReadyForAI(): HasMany
    {
        return $this->hasMany(CampaignAttachment::class)
            ->where('extraction_status', CampaignAttachment::STATUS_COMPLETED)
            ->whereNotNull('extracted_text');
    }

    /**
     * MCP servers attivi configurati direttamente sulla campagna.
     */
    public function mcpServers(): HasMany
    {
        return $this->hasMany(\App\Domain\Mcp\Models\CampaignMcpServer::class)
            ->where('is_active', true);
    }

    /**
     * true se almeno un campaign MCP ha override_brand_mcp=true → esclude
     * gli MCP del brand dalla effective list.
     */
    public function hasMcpOverride(): bool
    {
        return $this->mcpServers()->where('override_brand_mcp', true)->exists();
    }

    /**
     * Lista degli MCP server effettivi da passare ad Anthropic.
     *
     * - Default: union dei campaign MCP attivi + brand MCP attivi.
     * - Se hasMcpOverride(): solo i campaign MCP attivi.
     *
     * Se $brandId è specificato (es. quando si genera per un singolo
     * Project linkato a Campaign): usa SOLO gli MCP di quel brand.
     * Altrimenti usa l'union degli MCP di tutti i brand della campaign.
     *
     * @return array<int, array{name: string, url: string, api_key: ?string}>
     */
    public function effectiveMcpServers(?int $brandId = null): array
    {
        $toRow = fn ($m): array => [
            'name'    => $m->name,
            'url'     => $m->url,
            'api_key' => $m->getApiKey(),
        ];

        // toBase() perché dopo map() a array gli elementi non sono più modelli:
        // merge() su EloquentCollection assume getKey() sui modelli.
        $campaignMcps = $this->mcpServers->toBase()->map($toRow);

        if ($this->hasMcpOverride()) {
            return $campaignMcps->values()->all();
        }

        $brands = $brandId === null
            ? $this->brands
            : $this->brands->where('id', $brandId);

        $brandMcps = $brands->toBase()
            ->flatMap(fn ($brand) => $brand->mcpServers)
            ->map($toRow);

        return $campaignMcps->merge($brandMcps)->values()->all();
    }

    /**
     * Helper per verificare se la campagna è in uno stato che la fa contare per il plan limit.
     */
    public function isActiveStatus(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Verifica che le date siano coerenti (start <= end).
     */
    public function hasValidDateRange(): bool
    {
        return $this->start_date && $this->end_date && $this->start_date->lessThanOrEqualTo($this->end_date);
    }
}
