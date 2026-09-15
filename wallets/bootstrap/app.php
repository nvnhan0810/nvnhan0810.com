<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $webPrefix = trim((string) config('app.path_prefix', 'wallets'), '/');
            $apiPrefix = trim((string) config('app.api_path_prefix', 'wallets/api'), '/');

            $web = Route::middleware('web');
            if ($webPrefix !== '') {
                $web = $web->prefix($webPrefix);
            }
            $web->group(base_path('routes/web.php'));

            if (is_file(base_path('routes/api.php'))) {
                $api = Route::middleware('api');
                if ($apiPrefix !== '') {
                    $api = $api->prefix($apiPrefix);
                }
                $api->group(base_path('routes/api.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
