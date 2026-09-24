<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_id', 64)->unique();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->json('redirect_uris');
            $table->json('redirect_uri_patterns');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $walletsUrl = rtrim((string) env('SSO_WALLETS_URL', 'https://wallets.nvnhan0810.com'), '/');
        $flcUrl = rtrim((string) env('SSO_FLC_URL', 'https://foreign.nvnhan0810.com'), '/');
        $todoUrl = rtrim((string) env('SSO_TODO_URL', 'https://todo.nvnhan0810.com'), '/');
        $now = now();

        $rows = [
            [
                'client_id' => 'wallets',
                'name' => 'Wallets',
                'domain' => $walletsUrl,
                'redirect_uris' => [$walletsUrl.'/auth/sso/callback'],
                'redirect_uri_patterns' => [],
            ],
            [
                'client_id' => 'todo',
                'name' => 'Todo',
                'domain' => $todoUrl,
                'redirect_uris' => [$todoUrl.'/auth/sso/callback'],
                'redirect_uri_patterns' => [],
            ],
            [
                'client_id' => 'flc-web',
                'name' => 'FLC Web',
                'domain' => $flcUrl,
                'redirect_uris' => [$flcUrl.'/auth/sso/callback'],
                'redirect_uri_patterns' => [],
            ],
            [
                'client_id' => 'flc-admin',
                'name' => 'FLC Admin',
                'domain' => $flcUrl,
                'redirect_uris' => [$flcUrl.'/admin/auth/sso/callback'],
                'redirect_uri_patterns' => [],
            ],
            [
                'client_id' => 'flc-mobile',
                'name' => 'FLC Mobile',
                'domain' => null,
                'redirect_uris' => [],
                'redirect_uri_patterns' => [
                    '/^flc:\/\/oauth-callback(\/|\?|$)/',
                    '/^https:\/\/[a-z0-9-]+\.chromiumapp\.org(\/|\?|$)/',
                ],
            ],
        ];

        foreach ($rows as $row) {
            DB::table('sso_clients')->insert([
                'id' => (string) Str::uuid(),
                'client_id' => $row['client_id'],
                'name' => $row['name'],
                'domain' => $row['domain'],
                'redirect_uris' => json_encode($row['redirect_uris'], JSON_THROW_ON_ERROR),
                'redirect_uri_patterns' => json_encode($row['redirect_uri_patterns'], JSON_THROW_ON_ERROR),
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_clients');
    }
};
