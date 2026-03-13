<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicApi;

use App\Domain\Brand\Models\Brand;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use App\Domain\Social\Models\PostPublication;
use App\Domain\Social\Models\SocialConnection;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Post CRUD + bulk + schedule + publish per Public API v1.
 *
 * Corrisponde a public_api.py → endpoint /posts/*.
 * Auth via X-API-Key + scope (read / write / publish).
 */
final class PostApiController extends Controller
{
    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Verifica che il post appartenga all'organizzazione dell'utente.
     */
    private function verifyPostAccess(Request $request, int $postId): ?Post
    {
        return Post::query()
            ->join('projects', 'posts.project_id', '=', 'projects.id')
            ->join('brands', 'projects.brand_id', '=', 'brands.id')
            ->where('brands.organization_id', $request->user()->organization_id)
            ->where('posts.id', $postId)
            ->select('posts.*')
            ->first();
    }

    /**
     * Verifica che il progetto appartenga all'organizzazione dell'utente.
     */
    private function verifyProjectAccess(Request $request, int $projectId): ?Project
    {
        return Project::query()
            ->join('brands', 'projects.brand_id', '=', 'brands.id')
            ->where('brands.organization_id', $request->user()->organization_id)
            ->where('projects.id', $projectId)
            ->select('projects.*')
            ->first();
    }

    /**
     * PostOut — schema semplificato.
     */
    private function toOut(Post $p): array
    {
        return [
            'id'                 => $p->id,
            'project_id'         => $p->project_id,
            'platform'           => $p->platform?->value ?? $p->getRawOriginal('platform'),
            'scheduled_date'     => $p->scheduled_date?->format('Y-m-d'),
            'scheduled_time'     => $p->scheduled_time,
            'title'              => $p->title,
            'content'            => $p->content,
            'hashtags'           => $p->hashtags,
            'pillar'             => $p->pillar,
            'post_type'          => $p->post_type?->value ?? $p->getRawOriginal('post_type'),
            'content_type'       => $p->content_type?->value ?? $p->getRawOriginal('content_type'),
            'visual_suggestion'  => $p->visual_suggestion,
            'cta'                => $p->cta,
            'call_to_action'     => $p->call_to_action,
            'image_url'          => $p->image_url,
            'is_carousel'        => $p->is_carousel ?? false,
            'status'             => $p->status?->value ?? $p->getRawOriginal('status') ?? 'draft',
            'publication_status' => $p->publication_status?->value ?? $p->getRawOriginal('publication_status'),
            'created_at'         => $p->created_at?->toIso8601String(),
        ];
    }

    // ── READ ────────────────────────────────────────────────────

