<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Services;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Jobs\ExtractAttachmentTextJob;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Generation\Jobs\GenerateCampaignPostsJob;
use App\Domain\Project\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea una Campaign dentro il calendario di un project (entry point unico
 * dal modal "Campagna AI"). Gestisce upload allegati, MCP config e dispatch
 * della generation async.
 */
final class CampaignGenerationService
{
    /**
     * @param  array{
     *   name: string,
     *   brief: string,
     *   pillar?: ?string,
     *   start_date?: ?string,
     *   end_date?: ?string,
     *   platforms?: ?array<int, string>,
     *   posts_count?: ?int,
     *   attachments?: array<int, UploadedFile>,
     *   mcp_servers?: array<int, array{name: string, url: string, api_key?: ?string, scopes?: ?array<int, string>, override_brand_mcp?: ?bool}>,
     * }  $data
     */
    public function createAndGenerate(Project $project, array $data, ?int $userId = null): Campaign
    {
        return DB::transaction(function () use ($project, $data, $userId) {
            $campaign = Campaign::create([
                'organization_id'    => $project->organization_id,
                'name'               => $data['name'],
                'brief'              => $data['brief'],
                'pillar'             => $data['pillar'] ?? null,
                'start_date'         => $data['start_date'] ?? $project->start_date,
                'end_date'           => $data['end_date'] ?? $project->end_date,
                'status'             => CampaignStatus::Planning,
                'created_by_user_id' => $userId,
            ]);

            // Lega al brand del project (pivot brand_campaign — relation belongsToMany)
            $campaign->brands()->syncWithoutDetaching([$project->brand_id]);

            foreach ($data['attachments'] ?? [] as $file) {
                $this->storeAttachment($campaign, $file, $userId);
            }

            foreach ($data['mcp_servers'] ?? [] as $mcp) {
                $campaign->mcpServers()->create([
                    'name'               => $mcp['name'],
                    'url'                => $mcp['url'],
                    'encrypted_api_key'  => ! empty($mcp['api_key']) ? Crypt::encryptString($mcp['api_key']) : null,
                    'scopes'             => $mcp['scopes'] ?? null,
                    'is_active'          => true,
                    'override_brand_mcp' => $mcp['override_brand_mcp'] ?? false,
                ]);
            }

            GenerateCampaignPostsJob::dispatch(
                $campaign->id,
                $project->id,
                $data['platforms']    ?? null,
                $data['posts_count']  ?? null,
            );

            return $campaign;
        });
    }

    /**
     * Promuove una Campaign in Draft (creata via QuickAddPostModal espandendo
     * KB/MCP) allo stato Planning, dispatchando la generation.
     */
    public function promoteDraft(Campaign $campaign, Project $project, array $data): Campaign
    {
        $campaign->update([
            'name'       => $data['name'],
            'brief'      => $data['brief'],
            'pillar'     => $data['pillar'] ?? null,
            'start_date' => $data['start_date'] ?? $project->start_date,
            'end_date'   => $data['end_date'] ?? $project->end_date,
            'status'     => CampaignStatus::Planning,
        ]);

        $campaign->brands()->syncWithoutDetaching([$project->brand_id]);

        GenerateCampaignPostsJob::dispatch(
            $campaign->id,
            $project->id,
            $data['platforms']    ?? null,
            $data['posts_count']  ?? null,
        );

        return $campaign;
    }

    public function storeAttachment(Campaign $campaign, UploadedFile $file, ?int $userId): CampaignAttachment
    {
        $extension      = $file->getClientOriginalExtension();
        $storedFilename = Str::uuid()->toString() . ($extension ? ".{$extension}" : '');
        $storagePath    = storage_path("app/campaign-attachments/{$campaign->id}");
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }
        $file->move($storagePath, $storedFilename);

        $attachment = CampaignAttachment::create([
            'campaign_id'         => $campaign->id,
            'uploaded_by_user_id' => $userId,
            'original_filename'   => $file->getClientOriginalName(),
            'stored_filename'     => $storedFilename,
            'mime_type'           => $file->getClientMimeType(),
            'size_bytes'          => filesize("{$storagePath}/{$storedFilename}") ?: 0,
            'extraction_status'   => CampaignAttachment::STATUS_PENDING,
        ]);

        ExtractAttachmentTextJob::dispatch($attachment->id);

        return $attachment;
    }
}
