<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Post\Models\Post;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Galleria immagini a livello di organizzazione, con ricerca e filtri.
 *
 * "Indicizzazione": ogni immagine eredita i metadati testuali del suo post
 * (pillar = argomento, visual_suggestion / image_prompt = cosa raffigura,
 * content = testo del post, hashtags). La ricerca fa match case-insensitive su
 * questi campi; i filtri per argomento (pillar) e piattaforma restringono ancora.
 *
 * Tenancy: Post usa BelongsToOrganization → global scope sull'org dell'utente.
 * Le faccette (elenco argomenti/piattaforme per i menu) sono ricavate con una
 * query esplicitamente filtrata per organization_id, così restano isolate.
 */
class GalleryController extends Controller
{
    /**
     * GET /api/gallery?q=&pillar=&platform=&limit=
     */
    public function index(Request $request): JsonResponse
    {
        $limit    = min((int) $request->integer('limit', 200), 500);
        $q        = trim((string) $request->query('q', ''));
        $pillar   = trim((string) $request->query('pillar', ''));
        $platform = trim((string) $request->query('platform', ''));

        $query = Post::query()->whereNotNull('image_url');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('content', 'ILIKE', $like)
                    ->orWhere('visual_suggestion', 'ILIKE', $like)
                    ->orWhere('image_prompt', 'ILIKE', $like)
                    ->orWhere('pillar', 'ILIKE', $like)
                    ->orWhereRaw('CAST(hashtags AS TEXT) ILIKE ?', [$like]);
            });
        }

        if ($pillar !== '') {
            $query->where('pillar', $pillar);
        }

        if ($platform !== '') {
            $query->where('platform', $platform);
        }

        $posts = $query
            ->orderByDesc('created_at')
            ->limit(600)
            ->get(['id', 'image_url', 'carousel_images', 'visual_suggestion', 'image_prompt', 'pillar', 'platform', 'created_at']);

        $seen   = [];
        $images = [];

        foreach ($posts as $post) {
            $carousel = is_array($post->carousel_images) ? $post->carousel_images : [];
            $urls     = array_merge([$post->image_url], $carousel);

            foreach ($urls as $url) {
                if (! is_string($url) || $url === '' || isset($seen[$url]) || str_starts_with($url, 'data:')) {
                    continue;
                }

                $seen[$url] = true;
                $images[]   = [
                    'url'        => $url,
                    'post_id'    => $post->id,
                    'prompt'     => $post->visual_suggestion ?: $post->image_prompt,
                    'pillar'     => $post->pillar,
                    'platform'   => $this->enumValue($post->platform),
                    'created_at' => optional($post->created_at)->toIso8601String(),
                ];

                if (count($images) >= $limit) {
                    break 2;
                }
            }
        }

        return response()->json([
            'images' => $images,
            'facets' => $this->facets(),
        ]);
    }

    /**
     * Elenco argomenti (pillar) e piattaforme presenti nella galleria dell'org,
     * per popolare i menu dei filtri. Filtro org esplicito → isolamento garantito.
     */
    private function facets(): array
    {
        $orgId = Auth::user()->organization_id;

        $pillars = DB::table('posts')
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->whereNotNull('image_url')
            ->whereNotNull('pillar')
            ->where('pillar', '<>', '')
            ->distinct()
            ->orderBy('pillar')
            ->pluck('pillar')
            ->values();

        $platforms = DB::table('posts')
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->whereNotNull('image_url')
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform')
            ->values();

        return ['pillars' => $pillars, 'platforms' => $platforms];
    }

    private function enumValue(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return is_object($v) ? ($v->value ?? null) : (string) $v;
    }
}
