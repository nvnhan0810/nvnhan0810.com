<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $primaryKeyExists = DB::selectOne("
            SELECT 1
            FROM pg_constraint
            WHERE conrelid = 'posts'::regclass
            AND contype = 'p'
        ");

        if (! $primaryKeyExists) {
            DB::statement('ALTER TABLE posts ADD PRIMARY KEY (id)');
        }

        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content');
            $table->timestamps();

            $table->unique(['post_id', 'locale']);
        });

        if (Schema::hasColumn('posts', 'title')) {
            $posts = DB::table('posts')->get();

            foreach ($posts as $post) {
                DB::table('post_translations')->insert([
                    'post_id' => $post->id,
                    'locale' => 'en',
                    'title' => $post->title,
                    'description' => $post->description,
                    'content' => $post->content,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn(['title', 'description', 'content']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('content')->nullable();
        });

        $translations = DB::table('post_translations')
            ->where('locale', 'en')
            ->get();

        foreach ($translations as $translation) {
            DB::table('posts')
                ->where('id', $translation->post_id)
                ->update([
                    'title' => $translation->title,
                    'description' => $translation->description,
                    'content' => $translation->content,
                ]);
        }

        Schema::dropIfExists('post_translations');

        Schema::table('posts', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->text('content')->nullable(false)->change();
        });
    }
};
