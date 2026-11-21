<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->append(SecurityHeaders::class);

        // Behind a hosting provider's HTTPS proxy (Render, Railway, a load balancer).
        // TRUST_PROXIES=true trusts any proxy — only safe when the app cannot be reached
        // except through that proxy. Otherwise list the proxy IPs / CIDRs, comma-separated.
        $proxies = trim((string) env('TRUST_PROXIES', ''));
        if ($proxies !== '' && ! in_array(strtolower($proxies), ['false', '0', 'off'], true)) {
            $middleware->trustProxies(at: in_array(strtolower($proxies), ['true', '1', '*'], true)
                ? '*'
                : array_map('trim', explode(',', $proxies)));
        }
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
