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
use Illuminate\Support\Facades\Storage;

class GenerateTerritorialPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    private const ALLOWED_VERTICALS = ['pro_loco', 'unpli_regional'];

    private const PHASES = [
        'announcement' => -3,   // T-3 giorni: anticipo concreto, vicino all'evento
        'recap'        => +1,   // T+1 giorno: ringraziamento + invito ai prossimi
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
        // + brand.territory_meta. Buffer di 3gg a sinistra per coprire eventi al
        // limite con T-3 anticipo, +1gg a destra per coprire eventi che finiscono
        // l'ultimo giorno del project con T+1 nel giorno successivo.
        $events = app(TerritoryMatcher::class)->eligibleEvents(
            $brand,
            $periodStart->copy()->subDays(3),
            $periodEnd->copy()->addDays(1),
        );

        $platformValues = array_map(fn (Platform $p) => $p->value, $platforms);
        Log::info("[TERRITORIAL] Project {$project->id}: {$events->count()} events in window, platforms=" . implode(',', $platformValues));

        foreach ($events as $event) {
            $this->generatePostsForEvent($event, $project, $brand, $platforms, $generator);
        }

        // Monthly digest aggregato: 1 post per ogni primo del mese che cade
        // nel range del project, per ogni piattaforma. Idempotente.
        $this->generateMonthlyDigests($events, $project, $brand, $platforms, $generator);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TerritorialEvent>  $events
     * @param  array<int, Platform>  $platforms
     */
    private function generateMonthlyDigests(
        $events,
        Project $project,
        Brand $brand,
        array $platforms,
        EventPostGenerator $generator,
    ): void {
        $periodStart = $project->start_date?->copy() ?? now()->startOfDay();
        $periodEnd   = $project->end_date?->copy() ?? now()->addDays(30)->endOfDay();

        // Cursor sul primo del mese. Se la data di inizio è già il 1° del mese
        // lo usiamo; altrimenti saltiamo al 1° del mese successivo (non si
        // pubblica un digest retroattivo).
        $cursor = $periodStart->copy()->startOfMonth();
        if ($cursor->lt($periodStart)) {
            $cursor->addMonth();
        }

        while ($cursor->lte($periodEnd)) {
            $monthStart = $cursor->copy();
            $monthEnd   = $cursor->copy()->endOfMonth();

            // Eventi che intersectano il mese (compresi multi-mese in corso)
            $monthEvents = $events->filter(function (TerritorialEvent $e) use ($monthStart, $monthEnd) {
                if (! $e->start_at) {
                    return false;
                }
                $eventEnd = $e->end_at ?: $e->start_at;
                return $e->start_at->lte($monthEnd) && $eventEnd->gte($monthStart);
            });

            if ($monthEvents->isEmpty()) {
                $cursor->addMonth();
                continue;
            }

            // Copertina del digest: prima locandina disponibile fra gli eventi
            // del mese (ordinati per data inizio). Null se nessuno ha image_path.
            $coverImagePath = $monthEvents
                ->filter(fn (TerritorialEvent $e) => ! empty($e->image_path))
                ->sortBy('start_at')
                ->first()
                ?->image_path;

            foreach ($platforms as $platform) {
                // Idempotency: 1 digest per (project, month, platform)
                $exists = Post::where('project_id', $project->id)
                    ->where('platform', $platform->value)
                    ->where('post_type', PostType::TerritorialMonthlyDigest->value)
                    ->whereDate('scheduled_date', $monthStart->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                try {
                    $content = $generator->generateMonthlyDigest($monthEvents, $brand, $monthStart, $platform);

                    $hashtagsArray = array_values(array_filter(
                        preg_split('/\s+/', trim($content['hashtags'] ?? '')) ?: []
                    ));

                    $digestPayload = [
                        'project_id'          => $project->id,
                        'organization_id'     => $project->organization_id,
                        'platform'            => $platform,
                        'post_type'           => PostType::TerritorialMonthlyDigest->value,
                        'pillar'              => 'Eventi del territorio',
                        'title'               => $content['title'],
                        'content'             => $content['content'],
                        'hashtags'            => $hashtagsArray,
                        'cta'                 => $content['cta'] ?? null,
                        'call_to_action'      => $content['cta'] ?? null,
                        'scheduled_date'      => $monthStart,
                        'status'              => 'draft',
                        'generation_metadata' => [
                            'source'       => 'territorial_monthly_digest',
                            'month'        => $monthStart->format('Y-m'),
                            'event_count'  => $monthEvents->count(),
                            'event_ids'    => $monthEvents->pluck('id')->values()->all(),
                            'generated_at' => now()->toIso8601String(),
                        ],
                    ];
                    if ($coverImagePath) {
                        $digestPayload['image_url']  = Storage::disk('public')->url($coverImagePath);
                        $digestPayload['media_type'] = 'image';
                    }

                    Post::create($digestPayload);

                    Log::info('[TERRITORIAL] Monthly digest generated', [
                        'project_id'  => $project->id,
                        'month'       => $monthStart->format('Y-m'),
                        'platform'    => $platform->value,
                        'event_count' => $monthEvents->count(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[TERRITORIAL] Monthly digest generation failed', [
                        'project_id' => $project->id,
                        'month'      => $monthStart->format('Y-m'),
                        'platform'   => $platform->value,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $cursor->addMonth();
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

                    $postPayload = [
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
                        // Niente AI image gen: usiamo la locandina ufficiale dell'evento
                        // se disponibile, altrimenti immagine assente (l'editor può aggiungerla).
                        'visual_suggestion'   => null,
                        'scheduled_date'      => $scheduledDate,
                        'status'              => 'draft',
                        'generation_metadata' => [
                            'source'   => 'territorial',
                            'event_id' => $event->id,
                            'phase'    => $phase,
                            'platform' => $platform->value,
                        ],
                    ];
                    if ($event->image_path) {
                        $postPayload['image_url']  = Storage::disk('public')->url($event->image_path);
                        $postPayload['media_type'] = 'image';
                    }
                    // Quando manca image_path lasciamo invariati i default Post
                    // (media_type='image' come attribute default). image_url
                    // resta null (è già nullable).

                    $post = Post::create($postPayload);

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
