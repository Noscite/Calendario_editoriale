<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Services\PostCreditService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin: wallet crediti-post (ricarica manuale + storico movimenti).
 * Pattern gemello di BrandApiSettings: selettore organizzazione + form,
 * niente automazione Stripe in questa fase (ricarica sempre assistita).
 */
class PostCreditWallet extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationLabel = 'Wallet Crediti-Post';
    protected static string|\UnitEnum|null $navigationGroup = 'Piani & Utilizzo';
    protected static ?string $title = 'Wallet crediti-post';
    protected static ?int $navigationSort = 5;

    public function getView(): string
    {
        return 'filament.admin.pages.post-credit-wallet';
    }

    public ?int $selectedOrganizationId = null;

    public int $amount = 0;
    public ?string $paymentReference = null;
    public ?string $note = null;

    public function mount(): void
    {
        $this->selectedOrganizationId = Organization::query()->orderBy('name')->first()?->id;
    }

    public function availableOrganizations()
    {
        return Organization::query()->orderBy('name')->get();
    }

    public function updatedSelectedOrganizationId(): void
    {
        $this->amount = 0;
        $this->paymentReference = null;
        $this->note = null;
    }

    public function balance(): int
    {
        if (! $this->selectedOrganizationId) {
            return 0;
        }

        return app(PostCreditService::class)->balance($this->selectedOrganizationId);
    }

    public function recentMovements()
    {
        if (! $this->selectedOrganizationId) {
            return collect();
        }

        return DB::table('post_credit_ledger')
            ->leftJoin('users', 'users.id', '=', 'post_credit_ledger.created_by_admin_id')
            ->where('post_credit_ledger.organization_id', $this->selectedOrganizationId)
            ->orderByDesc('post_credit_ledger.created_at')
            ->limit(30)
            ->select('post_credit_ledger.*', 'users.full_name as admin_name')
            ->get();
    }

    public function credit(): void
    {
        if (! $this->selectedOrganizationId) {
            return;
        }

        if ($this->amount <= 0) {
            Notification::make()->title('Inserisci una quantità positiva di post')->danger()->send();
            return;
        }

        app(PostCreditService::class)->credit(
            organizationId: $this->selectedOrganizationId,
            amount: $this->amount,
            reason: 'purchase',
            adminId: Auth::id(),
            paymentReference: $this->paymentReference ?: null,
            note: $this->note ?: null,
        );

        Notification::make()->title("Accreditati {$this->amount} post")->success()->send();

        $this->amount = 0;
        $this->paymentReference = null;
        $this->note = null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'superuser';
    }
}
