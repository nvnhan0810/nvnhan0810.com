<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'recurring_item_id')) {
                $table->dropConstrainedForeignId('recurring_item_id');
            }
        });

        Schema::table('recurring_items', function (Blueprint $table) {
            if (Schema::hasColumn('recurring_items', 'loan_id')) {
                $table->dropConstrainedForeignId('loan_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recurring_items', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('wallet_id')->constrained()->nullOnDelete();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('recurring_item_id')->nullable()->after('wallet_id')->constrained('recurring_items')->nullOnDelete();
        });
    }
};
