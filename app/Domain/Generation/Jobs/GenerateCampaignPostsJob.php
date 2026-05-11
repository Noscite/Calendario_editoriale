<?php

declare(strict_types=1);

namespace App\Domain\Generation\Jobs;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Generation\Services\ClaudeContentGenerator;
use App\Domain\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera i post di una Campaign creata via POST /api/projects/{id}/campaigns
 * (modal "Campagna AI").
 *
 * Workflow:
 * 1. Aspetta che gli allegati siano extracted (timeout 3 min)
 * 2. Decide platforms + count (null = AI default heuristic)
 * 3. Chiama ClaudeContentGenerator::generateForCampaign
 * 4. I post creati hanno campaign_id valorizzato → si sommano al calendario
 *    esistente del project
 */
final class GenerateCampaignPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    private const ATTACHMENT_WAIT_MAX_SECONDS = 180;
    private const ATTACHMENT_POLL_INTERVAL    = 5;

    /**
     * @param  array<int, string>|null  $platformsRequested  null = AI decide (usa project.platforms)
     * @param  int|null                 $postsCountRequested  null = heuristic da durata
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly int $projectId,
        public readonly ?array $platformsRequested = null,
        public readonly ?int $postsCountRequested = null,
    ) {}

    public function handle(ClaudeContentGenerator $generator): void
    {
        $campaign = Campaign::with(['attachments', 'mcpServers', 'brands'])->findOrFail($this->campaignId);
        $project  = Project::with('brand')->findOrFail($this->projectId);

        // 1. Aspetta extraction degli allegati (best-effort, non bloccante).
        $this->waitForAttachmentExtraction($campaign);

        // 2. Risolvi platforms + count
        $platforms = $this->platformsRequested
            ?? ($project->platforms ?? config('territorial.default_platforms', ['linkedin', 'instagram', 'facebook']));
        $count = $this->postsCountRequested ?? $this->estimateOptimalCount($campaign);

        // 3. Genera. Il generator crea i Post con campaign_id valorizzato.
        try {
            $generator->generateForCampaign(
                campaign:    $campaign,
                project:     $project,
                platforms:   $platforms,
                postsCount:  $count,
            );

            $campaign->update([
                'status'           => CampaignStatus::Active,
                'generation_error' => null,
            ]);

            Log::info("[CAMPAIGN GEN] Campaign {$campaign->id} completed", [
                'platforms'    => $platforms,
                'count'        => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error("[CAMPAIGN GEN] Campaign {$campaign->id} failed", [
                'error' => $e->getMessage(),
            ]);
            $campaign->update([
                'status'           => CampaignStatus::Draft,  // torna a draft, l'utente può riprovare
                'generation_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function waitForAttachmentExtraction(Campaign $campaign): void
    {
        $waited = 0;
        while ($waited < self::ATTACHMENT_WAIT_MAX_SECONDS) {
            $pending = $campaign->attachments()
                ->whereIn('extraction_status', [
                    CampaignAttachment::STATUS_PENDING,
                    CampaignAttachment::STATUS_PROCESSING,
                ])
                ->exists();
            if (! $pending) {
                return;
            }
            sleep(self::ATTACHMENT_POLL_INTERVAL);
            $waited += self::ATTACHMENT_POLL_INTERVAL;
        }

        Log::warning("[CAMPAIGN GEN] Campaign {$campaign->id}: attachment extraction timeout (>180s), proceeding with available text");
    }

    private function estimateOptimalCount(Campaign $campaign): int
    {
        $days = $campaign->start_date && $campaign->end_date
            ? Carbon::parse($campaign->start_date)->diffInDays(Carbon::parse($campaign->end_date)) + 1
            : 7;

        // ~1 post ogni 3 giorni, min 3 max 30
        return max(3, min(30, (int) ceil($days / 3)));
    }
}
