<?php

namespace Modules\ReadingDigest;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\ReadingDigest\Application\Handler\GetArticleListHandler;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;

class ReadingDigestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/reading-digest.php'),
            'reading-digest'
        );

        $this->callAfterResolving(QueryBus::class, function (QueryBus $bus): void {
            if (! $bus instanceof LaravelQueryBus) {
                return;
            }

            $bus->register(GetArticleList::class, GetArticleListHandler::class);
        });
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/reading-digest.php'));
    }
}
