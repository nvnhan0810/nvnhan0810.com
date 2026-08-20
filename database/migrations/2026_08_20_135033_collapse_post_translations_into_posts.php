<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('post_translations')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('posts', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('posts', 'content')) {
                $table->text('content')->nullable();
            }
            if (! Schema::hasColumn('posts', 'source_url')) {
                $table->string('source_url', 2048)->nullable();
            }
        });

        $postIds = DB::table('post_translations')->distinct()->pluck('post_id');

        foreach ($postIds as $postId) {
            $vi = DB::table('post_translations')
                ->where('post_id', $postId)
                ->where('locale', 'vi')
                ->first();

            $en = DB::table('post_translations')
                ->where('post_id', $postId)
                ->where('locale', 'en')
                ->first();

            $source = $vi ?? $en ?? DB::table('post_translations')
                ->where('post_id', $postId)
                ->orderBy('id')
                ->first();

            if (! $source) {
                continue;
            }

            DB::table('posts')
                ->where('id', $postId)
                ->update([
                    'title' => $source->title,
                    'description' => $source->description,
                    'content' => $source->content,
                    'source_url' => $source->source_url ?? null,
                ]);
        }

        Schema::dropIfExists('post_translations');
    }

    public function down(): void
    {
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'locale']);
        });

        $posts = DB::table('posts')->get();

        foreach ($posts as $post) {
            DB::table('post_translations')->insert([
                'post_id' => $post->id,
                'locale' => 'vi',
                'title' => $post->title ?? '',
                'description' => $post->description,
                'content' => $post->content,
                'source_url' => $post->source_url ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'content', 'source_url']);
        });
    }
};
