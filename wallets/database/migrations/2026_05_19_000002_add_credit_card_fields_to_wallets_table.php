<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('credit_limit', 15, 2)->nullable()->after('balance');
            $table->unsignedTinyInteger('statement_day')->nullable()->after('credit_limit');
            $table->unsignedTinyInteger('payment_day')->nullable()->after('statement_day');
            $table->decimal('outstanding_balance', 15, 2)->nullable()->after('payment_day');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'statement_day', 'payment_day', 'outstanding_balance']);
        });
    }
};
