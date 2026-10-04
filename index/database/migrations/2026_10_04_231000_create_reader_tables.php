<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('page_count')->default(0);
            $table->string('content_type')->default('application/pdf');
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->char('content_sha256', 64)->nullable();
            $table->string('seaweed_pdf_key')->nullable();
            $table->string('seaweed_thumb_key')->nullable();
            $table->string('status', 32)->default('uploading');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'deleted_at']);
        });

        Schema::create('reader_reading_progress', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->foreign('document_id')
                ->references('id')
                ->on('reader_documents')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('page_index')->default(0);
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestampTz('updated_at');

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('reader_page_annotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('reader_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('page_index');
            $table->string('format', 64)->default('pencilkit.pkdrawing');
            $table->string('seaweed_key')->nullable();
            $table->unsignedInteger('byte_size')->default(0);
            $table->char('content_sha256', 64)->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestampTz('updated_at');

            $table->unique(['document_id', 'page_index']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_page_annotations');
        Schema::dropIfExists('reader_reading_progress');
        Schema::dropIfExists('reader_documents');
    }
};
