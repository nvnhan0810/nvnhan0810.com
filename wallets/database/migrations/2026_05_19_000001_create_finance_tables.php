<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // cash, bank, credit_card, e_wallet
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('transaction_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // income, expense
            $table->decimal('amount', 15, 2);
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('default_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // income, expense
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->string('category')->nullable();
            $table->foreignId('transaction_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('transacted_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // income, expense
            $table->decimal('amount', 15, 2);
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_month'); // 1-31
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('transaction_templates');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('settings');
    }
};
