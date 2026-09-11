<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable()->after('content');
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('post_translations')
            ->whereNull('content')
            ->update(['content' => '']);

        Schema::table('post_translations', function (Blueprint $table) {
            $table->dropColumn('source_url');
            $table->text('content')->nullable(false)->change();
        });
    }
};

