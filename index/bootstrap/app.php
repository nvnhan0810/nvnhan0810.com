<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Reader\Domain\Enums\ApiErrorCode;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/v1/*') || $request->expectsJson()) {
                return null;
            }

            return route('google.login');
        });

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

        $schedule->command('reader:purge-expired-trash')
            ->dailyAt('23:30')
            ->timezone($timezone)
            ->name('reader:purge-expired-trash')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/v1/*') || $request->expectsJson()) {
                return ApiErrorResponse::unauthenticated();
            }

            return null;
        });

        $exceptions->render(function (ReaderDomainException $exception, Request $request) {
            if ($request->is('api/v1/*') || $request->expectsJson()) {
                return ApiErrorResponse::fromDomain($exception);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::VALIDATION_ERROR,
                $exception->getMessage(),
                422,
                ['errors' => $exception->errors()],
            );
        });
    })->create();
