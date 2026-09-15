<?php

namespace Modules\Sso;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Sso\Domain\Ports\AuthorizationCodeStore;
use Modules\Sso\Domain\SsoClientRegistry;
use Modules\Sso\Infrastructure\CacheAuthorizationCodeStore;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/sso.php'), 'sso');

        $this->app->singleton(SsoClientRegistry::class);
        $this->app->bind(AuthorizationCodeStore::class, CacheAuthorizationCodeStore::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/sso.php'));
    }
}
