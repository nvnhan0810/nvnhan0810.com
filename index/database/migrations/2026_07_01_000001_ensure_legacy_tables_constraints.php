<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The production Postgres database was imported without primary keys,
     * auto-increment sequences, unique indexes and foreign keys on the older
     * (pre reading-digest) tables. This restores them to match the migrations.
     * Idempotent: every change is guarded, so it is a no-op on databases that
     * were created cleanly.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // 1. bigint auto-increment id columns: sequence + primary key.
        foreach (['posts', 'tags', 'post_tag', 'series', 'series_posts', 'post_translations'] as $table) {
            $this->ensureSerialPrimaryKey($table);
        }

        // 2. explicit (string) primary keys.
        $this->ensurePrimaryKey('sessions', 'id');
        $this->ensurePrimaryKey('password_reset_tokens', 'email');
        $this->ensurePrimaryKey('job_batches', 'id');

        // 3. unique indexes.
        $this->ensureUnique('posts', ['slug']);
        $this->ensureUnique('tags', ['slug']);
        $this->ensureUnique('series', ['slug']);
        $this->ensureUnique('series_posts', ['series_id', 'post_id']);
        $this->ensureUnique('post_translations', ['post_id', 'locale']);
        $this->ensureUnique('failed_jobs', ['uuid']);

        // 4. foreign keys.
        $this->ensureForeignKey('post_translations', 'post_id', 'posts', 'id', cascade: true);
    }

    public function down(): void
    {
        // Do not drop production constraints on rollback.
    }

    private function ensureSerialPrimaryKey(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $sequence = $table.'_id_seq';
        $default = $this->idDefault($table);

        if ($default === null || stripos($default, 'nextval') === false) {
            DB::statement("CREATE SEQUENCE IF NOT EXISTS {$sequence}");
            DB::statement("UPDATE {$table} SET id = nextval('{$sequence}') WHERE id IS NULL");
            DB::statement("SELECT setval('{$sequence}', COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN id SET DEFAULT nextval('{$sequence}')");
            DB::statement("ALTER SEQUENCE {$sequence} OWNED BY {$table}.id");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN id SET NOT NULL");
        }

        if (! $this->hasPrimaryKey($table)) {
            DB::statement("ALTER TABLE {$table} ADD PRIMARY KEY (id)");
        }
    }

    private function ensurePrimaryKey(string $table, string $column): void
    {
        if (Schema::hasTable($table) && ! $this->hasPrimaryKey($table)) {
            DB::statement("ALTER TABLE {$table} ADD PRIMARY KEY ({$column})");
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureUnique(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $name = $table.'_'.implode('_', $columns).'_unique';
        $cols = implode(', ', array_map(fn ($c) => "\"{$c}\"", $columns));

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$name} ON {$table} ({$cols})");
    }

    private function ensureForeignKey(string $table, string $column, string $refTable, string $refColumn, bool $cascade = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
            return;
        }

        $exists = DB::selectOne('
            SELECT 1 AS found
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN unnest(c.conkey) AS k(attnum) ON true
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = k.attnum
            WHERE c.contype = ? AND n.nspname = current_schema() AND t.relname = ? AND a.attname = ?
            LIMIT 1
        ', ['f', $table, $column]) !== null;

        if ($exists) {
            return;
        }

        $name = $table.'_'.$column.'_foreign';
        $onDelete = $cascade ? ' ON DELETE CASCADE' : '';

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}){$onDelete}");
    }

    private function idDefault(string $table): ?string
    {
        $row = DB::selectOne("
            SELECT column_default
            FROM information_schema.columns
            WHERE table_schema = current_schema() AND table_name = ? AND column_name = 'id'
        ", [$table]);

        return $row->column_default ?? null;
    }

    private function hasPrimaryKey(string $table): bool
    {
        return DB::selectOne('
            SELECT 1 AS found
            FROM pg_constraint c
            JOIN pg_class t ON c.conrelid = t.oid
            JOIN pg_namespace n ON t.relnamespace = n.oid
            WHERE n.nspname = current_schema() AND t.relname = ? AND c.contype = ?
            LIMIT 1
        ', [$table, 'p']) !== null;
    }
};
