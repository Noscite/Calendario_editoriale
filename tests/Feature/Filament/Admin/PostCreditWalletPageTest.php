<?php

declare(strict_types=1);

use App\Domain\Subscription\Services\PostCreditService;
use App\Filament\Admin\Pages\PostCreditWallet;
use Livewire\Livewire;

beforeEach(function () {
    [$this->superAdmin, $this->org] = createAuthenticatedUser(['role' => 'superuser']);
});

it('renders and shows zero balance for a fresh organization', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(PostCreditWallet::class)
        ->set('selectedOrganizationId', $this->org->id)
        ->assertSuccessful()
        ->assertSee('Wallet crediti-post')
        ->assertSee('0');
});

it('credits the wallet and reflects the new balance', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(PostCreditWallet::class)
        ->set('selectedOrganizationId', $this->org->id)
        ->set('amount', 50)
        ->set('paymentReference', 'CRO-12345')
        ->call('credit')
        ->assertSuccessful();

    expect(app(PostCreditService::class)->balance($this->org->id))->toBe(50);
});

it('rejects crediting zero or negative amounts without writing to the ledger', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(PostCreditWallet::class)
        ->set('selectedOrganizationId', $this->org->id)
        ->set('amount', 0)
        ->call('credit')
        ->assertSuccessful();

    expect(app(PostCreditService::class)->balance($this->org->id))->toBe(0);
    expect(app(PostCreditService::class)->isWalletEnrolled($this->org->id))->toBeFalse();
});
