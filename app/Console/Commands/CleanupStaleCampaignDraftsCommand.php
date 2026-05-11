<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use Illuminate\Console\Command;

/**
 * Elimina le Campaign in stato Draft più vecchie di 24h.
 *
 * Le Draft vengono create lazy quando l'utente espande KB/MCP nel
 * QuickAddPostModal (tab Campagna AI) ma poi può chiudere il modal
 * senza submit. Senza pulizia, ogni espansione lascia una Draft
 * orfana nel DB.
 *
 * Schedulato ogni notte alle 03:00 in routes/console.php.
 */
final class CleanupStaleCampaignDraftsCommand extends Command
{
    protected $signature   = 'campaigns:cleanup-stale-drafts {--hours=24 : Età massima delle Draft in ore}';
    protected $description = 'Elimina campaign in stato Draft più vecchie di N ore (default 24h).';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        // Bypass del global scope BelongsToOrganization: il comando viene
        // eseguito senza utente autenticato, e dobbiamo poter agire su
        // TUTTE le org.
        $count = Campaign::withoutGlobalScope('organization')
            ->where('status', CampaignStatus::Draft)
            ->where('updated_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("Eliminate {$count} Draft campaign più vecchie di {$hours}h.");

        return self::SUCCESS;
    }
}
