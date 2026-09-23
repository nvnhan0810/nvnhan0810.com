<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_pomodoro_states', function (Blueprint $table) {
            $table->uuid('session_uuid')->nullable()->after('is_running');
            $table->timestamp('last_focused_at')->nullable()->after('session_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('todo_pomodoro_states', function (Blueprint $table) {
            $table->dropColumn(['session_uuid', 'last_focused_at']);
        });
    }
};
