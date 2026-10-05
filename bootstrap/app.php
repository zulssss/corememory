<?php

use App\Http\Middleware\NoIndexWhenRequested;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every response, admin included. Off unless SITE_NOINDEX=true.
        $middleware->append(NoIndexWhenRequested::class);

        // Behind a load balancer (Laravel Cloud) every request reaches Laravel
        // as plain http. Invoice links are SIGNED for https, so without
        // trusting the proxy's X-Forwarded-* headers each signature check
        // fails and every couple's invoice link returns 403. Opt-in, because
        // on a server with no proxy in front, trusting those headers would let
        // anyone spoof their IP and walk past the booking rate limit.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
