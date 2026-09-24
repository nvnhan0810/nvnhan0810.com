<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('todo_pomodoro_states');
        Schema::dropIfExists('eisenhower_logs');
        Schema::dropIfExists('todos');
        Schema::dropIfExists('todo_projects');
        Schema::dropIfExists('web_push_subscriptions');
    }

    public function down(): void
    {
        Schema::create('todo_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('git_repo_url')->unique()->nullable();
            $table->string('domain')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('todo_projects')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['backlog', 'todo', 'in_progress', 'done', 'rejected'])->default('backlog');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_important')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('eisenhower_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained('todos')->cascadeOnDelete();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_important')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

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
            $table->uuid('session_uuid')->nullable();
            $table->timestamp('last_focused_at')->nullable();
            $table->unsignedBigInteger('client_updated_at')->default(0);
            $table->timestamps();
        });

        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64);
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aes128gcm');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_focused_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint_hash']);
            $table->index(['user_id', 'last_focused_at']);
        });
    }
};
