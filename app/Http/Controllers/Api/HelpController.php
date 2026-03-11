<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Help\Models\HelpArticle;
use App\Domain\Help\Models\HelpCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class HelpController extends Controller
{
    // GET /api/help/categories
    public function categories(): JsonResponse
    {
        $categories = HelpCategory::where('is_active', true)
            ->withCount(['articles' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'slug'           => $c->slug,
                'title'          => $c->title,
                'description'    => $c->description,
                'icon'           => $c->icon,
                'articles_count' => $c->articles_count,
            ]);

        return response()->json($categories);
    }

    // GET /api/help/articles?category=slug&featured=true&q=query
    public function articles(Request $request): JsonResponse
    {
        $query = HelpArticle::where('is_active', true)->with('category');

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($q = $request->query('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'ilike', "%{$q}%")
                   ->orWhere('excerpt', 'ilike', "%{$q}%")
                   ->orWhere('content', 'ilike', "%{$q}%");
            });
        }

        $articles = $query->orderBy('sort_order')
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'slug'        => $a->slug,
                'title'       => $a->title,
                'excerpt'     => $a->excerpt,
                'is_featured' => $a->is_featured,
                'views'       => $a->views,
                'category'    => [
                    'slug'  => $a->category->slug,
                    'title' => $a->category->title,
                    'icon'  => $a->category->icon,
                ],
            ]);

        return response()->json($articles);
    }

    // GET /api/help/articles/{slug}
    public function article(string $slug): JsonResponse
    {
        $article = HelpArticle::where('slug', $slug)
            ->where('is_active', true)
            ->with('category')
            ->firstOrFail();

        $article->increment('views');

        // Related articles from same category
        $related = HelpArticle::where('help_category_id', $article->help_category_id)
            ->where('id', '!=', $article->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(4)
            ->get()
            ->map(fn ($a) => [
                'slug'    => $a->slug,
                'title'   => $a->title,
                'excerpt' => $a->excerpt,
            ]);

        return response()->json([
            'id'          => $article->id,
            'slug'        => $article->slug,
            'title'       => $article->title,
            'content'     => $article->content,
            'excerpt'     => $article->excerpt,
            'is_featured' => $article->is_featured,
            'views'       => $article->views,
            'category'    => [
                'slug'  => $article->category->slug,
                'title' => $article->category->title,
                'icon'  => $article->category->icon,
            ],
            'related' => $related,
        ]);
    }

    // GET /api/help/search?q=query
    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q', '');

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $articles = HelpArticle::where('is_active', true)
            ->where(function ($qb) use ($q) {
                $qb->where('title', 'ilike', "%{$q}%")
                   ->orWhere('excerpt', 'ilike', "%{$q}%")
                   ->orWhere('content', 'ilike', "%{$q}%");
            })
            ->with('category')
            ->orderBy('sort_order')
            ->limit(10)
            ->get()
            ->map(function ($a) use ($q) {
                // Build snippet with match context
                $snippet = $a->excerpt ?? '';
                if (stripos($a->content, $q) !== false) {
                    $pos = max(0, stripos($a->content, $q) - 60);
                    $snippet = mb_substr($a->content, $pos, 180);
                    $snippet = ($pos > 0 ? '...' : '') . trim(strip_tags($snippet)) . '...';
                }

                return [
                    'slug'     => $a->slug,
                    'title'    => $a->title,
                    'snippet'  => $snippet,
                    'category' => [
                        'slug'  => $a->category->slug,
                        'title' => $a->category->title,
                    ],
                ];
            });

        return response()->json($articles);
    }
}
