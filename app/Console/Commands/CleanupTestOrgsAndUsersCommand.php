<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use Illuminate\Console\Command;

/**
 * Identifica e cancella organizations e users creati durante i test.
 *
 * Pattern matching:
 *   - Organizations: name LIKE 'Test Org%' OR name = 'Test'
 *   - Users vincolati a quelle org (escluso role=superuser)
 *   - Users con email/full_name pattern factory in altre org SENZA brand reali
 *
 * Safety net (mai cancellare):
 *   - Organizations is_system_tenant=true
 *   - Users role='superuser'
 *   - Organizations con almeno 1 brand reale (name NOT LIKE 'Test Brand%' AND NOT LIKE 'Brand Test%')
 *
 * Default: dry-run. --force per eseguire la cancellazione (con conferma).
 *
 * users.organization_id ha nullOnDelete, NON cascade: cancellare prima
 * gli user, poi le organizations.
 */
final class CleanupTestOrgsAndUsersCommand extends Command
{
    protected $signature = 'kalendarium:cleanup-test-orgs-users
        {--force : Esegui davvero la cancellazione (default: dry-run)}';

    protected $description = 'Identifica e cancella organizations e users creati durante i test (factory residui).';

    public function handle(): int
    {
        // ── Candidate organizations ────────────────────────────────
        $candidateOrgs = Organization::query()
            ->withoutGlobalScope('organization')
            ->where(function ($q) {
                $q->where('name', 'LIKE', 'Test Org%')
                    ->orWhere('name', '=', 'Test');
            })
            ->where(function ($q) {
                $q->where('is_system_tenant', false)
                    ->orWhereNull('is_system_tenant');
            })
            ->orderBy('id')
            ->get();

        // Filtra org con almeno un brand reale (safety net)
        $candidateOrgs = $candidateOrgs->filter(function (Organization $org): bool {
            $realBrands = Brand::query()
                ->withoutGlobalScope('organization')
                ->where('organization_id', $org->id)
                ->where('name', 'NOT LIKE', 'Test Brand%')
                ->where('name', 'NOT LIKE', 'Brand Test%')
                ->where('name', '!=', 'Test')
                ->count();
            return $realBrands === 0;
        })->values();

        $candidateOrgIds = $candidateOrgs->pluck('id')->all();

        // ── Candidate users (in org test) ──────────────────────────
        $candidateUsers = User::query()
            ->whereIn('organization_id', $candidateOrgIds)
            ->where('role', '!=', 'superuser')
            ->orderBy('id')
            ->get();

        // ── Pattern users (in altre org, factory-style) ────────────
        $patternUsers = User::query()
            ->where(function ($q) {
                $q->where('email', 'LIKE', '%@example.com')
                    ->orWhere('email', 'LIKE', '%test%@%')
                    ->orWhere('email', 'LIKE', 'user_%@%')
                    ->orWhere('full_name', 'LIKE', 'Test User%');
            })
            ->where('role', '!=', 'superuser')
            ->whereNotIn('id', $candidateUsers->pluck('id')->all())
            ->orderBy('id')
            ->get();

        $patternUsers = $patternUsers->filter(function (User $user): bool {
            if (! $user->organization_id) {
                return true;
            }
            $org = Organization::query()
                ->withoutGlobalScope('organization')
                ->find($user->organization_id);
            if (! $org) {
                return true;
            }
            if ($org->is_system_tenant) {
                return false;
            }
            $realBrands = Brand::query()
                ->withoutGlobalScope('organization')
                ->where('organization_id', $org->id)
                ->where('name', 'NOT LIKE', 'Test Brand%')
                ->where('name', 'NOT LIKE', 'Brand Test%')
                ->where('name', '!=', 'Test')
                ->count();
            return $realBrands === 0;
        })->values();

        $allCandidateUsers = $candidateUsers->merge($patternUsers);

        if ($candidateOrgs->isEmpty() && $allCandidateUsers->isEmpty()) {
            $this->info('Nessun residuo di test trovato. DB pulito.');
            return self::SUCCESS;
        }

        $this->info("Candidate organizations: {$candidateOrgs->count()}");
        $this->info("Candidate users: {$allCandidateUsers->count()}");
        $this->newLine();

        if ($candidateOrgs->isNotEmpty()) {
            $this->info('=== ORGANIZATIONS DA CANCELLARE ===');
            $orgRows = $candidateOrgs->map(fn (Organization $o): array => [
                $o->id,
                mb_substr((string) $o->name, 0, 30),
                $o->plan_id ?? '-',
                $o->created_at?->format('Y-m-d'),
                User::query()->where('organization_id', $o->id)->count(),
            ])->all();

            $this->table(
                ['ID', 'Name', 'Plan', 'Created', 'Users'],
                $orgRows,
            );
        }

        if ($allCandidateUsers->isNotEmpty()) {
            $this->newLine();
            $this->info('=== USERS DA CANCELLARE ===');
            $userRows = $allCandidateUsers->map(fn (User $u): array => [
                $u->id,
                mb_substr((string) $u->email, 0, 35),
                mb_substr((string) ($u->full_name ?? ''), 0, 25),
                $u->organization_id ?? '-',
                $u->role ?? '-',
                $u->auth_provider ?? '-',
                $u->created_at?->format('Y-m-d'),
            ])->all();

            $this->table(
                ['ID', 'Email', 'Full Name', 'OrgID', 'Role', 'Auth', 'Created'],
                $userRows,
            );
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY RUN. Per cancellare:');
            $this->line('  php artisan kalendarium:cleanup-test-orgs-users --force');
            return self::SUCCESS;
        }

        $this->newLine();
        if (! $this->confirm('Confermi cancellazione DEFINITIVA?')) {
            $this->info('Annullato.');
            return self::SUCCESS;
        }

        // FK su users.organization_id è nullOnDelete: cancello prima gli user.
        $userDeleted = 0;
        foreach ($allCandidateUsers as $u) {
            try {
                $u->forceDelete();
                $userDeleted++;
            } catch (\Throwable $e) {
                $this->error("Errore user {$u->id}: {$e->getMessage()}");
            }
        }

        $orgDeleted = 0;
        foreach ($candidateOrgs as $o) {
            try {
                $o->forceDelete();
                $orgDeleted++;
            } catch (\Throwable $e) {
                $this->error("Errore org {$o->id}: {$e->getMessage()}");
            }
        }

        $this->info("Eliminati {$userDeleted} users e {$orgDeleted} organizations.");
        return self::SUCCESS;
    }
}
