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
        // Halaman/data admin yang tidak ditemukan -> kembali ke daftar dengan notifikasi
        $adminNotFound = function (Request $request) {
            if (!$request->is('admin/*') || $request->expectsJson()) {
                return null;
            }
            $sections = [
                'pesanan' => 'admin.pesanan', 'pembayaran' => 'admin.pembayaran', 'jadwal' => 'admin.jadwal',
                'pelanggan' => 'admin.pelanggan', 'paket' => 'admin.paket', 'galeri' => 'admin.galeri',
                'pengaturan' => 'admin.pengaturan',
            ];
            $route = $sections[$request->segment(2)] ?? 'admin.dashboard';

            return redirect()->route($route)->with('error', 'Data tidak ditemukan atau sudah dihapus.');
        };
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) use ($adminNotFound) {
            return $adminNotFound($request);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) use ($adminNotFound) {
            return $adminNotFound($request);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Vercel: filesystem read-only kecuali /tmp
if (getenv('VERCEL')) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
