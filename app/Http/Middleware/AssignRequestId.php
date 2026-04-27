<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::uuid()->toString();
        $request->attributes->set('request_id', $requestId);

        \Sentry\configureScope(function (Scope $scope) use ($requestId): void {
            $scope->setTag('request_id', $requestId);
        });

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
