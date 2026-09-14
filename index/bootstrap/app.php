<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Jobs\ReadingDigest\DecayInterestScoresJob;
use App\Jobs\ReadingDigest\PurgeStaleArticlesJob;
use App\Jobs\ReadingDigest\RebuildUserEmbeddingJob;
use App\Jobs\ReadingDigest\RunDailyDigestJob;
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
        $time = (string) config('reading-digest.notification_time', '07:00');
        $timezone = (string) config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');

        $schedule->job(new RunDailyDigestJob)
            ->dailyAt($time)
            ->timezone($timezone)
            ->name('reading-digest:daily')
            ->withoutOverlapping(30);

        $schedule->job(new PurgeStaleArticlesJob)
            ->dailyAt('03:00')
            ->timezone($timezone)
            ->name('reading-digest:purge-stale')
            ->withoutOverlapping();

        $schedule->job(new DecayInterestScoresJob)
            ->weeklyOn(1, '04:00')
            ->timezone($timezone)
            ->name('reading-digest:decay-interest')
            ->withoutOverlapping();

        $schedule->job(new RebuildUserEmbeddingJob)
            ->dailyAt('04:30')
            ->timezone($timezone)
            ->name('reading-digest:rebuild-embeddings')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
