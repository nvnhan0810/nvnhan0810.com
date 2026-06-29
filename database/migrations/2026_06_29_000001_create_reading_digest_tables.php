<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rd_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('articles_per_digest')->default(5);
            $table->unsignedSmallInteger('max_age_days')->default(7);
            $table->boolean('enabled')->default(true);
            $table->json('filters')->nullable();
            $table->timestamps();
        });

        Schema::create('rd_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->string('url');
            $table->unsignedInteger('fetch_interval_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->string('last_fetch_status')->nullable();
            $table->timestamp('last_fetch_at')->nullable();
            $table->text('last_fetch_error')->nullable();
            $table->timestamps();
        });

        Schema::create('rd_subject_source', function (Blueprint $table) {
            $table->uuid('subject_id');
            $table->uuid('source_id');
            $table->json('config')->nullable();
            $table->primary(['subject_id', 'source_id']);
            $table->foreign('subject_id')->references('id')->on('rd_subjects')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('rd_sources')->cascadeOnDelete();
        });

        Schema::create('rd_taxonomy_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->uuid('parent_id')->nullable();
            $table->string('label');
            $table->string('path');
            $table->timestamps();
            $table->index('path');
        });

        Schema::table('rd_taxonomy_nodes', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('rd_taxonomy_nodes')->nullOnDelete();
        });

        Schema::create('rd_source_tag_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_id');
            $table->string('raw_tag');
            $table->uuid('taxonomy_node_id');
            $table->timestamps();
            $table->unique(['source_id', 'raw_tag']);
            $table->foreign('source_id')->references('id')->on('rd_sources')->cascadeOnDelete();
            $table->foreign('taxonomy_node_id')->references('id')->on('rd_taxonomy_nodes')->cascadeOnDelete();
        });

        Schema::create('rd_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_id');
            $table->string('external_id');
            $table->string('url_hash', 64);
            $table->string('title');
            $table->text('url');
            $table->text('summary')->nullable();
            $table->longText('content_text')->nullable();
            $table->longText('content_html')->nullable();
            $table->string('language', 10)->default('en');
            $table->unsignedSmallInteger('estimated_read_time_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at');
            $table->boolean('force_include')->default(false);
            $table->boolean('force_exclude')->default(false);
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'external_id']);
            $table->unique('url_hash');
            $table->foreign('source_id')->references('id')->on('rd_sources')->cascadeOnDelete();
            $table->index(['published_at']);
        });

        Schema::create('rd_article_taxonomy', function (Blueprint $table) {
            $table->uuid('article_id');
            $table->uuid('taxonomy_node_id');
            $table->float('confidence')->default(1.0);
            $table->primary(['article_id', 'taxonomy_node_id']);
            $table->foreign('article_id')->references('id')->on('rd_articles')->cascadeOnDelete();
            $table->foreign('taxonomy_node_id')->references('id')->on('rd_taxonomy_nodes')->cascadeOnDelete();
        });

        Schema::create('rd_article_embeddings', function (Blueprint $table) {
            $table->uuid('article_id')->primary();
            $table->json('vector');
            $table->string('model');
            $table->timestamps();
            $table->foreign('article_id')->references('id')->on('rd_articles')->cascadeOnDelete();
        });

        Schema::create('rd_user_reading_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('preferences');
            $table->json('user_embedding')->nullable();
            $table->timestamp('embedding_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rd_user_interest_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('taxonomy_node_id');
            $table->float('score')->default(0);
            $table->timestamp('updated_at');
            $table->unique(['user_id', 'taxonomy_node_id']);
            $table->foreign('taxonomy_node_id')->references('id')->on('rd_taxonomy_nodes')->cascadeOnDelete();
        });

        Schema::create('rd_user_search_context', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query')->nullable();
            $table->uuid('taxonomy_node_id')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->foreign('taxonomy_node_id')->references('id')->on('rd_taxonomy_nodes')->nullOnDelete();
        });

        Schema::create('rd_digest_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('notification_time')->default('08:00');
            $table->string('timezone')->default('Asia/Ho_Chi_Minh');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('rd_digest_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status')->default('pending');
            $table->json('stats')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'run_date']);
        });

        Schema::create('rd_digest_run_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('digest_run_id');
            $table->uuid('subject_id');
            $table->uuid('article_id');
            $table->unsignedSmallInteger('rank');
            $table->float('retrieval_score')->nullable();
            $table->float('llm_score')->nullable();
            $table->text('llm_reason')->nullable();
            $table->string('tracking_token', 64)->unique();
            $table->timestamps();
            $table->foreign('digest_run_id')->references('id')->on('rd_digest_runs')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('rd_subjects')->cascadeOnDelete();
            $table->foreign('article_id')->references('id')->on('rd_articles')->cascadeOnDelete();
        });

        Schema::create('rd_article_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('article_id');
            $table->string('event');
            $table->json('metadata')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->timestamp('created_at');
            $table->foreign('article_id')->references('id')->on('rd_articles')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('rd_subjects')->nullOnDelete();
            $table->index(['user_id', 'article_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rd_article_interactions');
        Schema::dropIfExists('rd_digest_run_items');
        Schema::dropIfExists('rd_digest_runs');
        Schema::dropIfExists('rd_digest_settings');
        Schema::dropIfExists('rd_user_search_context');
        Schema::dropIfExists('rd_user_interest_scores');
        Schema::dropIfExists('rd_user_reading_profiles');
        Schema::dropIfExists('rd_article_embeddings');
        Schema::dropIfExists('rd_article_taxonomy');
        Schema::dropIfExists('rd_articles');
        Schema::dropIfExists('rd_source_tag_mappings');
        Schema::dropIfExists('rd_taxonomy_nodes');
        Schema::dropIfExists('rd_subject_source');
        Schema::dropIfExists('rd_sources');
        Schema::dropIfExists('rd_subjects');
    }
};
