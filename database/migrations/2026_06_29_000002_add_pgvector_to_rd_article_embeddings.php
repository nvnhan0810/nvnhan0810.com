<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pgvector DDL can fail (missing extension, permissions) and must not
     * abort the main schema migration transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('rd_article_embeddings')) {
            return;
        }

        if (Schema::hasColumn('rd_article_embeddings', 'embedding')) {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable) {
            return;
        }

        try {
            DB::statement('ALTER TABLE rd_article_embeddings ADD COLUMN embedding vector(1536)');
        } catch (\Throwable) {
            return;
        }

        // IVFFlat indexes need sufficient rows; create later if desired.
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('rd_article_embeddings', 'embedding')) {
            return;
        }

        DB::statement('ALTER TABLE rd_article_embeddings DROP COLUMN IF EXISTS embedding');
    }
};
