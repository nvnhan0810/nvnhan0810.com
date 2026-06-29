<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url');
        $isLocalHost = str_contains($appUrl, 'localhost')
            || str_contains($appUrl, '127.0.0.1')
            || str_contains($appUrl, '[::1]');

        if (app()->environment('production') && ! $isLocalHost) {
            URL::forceScheme('https');
        }
        
        Queue::failing(function (JobFailed $event): void {
            Log::error('Queue job failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}
