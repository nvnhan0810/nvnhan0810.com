<?php

namespace Modules\Shared;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelCommandBus;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LaravelQueryBus::class, function ($app): LaravelQueryBus {
            return new LaravelQueryBus($app);
        });

        $this->app->bind(QueryBus::class, LaravelQueryBus::class);

        $this->app->singleton(LaravelCommandBus::class, function ($app): LaravelCommandBus {
            return new LaravelCommandBus($app);
        });

        $this->app->bind(CommandBus::class, LaravelCommandBus::class);
    }
}
