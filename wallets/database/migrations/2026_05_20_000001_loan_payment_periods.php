<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_items', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('wallet_id')->constrained()->nullOnDelete();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_day')->nullable()->after('monthly_payment');
            $table->foreignId('recurring_item_id')->nullable()->after('wallet_id')->constrained('recurring_items')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind')->default('period')->after('loan_id');
            $table->date('period_due_date')->nullable()->after('paid_at');
            $table->unsignedSmallInteger('schedule_month_index')->nullable()->after('period_due_date');
            $table->boolean('reduces_principal')->default(true)->after('schedule_month_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['kind', 'period_due_date', 'schedule_month_index', 'reduces_principal']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_item_id');
            $table->dropColumn('payment_day');
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
    }
};
