<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The production database was imported without auto-increment sequences /
     * primary keys on integer id columns. On the `jobs` table this is fatal:
     * every row is inserted with id = NULL, so the worker's
     * `DELETE FROM jobs WHERE id = ?` matches ALL null-id rows and wipes every
     * other queued job the moment one job finishes. This restores a real
     * auto-incrementing primary key on the queue tables.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table) || $this->hasPrimaryKey($table)) {
                continue;
            }

            $sequence = $table.'_id_seq';

            DB::statement("CREATE SEQUENCE IF NOT EXISTS {$sequence}");
            DB::statement("UPDATE {$table} SET id = nextval('{$sequence}') WHERE id IS NULL");
            DB::statement("SELECT setval('{$sequence}', COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN id SET DEFAULT nextval('{$sequence}')");
            DB::statement("ALTER SEQUENCE {$sequence} OWNED BY {$table}.id");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN id SET NOT NULL");
            DB::statement("ALTER TABLE {$table} ADD PRIMARY KEY (id)");
        }
    }

    public function down(): void
    {
        // Do not drop production primary keys / sequences on rollback.
    }

    private function hasPrimaryKey(string $table): bool
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
