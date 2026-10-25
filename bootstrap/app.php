<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Di balik proxy Vercel: percayai X-Forwarded-* agar URL/asset pakai https
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin.token' => \App\Http\Middleware\AdminTokenAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Vercel: filesystem read-only kecuali /tmp
if (getenv('VERCEL')) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
