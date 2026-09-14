<?php

namespace Modules\ReadingDigest;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\ReadingDigest\Application\Command\ApplyArticleVote;
use Modules\ReadingDigest\Application\Command\RecordArticleOpened;
use Modules\ReadingDigest\Application\Handler\ApplyArticleVoteHandler;
use Modules\ReadingDigest\Application\Handler\GetArticleListHandler;
use Modules\ReadingDigest\Application\Handler\GetTodayDigestHandler;
use Modules\ReadingDigest\Application\Handler\RecordArticleOpenedHandler;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\ReadingDigest\Application\Query\GetTodayDigest;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelCommandBus;
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
            $bus->register(GetTodayDigest::class, GetTodayDigestHandler::class);
        });

        $this->callAfterResolving(CommandBus::class, function (CommandBus $bus): void {
            if (! $bus instanceof LaravelCommandBus) {
                return;
            }

            $bus->register(ApplyArticleVote::class, ApplyArticleVoteHandler::class);
            $bus->register(RecordArticleOpened::class, RecordArticleOpenedHandler::class);
        });
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/reading-digest.php'));
    }
}
