<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestampsTz();

            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'name']);
        });

        Schema::create('reader_collection_documents', function (Blueprint $table) {
            $table->foreignUuid('collection_id')
                ->constrained('reader_collections')
                ->cascadeOnDelete();
            $table->foreignUuid('document_id')
                ->constrained('reader_documents')
                ->cascadeOnDelete();
            $table->timestampTz('added_at');

            $table->primary(['collection_id', 'document_id']);
            $table->index(['document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_collection_documents');
        Schema::dropIfExists('reader_collections');
    }
};