    /**
     * GET /api/v1/posts
     *
     * Filtrabile per: project_id, brand_id, platform, date_from, date_to, status.
     * Paginazione: limit (default 50, max 200), offset (default 0).
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $query = Post::query()
            ->join('projects', 'posts.project_id', '=', 'projects.id')
            ->join('brands', 'projects.brand_id', '=', 'brands.id')
            ->where('brands.organization_id', $orgId)
            ->select('posts.*');

        // Filtri
        if ($projectId = $request->query('project_id')) {
            $query->where('posts.project_id', (int) $projectId);
        }

        if ($brandId = $request->query('brand_id')) {
            $query->where('brands.id', (int) $brandId);
        }

        if ($platform = $request->query('platform')) {
            $query->where('posts.platform', $platform);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->where('posts.scheduled_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->where('posts.scheduled_date', '<=', $dateTo);
        }

        if ($status = $request->query('status')) {
            $query->where('posts.status', $status);
        }

        $limit  = min((int) ($request->query('limit', 50)), 200);
        $offset = max((int) ($request->query('offset', 0)), 0);

        $posts = $query->orderBy('posts.scheduled_date')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Post $p) => $this->toOut($p));

        return response()->json($posts->values());
    }

    /**
     * GET /api/v1/posts/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $post = $this->verifyPostAccess($request, $id);

        if (! $post) {
            return response()->json(['detail' => 'Post non trovato'], 404);
        }

        return response()->json($this->toOut($post));
    }

    // ── CREATE ──────────────────────────────────────────────────

    /**
     * POST /api/v1/posts
     *
     * Crea un post nel calendario (body JSON, schema PostCreateInput).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'        => 'required|integer',
            'platform'          => 'required|string',
            'scheduled_date'    => 'required|date',
            'scheduled_time'    => 'nullable|string',
            'title'             => 'nullable|string|max:500',
            'content'           => 'required|string',
            'hashtags'          => 'nullable|array',
            'pillar'            => 'nullable|string',
            'post_type'         => 'nullable|string',
            'content_type'      => 'nullable|string',
            'visual_suggestion' => 'nullable|string',
            'cta'               => 'nullable|string',
            'call_to_action'    => 'nullable|string',
        ]);

        $project = $this->verifyProjectAccess($request, (int) $data['project_id']);
        if (! $project) {
            return response()->json(['detail' => 'Progetto non trovato o non accessibile'], 404);
        }

        $post = Post::create([
            'organization_id'    => $project->organization_id,
            'project_id'         => $data['project_id'],
            'platform'           => $data['platform'],
            'scheduled_date'     => $data['scheduled_date'],
            'scheduled_time'     => $data['scheduled_time'] ?? '09:00',
            'title'              => $data['title'] ?? null,
            'content'            => $data['content'],
            'hashtags'           => $data['hashtags'] ?? [],
            'pillar'             => $data['pillar'] ?? null,
            'post_type'          => $data['post_type'] ?? 'educational',
            'content_type'       => $data['content_type'] ?? 'post',
            'visual_suggestion'  => $data['visual_suggestion'] ?? null,
            'cta'                => $data['cta'] ?? null,
            'call_to_action'     => $data['call_to_action'] ?? null,
            'status'             => 'draft',
            'publication_status' => 'draft',
        ]);

        return response()->json($this->toOut($post->fresh()), 201);
    }

    /**
     * POST /api/v1/posts/create
     *
     * Endpoint con parametri espliciti per compatibilità MCP.
     * Stessa logica di store(), accetta gli stessi campi.
     */
    public function createExplicit(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * POST /api/v1/posts/bulk
     *
     * Crea multipli post in una singola chiamata.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'posts'                    => 'required|array|min:1',
            'posts.*.project_id'       => 'required|integer',
            'posts.*.platform'         => 'required|string',
            'posts.*.scheduled_date'   => 'required|date',
            'posts.*.scheduled_time'   => 'nullable|string',
            'posts.*.title'            => 'nullable|string|max:500',
            'posts.*.content'          => 'required|string',
            'posts.*.hashtags'         => 'nullable|array',
            'posts.*.pillar'           => 'nullable|string',
            'posts.*.post_type'        => 'nullable|string',
            'posts.*.content_type'     => 'nullable|string',
            'posts.*.visual_suggestion' => 'nullable|string',
            'posts.*.cta'              => 'nullable|string',
            'posts.*.call_to_action'   => 'nullable|string',
        ]);

        $created = [];

        DB::transaction(function () use ($data, $request, &$created) {
            foreach ($data['posts'] as $postData) {
                $project = $this->verifyProjectAccess($request, (int) $postData['project_id']);
                if (! $project) {
                    abort(404, "Progetto {$postData['project_id']} non trovato o non accessibile");
                }

                $post = Post::create([
                    'organization_id'    => $project->organization_id,
                    'project_id'         => $postData['project_id'],
                    'platform'           => $postData['platform'],
                    'scheduled_date'     => $postData['scheduled_date'],
                    'scheduled_time'     => $postData['scheduled_time'] ?? '09:00',
                    'title'              => $postData['title'] ?? null,
                    'content'            => $postData['content'],
                    'hashtags'           => $postData['hashtags'] ?? [],
                    'pillar'             => $postData['pillar'] ?? null,
                    'post_type'          => $postData['post_type'] ?? 'educational',
                    'content_type'       => $postData['content_type'] ?? 'post',
                    'visual_suggestion'  => $postData['visual_suggestion'] ?? null,
                    'cta'                => $postData['cta'] ?? null,
                    'call_to_action'     => $postData['call_to_action'] ?? null,
                    'status'             => 'draft',
                    'publication_status' => 'draft',
                ]);

                $created[] = $post;
            }
        });

        return response()->json([
            'success'       => true,
            'created_count' => count($created),
            'post_ids'      => array_map(fn ($p) => $p->id, $created),
        ], 201);
    }

    // ── UPDATE ──────────────────────────────────────────────────

    /**
     * PATCH /api/v1/posts/{id}
     *
     * Aggiorna un post esistente (campi opzionali, PostUpdateInput).
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $post = $this->verifyPostAccess($request, $id);

        if (! $post) {
            return response()->json(['detail' => 'Post non trovato'], 404);
        }

        $data = $request->validate([
            'platform'          => 'nullable|string',
            'scheduled_date'    => 'nullable|date',
            'scheduled_time'    => 'nullable|string',
            'title'             => 'nullable|string|max:500',
            'content'           => 'nullable|string',
            'hashtags'          => 'nullable|array',
            'pillar'            => 'nullable|string',
            'post_type'         => 'nullable|string',
            'content_type'      => 'nullable|string',
            'visual_suggestion' => 'nullable|string',
            'cta'               => 'nullable|string',
            'call_to_action'    => 'nullable|string',
        ]);

        // Solo campi esplicitamente inviati (exclude_unset)
        $updateData = array_filter($data, fn ($v) => $v !== null);

        if (! empty($updateData)) {
            $post->update($updateData);
        }

        return response()->json($this->toOut($post->fresh()));
    }

    // ── DELETE ───────────────────────────────────────────────────

    /**
     * DELETE /api/v1/posts/{id}
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $post = $this->verifyPostAccess($request, $id);

        if (! $post) {
            return response()->json(['detail' => 'Post non trovato'], 404);
        }

        $post->delete();

        return response()->json(['success' => true, 'message' => "Post {$id} eliminato"]);
    }

    // ── SCHEDULE ────────────────────────────────────────────────

    /**
     * POST /api/v1/posts/{id}/schedule
     *
     * Schedula un post per la pubblicazione automatica.
     * Query param: platforms[] (opzionale, default = piattaforma del post).
     */
    public function schedule(int $id, Request $request): JsonResponse
    {
        $post = $this->verifyPostAccess($request, $id);

        if (! $post) {
            return response()->json(['detail' => 'Post non trovato'], 404);
        }

        // Carica relazioni necessarie
        $post->load('project.brand');
        $brand = $post->project->brand;

        $targetPlatforms = $request->query('platforms')
            ? (array) $request->query('platforms')
            : [$post->platform?->value ?? $post->getRawOriginal('platform')];

        $scheduled = [];

        foreach ($targetPlatforms as $platform) {
            $connection = SocialConnection::where('brand_id', $brand->id)
                ->where('platform', $platform)
                ->where('is_active', true)
                ->first();

            if (! $connection) {
                return response()->json([
                    'detail' => "Nessuna connessione attiva per {$platform} sul brand {$brand->name}",
                ], 400);
            }

            $scheduledTime = Carbon::parse(
                $post->scheduled_date->format('Y-m-d') . ' ' . ($post->scheduled_time ?? '09:00'),
                'UTC'
            );

            PostPublication::create([
                'post_id'              => $post->id,
                'social_connection_id' => $connection->id,
                'platform'             => $platform,
                'status'               => 'scheduled',
                'scheduled_for'        => $scheduledTime,
            ]);

            $scheduled[] = $platform;
        }

        $post->update(['publication_status' => 'scheduled']);

        return response()->json(['success' => true, 'scheduled_platforms' => $scheduled]);
    }

    /**
     * POST /api/v1/posts/{id}/publish
     *
     * Pubblica un post immediatamente sulle piattaforme connesse.
     */
    public function publish(int $id, Request $request): JsonResponse
    {
        $post = $this->verifyPostAccess($request, $id);

        if (! $post) {
            return response()->json(['detail' => 'Post non trovato'], 404);
        }

        $post->load('project.brand');
        $brand = $post->project->brand;

        $platform = $post->platform?->value ?? $post->getRawOriginal('platform');

        $connection = SocialConnection::where('brand_id', $brand->id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            return response()->json([
                'detail' => "Nessuna connessione {$platform} attiva",
            ], 400);
        }

        // TODO: integrare PublisherService quando disponibile
        // Per ora crea il record di pubblicazione come "publishing"
        $publication = PostPublication::create([
            'post_id'              => $post->id,
            'social_connection_id' => $connection->id,
            'platform'             => $platform,
            'status'               => 'publishing',
            'scheduled_for'        => now(),
        ]);

        $post->update(['publication_status' => 'publishing']);

        return response()->json([
            'success'        => true,
            'message'        => "Pubblicazione avviata per {$platform}",
            'publication_id' => $publication->id,
        ]);
    }
}
