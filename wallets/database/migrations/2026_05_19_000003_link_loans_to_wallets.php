<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('wallet_id')->nullable()->after('is_settled')->constrained()->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('note')->constrained()->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('transaction_template_id')->constrained()->nullOnDelete();
            $table->foreignId('loan_payment_id')->nullable()->after('loan_id')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_payment_id');
            $table->dropConstrainedForeignId('loan_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_id');
        });
    }
};
