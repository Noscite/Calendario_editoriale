<?php

declare(strict_types=1);

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Route;

// ── 1. Request-ID presente in ogni response ───────────────────────────────────

test('request_id_is_present_in_response_header', function () {
    $response = $this->getJson('/api/health');

    $response->assertHeader('X-Request-ID');

    $requestId = $response->headers->get('X-Request-ID');
    expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

// ── 2. ValidationException → 422 con formato uniforme ────────────────────────

test('validation_exception_returns_422_uniform_format', function () {
    Route::post('/_test/validation', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name'  => 'required|string',
            'email' => 'required|email',
        ]);
    })->middleware('api');

    $response = $this->postJson('/_test/validation', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'error' => ['code', 'message', 'details'],
        ])
        ->assertJson([
            'error' => ['code' => 'VALIDATION_ERROR'],
        ]);

    // Il vecchio formato flat non deve esistere
    $response->assertJsonMissing(['The name field is required']);
    expect($response->json('error.details'))->toHaveKeys(['name', 'email']);
});

// ── 3. BusinessException → codice e status corretti ──────────────────────────

test('business_exception_returns_correct_code_and_status', function () {
    Route::get('/_test/business-exception', function () {
        throw new BusinessException(
            'Quota superata per questo mese.',
            'QUOTA_EXCEEDED',
            402,
        );
    })->middleware('api');

    $response = $this->getJson('/_test/business-exception');

    $response->assertStatus(402)
        ->assertExactJson([
            'error' => [
                'code'    => 'QUOTA_EXCEEDED',
                'message' => 'Quota superata per questo mese.',
            ],
        ]);
});

// ── 4. Throwable non gestita → JSON pulito, nessun stack trace esposto ────────

test('unhandled_exception_returns_clean_json_in_production', function () {
    Route::get('/_test/unhandled-exception', function () {
        throw new \RuntimeException('Dettaglio interno segreto — non deve apparire al client');
    })->middleware('api');

    $response = $this->getJson('/_test/unhandled-exception');

    $response->assertStatus(500)
        ->assertJsonStructure([
            'error' => ['code', 'message', 'request_id'],
        ])
        ->assertJson([
            'error' => ['code' => 'INTERNAL_ERROR'],
        ]);

    // Il messaggio interno NON deve essere visibile al client
    $body = $response->getContent();
    expect($body)->not->toContain('Dettaglio interno segreto');
    expect($body)->not->toContain('RuntimeException');
    expect($body)->not->toContain('stack');

    // Il request_id deve essere un UUID valido
    $data      = $response->json();
    $requestId = $data['error']['request_id'];
    expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});
