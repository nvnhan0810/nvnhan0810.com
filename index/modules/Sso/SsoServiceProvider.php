<?php

namespace Modules\Sso;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelCommandBus;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;
use Modules\Sso\Application\Command\CreateSsoClient;
use Modules\Sso\Application\Command\DeleteSsoClient;
use Modules\Sso\Application\Command\UpdateSsoClient;
use Modules\Sso\Application\Handler\CreateSsoClientHandler;
use Modules\Sso\Application\Handler\DeleteSsoClientHandler;
use Modules\Sso\Application\Handler\GetSsoClientHandler;
use Modules\Sso\Application\Handler\ListSsoClientsHandler;
use Modules\Sso\Application\Handler\UpdateSsoClientHandler;
use Modules\Sso\Application\Query\GetSsoClient;
use Modules\Sso\Application\Query\ListSsoClients;
use Modules\Sso\Domain\Ports\AuthorizationCodeStore;
use Modules\Sso\Domain\Ports\SsoClientRepository;
use Modules\Sso\Domain\SsoClientRegistry;
use Modules\Sso\Infrastructure\CacheAuthorizationCodeStore;
use Modules\Sso\Infrastructure\Persistence\EloquentSsoClientRepository;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/sso.php'), 'sso');

        $this->app->bind(AuthorizationCodeStore::class, CacheAuthorizationCodeStore::class);
        $this->app->bind(SsoClientRepository::class, EloquentSsoClientRepository::class);

        $this->app->singleton(SsoClientRegistry::class, function ($app): SsoClientRegistry {
            return new SsoClientRegistry(
                $app->make(SsoClientRepository::class),
                (string) config('sso.secret', ''),
            );
        });

        $this->callAfterResolving(QueryBus::class, function (QueryBus $bus): void {
            if (! $bus instanceof LaravelQueryBus) {
                return;
            }

            $bus->register(ListSsoClients::class, ListSsoClientsHandler::class);
            $bus->register(GetSsoClient::class, GetSsoClientHandler::class);
        });

        $this->callAfterResolving(CommandBus::class, function (CommandBus $bus): void {
            if (! $bus instanceof LaravelCommandBus) {
                return;
            }

            $bus->register(CreateSsoClient::class, CreateSsoClientHandler::class);
            $bus->register(UpdateSsoClient::class, UpdateSsoClientHandler::class);
            $bus->register(DeleteSsoClient::class, DeleteSsoClientHandler::class);
        });
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/sso.php'));
    }
}
