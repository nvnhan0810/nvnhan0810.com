<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('expected_amount', 15, 2);
            $table->string('status')->default('pending'); // pending|due|overdue|posted|skipped
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('posted_amount', 15, 2)->nullable();
            $table->date('posted_at')->nullable();
            $table->timestamp('reminded_telegram_at')->nullable();
            $table->timestamps();

            $table->unique(['recurring_item_id', 'due_date']);
            $table->index(['user_id', 'status', 'due_date']);
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('day_of_month');
            $table->date('ends_at')->nullable()->after('effective_from');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_item_id')->nullable()->after('transaction_template_id')->constrained()->nullOnDelete();
            $table->foreignId('recurring_occurrence_id')->nullable()->after('recurring_item_id')->constrained('recurring_occurrences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_occurrence_id');
            $table->dropConstrainedForeignId('recurring_item_id');
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'ends_at']);
        });

        Schema::dropIfExists('recurring_occurrences');
    }
};
