<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key'             => \App\Http\Middleware\PublicApiAuth::class,
            'ensure.organization' => \App\Http\Middleware\EnsureOrganization::class,
            'check.limits'        => \App\Http\Middleware\CheckSubscriptionLimits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Restituisce 401 JSON per tutte le richieste API non autenticate
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['detail' => 'Non autenticato.'], 401);
        });
    })->create();
