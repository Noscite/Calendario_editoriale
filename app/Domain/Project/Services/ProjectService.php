<?php

declare(strict_types=1);

namespace App\Domain\Project\Services;

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Post\Contracts\PostRepositoryInterface;
use App\Domain\Project\Contracts\ProjectRepositoryInterface;
use App\Domain\Project\Contracts\ProjectServiceInterface;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class ProjectService implements ProjectServiceInterface
{
    /**
     * Transizioni di stato valide: stato_corrente => [stati_disponibili]
     */
    private const array STATUS_TRANSITIONS = [
        'draft' => ['generating'],
        'generating' => ['review', 'draft'],  // draft è fallback su errore
        'review' => ['approved', 'draft'],     // draft per ri-generazione
        'approved' => ['published', 'review'],
        'published' => ['review'],             // per revisione post-pubblicazione
    ];

    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    public function listByBrand(int $brandId): Collection
    {
        return $this->projectRepository->findByBrand($brandId);
    }

    public function getById(int $id): Project
    {
        return $this->projectRepository->findOrFail($id);
    }

    public function create(array $data): Project
    {
        // Verifica che il brand esista
        $this->brandRepository->findOrFail($data['brand_id']);

        $data['status'] = ProjectStatus::Draft;

        return $this->projectRepository->create($data);
    }

    public function update(int $projectId, array $data): Project
    {
        $project = $this->projectRepository->findOrFail($projectId);

        // Se si sta aggiornando lo stato, valida la transizione
        if (isset($data['status'])) {
            $newStatus = $data['status'] instanceof ProjectStatus
                ? $data['status']
                : ProjectStatus::from($data['status']);

            $this->validateStatusTransition($project, $newStatus);
            $data['status'] = $newStatus;
        }

        return $this->projectRepository->update($project, $data);
    }

    public function updateStatus(int $projectId, ProjectStatus $status): Project
    {
        $project = $this->projectRepository->findOrFail($projectId);

        $this->validateStatusTransition($project, $status);

        return $this->projectRepository->updateStatus($project, $status);
    }

    public function delete(int $projectId): void
    {
        $project = $this->projectRepository->findOrFail($projectId);

        $this->projectRepository->delete($project);
    }

    public function duplicate(int $projectId): Project
    {
        $original = $this->projectRepository->findOrFail($projectId);

        $data = $original->replicate()->only([
            'brand_id', 'name', 'start_date', 'end_date',
            'platforms', 'posts_per_week', 'themes', 'brief',
            'custom_prompt', 'reference_urls', 'target_audience',
            'objectives', 'content_pillars', 'competitors',
            'special_dates', 'buyer_personas',
        ]);

        $data['name'] = $data['name'] . ' (copia)';
        $data['status'] = ProjectStatus::Draft;

        return $this->projectRepository->create($data);
    }

    public function exportToExcel(int $projectId): string
    {
        // L'export Excel sarà implementato con maatwebsite/excel o PhpSpreadsheet.
        // Per ora restituiamo il path come segnaposto.
        throw new \RuntimeException('Export Excel non ancora implementato. Usare un Export dedicato.');
    }

    // ---------------------------------------------------------------
    //  Logica interna
    // ---------------------------------------------------------------

    /**
     * Valida che la transizione di stato sia consentita.
     *
     * @throws ValidationException se la transizione non è valida
     */
    private function validateStatusTransition(Project $project, ProjectStatus $newStatus): void
    {
        $currentValue = $project->status instanceof ProjectStatus
            ? $project->status->value
            : ($project->status ?? 'draft');

        $allowedTransitions = self::STATUS_TRANSITIONS[$currentValue] ?? [];

        if (! in_array($newStatus->value, $allowedTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Transizione non valida: {$currentValue} → {$newStatus->value}. "
                    . 'Transizioni consentite: ' . implode(', ', $allowedTransitions) . '.',
                ],
            ]);
        }
    }
}
