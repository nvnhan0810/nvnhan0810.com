<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('google.login'));

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Source of truth for command list + times (local schedule:work / schedule:run).
        // Production k3s CronJobs in k3s/apps/nvnhan0810.com/jobs/ call these
        // artisan commands directly — not schedule:run.
        $time = (string) config('reading-digest.notification_time', '07:00');
        $timezone = (string) config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');

        $schedule->command('reading-digest:run-daily')
            ->dailyAt($time)
            ->timezone($timezone)
            ->name('reading-digest:daily')
            ->withoutOverlapping(30);

        $schedule->command('reading-digest:purge-stale')
            ->dailyAt('03:00')
            ->timezone($timezone)
            ->name('reading-digest:purge-stale')
            ->withoutOverlapping();

        $schedule->command('reading-digest:decay-interest')
            ->weeklyOn(1, '04:00')
            ->timezone($timezone)
            ->name('reading-digest:decay-interest')
            ->withoutOverlapping();

        $schedule->command('reading-digest:rebuild-embeddings')
            ->dailyAt('04:30')
            ->timezone($timezone)
            ->name('reading-digest:rebuild-embeddings')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
