<?php

namespace App\Domains\ReadingDigest;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestSettingsModel;
use App\Domains\ReadingDigest\Presentation\Jobs\DecayInterestScoresJob;
use App\Domains\ReadingDigest\Presentation\Jobs\RebuildUserEmbeddingJob;
use App\Domains\ReadingDigest\Presentation\Jobs\RunDailyDigestJob;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
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

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            try {
                $settings = DigestSettingsModel::query()->first();
            } catch (\Throwable) {
                $settings = null;
            }

            $time = $settings?->notification_time ?? config('reading-digest.notification_time', '08:00');
            $timezone = $settings?->timezone ?? config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');

            $schedule->job(new RunDailyDigestJob)
                ->dailyAt($time)
                ->timezone($timezone)
                ->name('reading-digest:daily');

            $schedule->job(new DecayInterestScoresJob)
                ->weekly()
                ->name('reading-digest:decay-interest');

            $schedule->call(function () {
                $user = User::query()->orderBy('id')->first();
                if ($user) {
                    RebuildUserEmbeddingJob::dispatch($user->id);
                }
            })->dailyAt('02:00')->name('reading-digest:rebuild-embedding');
        });
    }
}
