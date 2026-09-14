<?php

namespace Modules\Shared;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LaravelQueryBus::class, function ($app): LaravelQueryBus {
            return new LaravelQueryBus($app);
        });

        $this->app->bind(QueryBus::class, LaravelQueryBus::class);
    }
}
