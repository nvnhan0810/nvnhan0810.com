<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || $this->usersHasPrimaryKey()) {
            return;
        }

        $nextId = (int) DB::table('users')->max('id') + 1;

        DB::statement('CREATE SEQUENCE IF NOT EXISTS users_id_seq');
        DB::statement("SELECT setval('users_id_seq', ?, false)", [$nextId]);
        DB::statement("ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval('users_id_seq')");
        DB::statement('ALTER SEQUENCE users_id_seq OWNED BY users.id');
        DB::statement('ALTER TABLE users ADD PRIMARY KEY (id)');

        if (! $this->usersHasUniqueEmail()) {
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');
        }
    }

    public function down(): void
    {
        // Do not drop production primary key / email unique index on rollback.
    }

    private function usersHasPrimaryKey(): bool
    {
        return DB::selectOne("
            SELECT 1 AS found
            FROM pg_constraint c
            JOIN pg_class t ON c.conrelid = t.oid
            JOIN pg_namespace n ON t.relnamespace = n.oid
            WHERE n.nspname = current_schema()
              AND t.relname = 'users'
              AND c.contype = 'p'
            LIMIT 1
        ") !== null;
    }

    private function usersHasUniqueEmail(): bool
    {
        return DB::selectOne("
            SELECT 1 AS found
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'users'
              AND indexdef ILIKE '%UNIQUE%email%'
            LIMIT 1
        ") !== null;
    }
};
