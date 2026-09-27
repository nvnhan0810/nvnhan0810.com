<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Blog\Domain\Enums\SpecialTag;

return new class extends Migration
{
    public function up(): void
    {
        $slug = SpecialTag::Install->value;
        $name = SpecialTag::Install->label();
        $now = now();

        $exists = DB::table('tags')->where('slug', $slug)->exists();

        if ($exists) {
            DB::table('tags')->where('slug', $slug)->update([
                'name' => $name,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('tags')->insert([
            'name' => $name,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Keep the tag; posts may still reference it.
    }
};
