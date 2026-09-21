<?php

namespace Modules\Todo;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/todo.php'));
    }
}
