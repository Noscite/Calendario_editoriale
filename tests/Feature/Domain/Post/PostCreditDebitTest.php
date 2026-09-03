<?php

declare(strict_types=1);

use App\Domain\Subscription\Services\PostCreditService;

beforeEach(function () {
    [, $this->org] = createAuthenticatedUser();
    $this->service = app(PostCreditService::class);
    $this->brand = createBrand($this->org);
    $this->project = createProject($this->brand);
});

it('debits 1 credit when an AI-generated post is created via Post::create', function () {
    $this->service->credit($this->org->id, 10, 'purchase');

    createPost($this->project, ['generation_metadata' => ['source' => 'ai', 'model_copy' => 'claude-opus-4-8']]);

    expect($this->service->balance($this->org->id))->toBe(9);
});

it('does not debit for a manually-created post (no generation_metadata)', function () {
    $this->service->credit($this->org->id, 10, 'purchase');

    createPost($this->project); // nessun generation_metadata

    expect($this->service->balance($this->org->id))->toBe(10);
});

it('debiting alone never auto-enrolls an organization into the wallet gate', function () {
    // Nessun credit() mai chiamato: creare post AI-generated scrive comunque
    // debiti nel ledger (reason=consumption), ma questo NON deve far scattare
    // isWalletEnrolled/hasSufficientCredit — altrimenti la primissima
    // generazione di QUALSIASI organizzazione la porterebbe a saldo negativo
    // e la bloccherebbe alla generazione successiva.
    createPost($this->project, ['generation_metadata' => ['source' => 'ai']]);
    createPost($this->project, ['generation_metadata' => ['source' => 'ai']]);

    expect($this->service->isWalletEnrolled($this->org->id))->toBeFalse();
    expect($this->service->hasSufficientCredit($this->org->id, 100))->toBeTrue();
});
