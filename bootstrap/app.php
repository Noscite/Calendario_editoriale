<?php

use App\Exceptions\BusinessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Assegna X-Request-ID a ogni request — deve essere primo nella catena
        $middleware->append(\App\Http\Middleware\AssignRequestId::class);

        // Sovrascrive il default route('login') — questa è un'API pura, nessuna route login
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'api.key'              => \App\Http\Middleware\PublicApiAuth::class,
            'ensure.organization'  => \App\Http\Middleware\EnsureOrganization::class,
            'check.limits'         => \App\Http\Middleware\CheckSubscriptionLimits::class,
            'subscription.active'  => \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // BusinessException è già segnalata via report() nel controller chiamante.
        // Non serve re-inviarla a Sentry una seconda volta.
        $exceptions->dontReport([BusinessException::class]);

        // ── BusinessException — logica applicativa nota ──────────────────────
        // Il controller ha già chiamato report($originalException) prima di lanciare questa.
        $exceptions->render(function (BusinessException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code'    => $e->errorCode,
                    'message' => $e->publicMessage,
                ],
            ], $e->httpStatus);
        });

        // ── ValidationException → 422 ────────────────────────────────────────
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'error'  => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'I dati forniti non sono validi.',
                    'details' => $e->errors(),
                ],
                // Chiave 'errors' per compatibilità con assertJsonValidationErrors di Laravel
                'errors' => $e->errors(),
            ], 422);
        });

        // ── AuthenticationException → 401 ────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code'    => 'UNAUTHORIZED',
                    'message' => 'Non autenticato.',
                ],
            ], 401);
        });

        // ── AuthorizationException → 403 ─────────────────────────────────────
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'Non hai i permessi per questa operazione.',
                ],
            ], 403);
        });

        // ── ModelNotFoundException → 404 ──────────────────────────────────────
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Risorsa non trovata.',
                ],
            ], 404);
        });

        // ── NotFoundHttpException → 404 (route inesistente) ──────────────────
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Endpoint non trovato.',
                ],
            ], 404);
        });

        // ── ThrottleRequestsException → 429 ──────────────────────────────────
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code'    => 'RATE_LIMITED',
                    'message' => 'Troppi tentativi. Riprova tra qualche minuto.',
                ],
            ], 429);
        });

        // ── Fallback generico — qualsiasi Throwable non gestita ──────────────
        // Il messaggio interno non viene mai esposto al client.
        // Sentry riceve l'eccezione originale tramite il meccanismo di report() di Laravel.
        $exceptions->render(function (\Throwable $e, Request $request) {
            $requestId = $request->attributes->get('request_id', Str::uuid()->toString());

            return response()->json([
                'error' => [
                    'code'       => 'INTERNAL_ERROR',
                    'message'    => 'Si è verificato un errore. Il team è stato notificato.',
                    'request_id' => $requestId,
                ],
            ], 500);
        });
    })->create();
