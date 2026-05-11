<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Jobs;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Enums\Platform;
use App\Domain\Post\Enums\PostType;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Territorial\Generators\EventPostGenerator;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Models\TerritorialEventPost;
use App\Domain\Territorial\Services\TerritoryMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTerritorialPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    private const ALLOWED_VERTICALS = ['pro_loco', 'unpli_regional'];

    private const PHASES = [
        'announcement' => -14,  // T-14 giorni
        'reminder'     => -2,   // T-2 giorni
        'recap'        => +1,   // T+1 giorno
    ];

    public function __construct(public readonly int $projectId) {}

    public function handle(EventPostGenerator $generator): void
    {
        $project = Project::with('brand')->findOrFail($this->projectId);
        $brand = $project->brand;

        if (! $brand || ! in_array($brand->vertical ?? null, self::ALLOWED_VERTICALS, true)) {
            Log::info("[TERRITORIAL] Skip project {$project->id}: brand vertical not eligible");
            return;
        }

        // Piattaforme attive del brand (Platform enum cases via cast).
        /** @var array<int, Platform> $activePlatforms */
        $activePlatforms = SocialConnection::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->pluck('platform')
            ->all();

        if (empty($activePlatforms)) {
            // Onboarding-friendly: senza social connessi, generiamo comunque
            // i post come DRAFT sulle piattaforme default. L'utente collega
            // i social e ri-programma quando i draft compaiono nel calendario.
            $defaultPlatformValues = config('territorial.default_platforms', ['linkedin', 'instagram', 'facebook']);
            $platforms = array_map(fn (string $v) => Platform::from($v), $defaultPlatformValues);

            Log::info("[TERRITORIAL] Project {$project->id}: no active social connections — generating drafts on default platforms", [
                'brand_id'  => $brand->id,
                'platforms' => $defaultPlatformValues,
            ]);
        } else {
            $platforms = $activePlatforms;
        }

        // Range temporale del progetto. Default: oggi → fra 30gg.
        $periodStart = $project->start_date?->copy() ?? now()->startOfDay();
        $periodEnd   = $project->end_date?->copy() ?? now()->addDays(30)->endOfDay();

        // Eventi candidati: filtrati dal TerritoryMatcher in base a brand.vertical
        // + brand.territory_meta. Buffer di 14gg a sinistra perché un evento al
        // limite richiede T-14 di annuncio già nel periodo.
        $events = app(TerritoryMatcher::class)->eligibleEvents(
            $brand,
            $periodStart->copy()->subDays(14),
            $periodEnd,
        );

        $platformValues = array_map(fn (Platform $p) => $p->value, $platforms);
        Log::info("[TERRITORIAL] Project {$project->id}: {$events->count()} events in window, platforms=" . implode(',', $platformValues));

        foreach ($events as $event) {
            $this->generatePostsForEvent($event, $project, $brand, $platforms, $generator);
        }
    }

    /**
     * @param  array<int, Platform>  $platforms
     */
    private function generatePostsForEvent(
        TerritorialEvent $event,
        Project $project,
        Brand $brand,
        array $platforms,
        EventPostGenerator $generator,
    ): void {
        if (! $event->start_at) {
            Log::warning("[TERRITORIAL] Event {$event->id} has no start_at, skip");
            return;
        }

        foreach (self::PHASES as $phase => $offsetDays) {
            $scheduledDate = $event->start_at->copy()->addDays($offsetDays);

            foreach ($platforms as $platform) {
                // Idempotency: skip se già generato per questa combinazione
                $exists = TerritorialEventPost::where('territorial_event_id', $event->id)
                    ->where('phase', $phase)
                    ->whereHas('post', fn ($q) => $q
                        ->where('project_id', $project->id)
                        ->where('platform', $platform->value))
                    ->exists();

                if ($exists) {
                    continue;
                }

                try {
                    $content = $generator->generate($event, $brand, $phase, $platform);

                    // Hashtags: il prompt LLM ritorna stringa "#tag1 #tag2 ...".
                    // La colonna posts.hashtags ha cast 'json' → splittiamo in array.
                    $hashtagsArray = array_values(array_filter(
                        preg_split('/\s+/', trim($content['hashtags'] ?? '')) ?: []
                    ));

                    $post = Post::create([
                        'project_id'          => $project->id,
                        'organization_id'     => $project->organization_id,
                        'platform'            => $platform,
                        'post_type'           => PostType::TerritorialEvent->value,
                        'pillar'              => 'Eventi del territorio',
                        'title'               => $content['title'],
                        'content'             => $content['content'],
                        'hashtags'            => $hashtagsArray,
                        'cta'                 => $content['cta'],
                        'call_to_action'      => $content['cta'],
                        'visual_suggestion'   => $event->image_path,
                        'scheduled_date'      => $scheduledDate,
                        'status'              => 'draft',
                        'generation_metadata' => [
                            'source'   => 'territorial',
                            'event_id' => $event->id,
                            'phase'    => $phase,
                            'platform' => $platform->value,
                        ],
                    ]);

                    TerritorialEventPost::create([
                        'territorial_event_id' => $event->id,
                        'post_id'              => $post->id,
                        'phase'                => $phase,
                        'generated_at'         => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error("[TERRITORIAL] Generation failed event={$event->id} phase={$phase} platform={$platform->value}: {$e->getMessage()}");
                }
            }
        }
    }
}
