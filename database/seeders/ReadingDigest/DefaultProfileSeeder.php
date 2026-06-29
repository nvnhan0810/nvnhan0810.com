<?php

namespace Database\Seeders\ReadingDigest;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\UserReadingProfileModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Repositories\DefaultPreferences;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultProfileSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            return;
        }

        UserReadingProfileModel::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => DefaultPreferences::make()]
        );
    }
}
