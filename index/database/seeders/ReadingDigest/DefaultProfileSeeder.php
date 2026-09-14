<?php

namespace Database\Seeders\ReadingDigest;

use App\Models\RdUserReadingProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;

class DefaultProfileSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            return;
        }

        RdUserReadingProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => DefaultPreferences::make()]
        );
    }
}
