<?php

namespace Modules\Todo;

use App\Models\Todo;
use App\Observers\TodoObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;
use Modules\Todo\Domain\Ports\WebPushSender;
use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;
use Modules\Todo\Infrastructure\EloquentPomodoroStateRepository;
use Modules\Todo\Infrastructure\EloquentWebPushSubscriptionRepository;
use Modules\Todo\Infrastructure\MinishlinkWebPushSender;

class TodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PomodoroStateRepository::class, EloquentPomodoroStateRepository::class);
        $this->app->bind(WebPushSubscriptionRepository::class, EloquentWebPushSubscriptionRepository::class);
        $this->app->bind(WebPushSender::class, MinishlinkWebPushSender::class);
    }

    public function boot(): void
    {
        Todo::observe(TodoObserver::class);

        Route::middleware('web')->group(base_path('routes/todo.php'));
    }
}
