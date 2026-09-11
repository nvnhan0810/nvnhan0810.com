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
        Schema::create('series_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id');
            $table->foreignId('post_id');
            $table->integer('order');
            $table->timestamps();

            $table->unique(['series_id', 'post_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series_posts');
    }
};
