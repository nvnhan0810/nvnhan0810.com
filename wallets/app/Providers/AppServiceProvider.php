<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        if ($this->shouldForceHttps()) {
            URL::forceScheme('https');

            $appUrl = rtrim((string) config('app.url'), '/');
            if ($appUrl !== '') {
                URL::forceRootUrl($appUrl);
            }
        }
    }

    private function shouldForceHttps(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        $appUrl = (string) config('app.url');

        return ! str_contains($appUrl, 'localhost')
            && ! str_contains($appUrl, '127.0.0.1')
            && ! str_contains($appUrl, '[::1]');
    }
}
