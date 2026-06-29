<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cache') && ! $this->tableHasPrimaryKey('cache')) {
            DB::statement('ALTER TABLE cache ADD PRIMARY KEY (key)');
        }

        if (Schema::hasTable('cache_locks') && ! $this->tableHasPrimaryKey('cache_locks')) {
            DB::statement('ALTER TABLE cache_locks ADD PRIMARY KEY (key)');
        }
    }

    public function down(): void
    {
        // Do not drop production primary keys on rollback.
    }

    private function tableHasPrimaryKey(string $table): bool
    {
        return DB::selectOne('
            SELECT 1 AS found
            FROM pg_constraint c
            JOIN pg_class t ON c.conrelid = t.oid
            JOIN pg_namespace n ON t.relnamespace = n.oid
            WHERE n.nspname = current_schema()
              AND t.relname = ?
              AND c.contype = ?
            LIMIT 1
        ', [$table, 'p']) !== null;
    }
};
