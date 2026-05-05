<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use App\Domain\Review\Models\Review;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Console\Command;

/**
 * Identifica e cancella brand creati durante i test (factory residui).
 *
 * Pattern di matching:
 *   - LIKE 'Test Brand%'  (es. "Test Brand", "Test Brand 1")
 *   - LIKE 'Brand Test%'  (es. "Brand Test", "Brand Test 5")
 *   - = 'Test'             (safety net)
 *
 * Default: dry-run (mostra solo). --force per eseguire la cancellazione.
 * Idempotente: rilanciato dopo un wet-run riuscito stampa "DB pulito".
 *
 * forceDelete() bypassa SoftDeletes e attiva i FK cascadeOnDelete configurati,
 * pulendo automaticamente projects → posts, reviews, social_connections, brand_documents.
 */
final class CleanupTestBrandsCommand extends Command
{
    protected $signature = 'kalendarium:cleanup-test-brands
        {--force : Esegui davvero la cancellazione (default: dry-run)}';

    protected $description = 'Identifica e cancella brand creati durante i test (factory residui).';

    public function handle(): int
    {
        $candidates = Brand::query()
            ->withoutGlobalScope('organization')
            ->where(function ($q) {
                $q->where('name', 'LIKE', 'Test Brand%')
                    ->orWhere('name', 'LIKE', 'Brand Test%')
                    ->orWhere('name', '=', 'Test');
            })
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Nessun brand test trovato. DB pulito.');
            return self::SUCCESS;
        }

        $this->info("Trovati {$candidates->count()} brand candidati:");
        $this->newLine();

        $rows   = [];
        $totals = ['posts' => 0, 'reviews' => 0, 'connections' => 0, 'documents' => 0];

        foreach ($candidates as $b) {
            // Posts non hanno brand_id diretta: passano via projects → posts.
            $projectIds = Project::query()
                ->withoutGlobalScope('organization')
                ->where('brand_id', $b->id)
                ->pluck('id');
            $posts = $projectIds->isEmpty()
                ? 0
                : Post::query()->withoutGlobalScope('organization')->whereIn('project_id', $projectIds)->count();

            $reviews = Review::query()
                ->withoutGlobalScope('organization')
                ->where('brand_id', $b->id)
                ->count();

            $connections = SocialConnection::query()
                ->withoutGlobalScope('organization')
                ->where('brand_id', $b->id)
                ->count();

            $documents = BrandDocument::query()
                ->where('brand_id', $b->id)
                ->count();

            $rows[] = [
                $b->id,
                mb_substr((string) $b->name, 0, 25),
                $b->organization_id,
                $b->created_at?->format('Y-m-d'),
                $posts,
                $reviews,
                $connections,
                $documents,
            ];

            $totals['posts']       += $posts;
            $totals['reviews']     += $reviews;
            $totals['connections'] += $connections;
            $totals['documents']   += $documents;
        }

        $this->table(
            ['ID', 'Name', 'OrgID', 'Created', 'Posts', 'Reviews', 'Conns', 'Docs'],
            $rows,
        );

        $this->newLine();
        $this->info(sprintf(
            'Totali correlati: %d posts, %d reviews, %d connections, %d documents',
            $totals['posts'],
            $totals['reviews'],
            $totals['connections'],
            $totals['documents'],
        ));

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY RUN. Per eseguire la cancellazione, lancia:');
            $this->line('  php artisan kalendarium:cleanup-test-brands --force');
            return self::SUCCESS;
        }

        $this->newLine();
        if (! $this->confirm('Confermi la cancellazione DEFINITIVA di questi brand e tutte le entity correlate?')) {
            $this->info('Annullato.');
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($candidates as $b) {
            try {
                $b->forceDelete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Errore su brand {$b->id}: {$e->getMessage()}");
            }
        }

        $this->info("Eliminati {$deleted} brand di test.");
        return self::SUCCESS;
    }
}
