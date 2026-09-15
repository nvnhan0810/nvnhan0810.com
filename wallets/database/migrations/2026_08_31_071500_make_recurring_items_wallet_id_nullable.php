<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_items', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->foreignId('wallet_id')->nullable()->change();
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
        });

        DB::table('recurring_items')->update(['wallet_id' => null]);
    }

    public function down(): void
    {
        // Cannot safely restore NOT NULL without inventing wallet ids.
        Schema::table('recurring_items', function (Blueprint $table) {
            $table->dropForeign(['wallet_id']);
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->foreignId('wallet_id')->nullable(false)->change();
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
        });
    }
};
