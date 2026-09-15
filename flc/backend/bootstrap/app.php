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
            $webPrefix = trim((string) config('app.path_prefix', 'flc'), '/');
            $apiPrefix = trim((string) config('app.api_path_prefix', 'flc/api'), '/');

            $web = Route::middleware('web');
            if ($webPrefix !== '') {
                $web = $web->prefix($webPrefix);
            }
            $web->group(base_path('routes/web.php'));

            $api = Route::middleware('api');
            if ($apiPrefix !== '') {
                $api = $api->prefix($apiPrefix);
            }
            $api->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('user.login'));

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('flc:vocab-quiz-reminders midday')
            ->timezone('Asia/Ho_Chi_Minh')
            ->dailyAt('11:00');
        $schedule->command('flc:vocab-quiz-reminders evening')
            ->timezone('Asia/Ho_Chi_Minh')
            ->dailyAt('20:00');
    })
    ->create();
