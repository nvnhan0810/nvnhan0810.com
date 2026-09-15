<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('to_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->string('description');
            $table->text('note')->nullable();
            $table->date('transacted_at');
            $table->foreignId('transaction_template_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('adjustment_direction')->nullable()->after('type');
            $table->foreignId('wallet_transfer_id')->nullable()->after('loan_payment_id')->constrained()->nullOnDelete();
        });

        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->foreignId('from_wallet_id')->nullable()->after('default_wallet_id')->constrained('wallets')->nullOnDelete();
            $table->foreignId('to_wallet_id')->nullable()->after('from_wallet_id')->constrained('wallets')->nullOnDelete();
            $table->decimal('fee', 15, 2)->nullable()->after('amount');
            $table->string('adjustment_direction')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_wallet_id');
            $table->dropConstrainedForeignId('from_wallet_id');
            $table->dropColumn(['fee', 'adjustment_direction']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_transfer_id');
            $table->dropColumn('adjustment_direction');
        });

        Schema::dropIfExists('wallet_transfers');
    }
};
