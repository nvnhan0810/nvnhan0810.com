<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_documents', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('status');
            $table->index(['user_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        Schema::table('reader_documents', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_favorite']);
            $table->dropColumn('is_favorite');
        });
    }
};
