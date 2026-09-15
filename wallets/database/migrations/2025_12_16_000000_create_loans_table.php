<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'bank', 'borrow', 'lend'
            $table->string('name'); // Provider name or Person name
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->nullable(); // Annual interest rate %
            $table->integer('term_months')->nullable();
            $table->decimal('monthly_payment', 15, 2)->nullable(); // Fixed monthly payment
            $table->date('started_at');
            $table->boolean('is_settled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};

