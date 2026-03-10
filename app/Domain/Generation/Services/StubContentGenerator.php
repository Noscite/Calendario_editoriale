<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Post\Models\Post;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implementazione stub del generatore di contenuti.
 *
 * Verrà sostituita con ClaudeContentGenerator quando i servizi AI saranno configurati.
 */
final class StubContentGenerator implements ContentGeneratorInterface
{
    public function generateCalendar(Project $project): void
    {
        // Stub — la generazione effettiva verrà implementata con Claude/GPT
    }

    public function generateAiPosts(int $projectId, array $params): Collection
    {
        return new Collection();
    }

    public function regeneratePost(int $postId, ?string $userPrompt = null): Post
    {
        return Post::findOrFail($postId);
    }

    public function generatePersonas(int $projectId): array
    {
        $project = Project::findOrFail($projectId);

        return $project->buyer_personas ?? ['personas' => []];
    }

    public function regeneratePersonas(int $projectId, ?string $feedback = null): array
    {
        $project = Project::findOrFail($projectId);

        return $project->buyer_personas ?? ['personas' => []];
    }

    public function confirmPersonas(int $projectId, ?array $personas = null): array
    {
        return ['status' => 'confirmed'];
    }

    public function getPersonas(int $projectId): array
    {
        $project = Project::findOrFail($projectId);

        return [
            'personas'  => $project->buyer_personas,
            'confirmed' => $project->buyer_personas['confirmed'] ?? false,
        ];
    }

    public function addPersona(int $projectId, ?string $description = null): array
    {
        return ['success' => true, 'persona' => [], 'total_personas' => 0];
    }

    public function deletePersona(int $projectId, int $personaIndex): array
    {
        return ['success' => true, 'deleted' => '', 'remaining_personas' => 0];
    }

    public function regenerateSinglePersona(int $projectId, int $personaIndex, ?string $description = null): array
    {
        return ['success' => true, 'old_persona' => '', 'new_persona' => []];
    }

    public function getGenerationStatus(int $projectId): array
    {
        return [
            'status'        => 'draft',
            'post_count'    => 0,
            'percent'       => 0,
            'current_batch' => 0,
            'total_batches' => 0,
        ];
    }
}
