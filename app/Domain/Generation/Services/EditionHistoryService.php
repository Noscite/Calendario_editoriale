<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;

/**
 * Builds history context from previous editions for AI prompt injection.
 *
 * When generating a new edition, this service collects a summary of
 * all previous editions (posts, themes, pillars, top content) so that
 * the AI can ensure continuity and avoid repetition.
 */
final class EditionHistoryService
{
    /**
     * Maximum posts to sample per previous edition.
     */
    private const MAX_POSTS_PER_EDITION = 30;

    /**
     * Build a history context string for the given project.
     * Returns empty string if the project is not an edition or has no siblings.
     */
    public function buildContext(Project $project): string
    {
        if (! $project->isEdition()) {
            return '';
        }

        // Get all previous editions (same parent, lower edition_number)
        $previousEditions = Project::withoutGlobalScope('organization')
            ->where('parent_project_id', $project->parent_project_id)
            ->where('edition_number', '<', $project->edition_number)
            ->orderBy('edition_number')
            ->get();

        if ($previousEditions->isEmpty()) {
            return '';
        }

        $sections = [];

        foreach ($previousEditions as $edition) {
            $posts = Post::withoutGlobalScope('organization')
                ->where('project_id', $edition->id)
                ->orderBy('scheduled_date')
                ->limit(self::MAX_POSTS_PER_EDITION)
                ->get();

            if ($posts->isEmpty()) {
                continue;
            }

            $editionLabel = "Edizione {$edition->edition_number}: {$edition->name}";
            $period = $edition->start_date?->format('Y-m-d') . ' → ' . $edition->end_date?->format('Y-m-d');

            // Collect themes/pillars used
            $pillars = $posts->pluck('pillar')->filter()->unique()->values()->toArray();
            $platforms = $posts->pluck('platform')->map(fn ($p) => $p instanceof \BackedEnum ? $p->value : (string) $p)->unique()->values()->toArray();

            // Top content samples (first 5 posts)
            $samples = $posts->take(5)->map(function (Post $p) {
                $platform = $p->platform instanceof \BackedEnum ? $p->platform->value : (string) $p->platform;
                $content = mb_substr($p->content ?? '', 0, 150);
                return "  - [{$platform}] {$p->scheduled_date?->format('Y-m-d')}: {$content}";
            })->implode("\n");

            $sections[] = <<<SECTION
### {$editionLabel}
Periodo: {$period}
Post totali: {$posts->count()}
Piattaforme: {$this->implodeList($platforms)}
Pillar usati: {$this->implodeList($pillars)}
Esempi contenuti:
{$samples}
SECTION;
        }

        if (empty($sections)) {
            return '';
        }

        return "## STORICO EDIZIONI PRECEDENTI\n\n" . implode("\n\n", $sections);
    }

    private function implodeList(array $items): string
    {
        return !empty($items) ? implode(', ', $items) : 'N/A';
    }
}
