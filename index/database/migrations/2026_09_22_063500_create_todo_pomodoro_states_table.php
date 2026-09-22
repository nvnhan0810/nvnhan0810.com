<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_pomodoro_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('focus_minutes')->default(20);
            $table->unsignedSmallInteger('short_break_minutes')->default(5);
            $table->unsignedTinyInteger('sessions_before_long_break')->default(4);
            $table->unsignedSmallInteger('long_break_minutes')->default(15);
            $table->string('phase', 20)->default('focus');
            $table->unsignedInteger('remaining_ms');
            $table->unsignedBigInteger('ends_at')->nullable();
            $table->unsignedTinyInteger('focus_count')->default(0);
            $table->foreignId('active_todo_id')->nullable()->constrained('todos')->nullOnDelete();
            $table->boolean('is_running')->default(false);
            $table->unsignedBigInteger('client_updated_at')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_pomodoro_states');
    }
};
