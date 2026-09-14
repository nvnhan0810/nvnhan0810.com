<?php

namespace Modules\ReadingDigest;

use App\Domains\ReadingDigest\Presentation\Console\SetTelegramWebhookCommand;
use App\Domains\ReadingDigest\Presentation\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ReadingDigestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/reading-digest.php'),
            'reading-digest'
        );
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/reading-digest.php'));
    }
}
