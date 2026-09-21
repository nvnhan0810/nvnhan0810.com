<?php

namespace Modules\Todo;

use App\Models\Todo;
use App\Observers\TodoObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Todo::observe(TodoObserver::class);

        Route::middleware('web')->group(base_path('routes/todo.php'));
    }
}
