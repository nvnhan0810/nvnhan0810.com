<?php

namespace Tests\Unit\Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use App\Models\RdArticleInteraction;
use App\Models\RdSource;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\ReadingDigest\Application\Handler\PurgeStaleArticlesHandler;
use Tests\TestCase;

final class PurgeStaleArticlesHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (! Schema::hasTable('rd_articles') || ! Schema::hasTable('rd_article_interactions')) {
                $this->markTestSkipped('Reading digest tables are unavailable in the testing database.');
            }
        } catch (\Throwable) {
            $this->markTestSkipped('Testing database is not usable for Reading Digest persistence tests.');
        }
    }

    public function test_it_should_delete_old_articles_without_interactions(): void
    {
        config(['reading-digest.content_retention_days' => 30]);

        $source = RdSource::query()->create([
            'name' => 'Test Source',
            'type' => 'rss',
            'url' => 'https://example.com/feed.xml',
            'enabled' => true,
        ]);

        $user = User::factory()->create();

        $stale = RdArticle::query()->create([
            'source_id' => $source->id,
            'external_id' => 'stale-1',
            'url_hash' => hash('sha256', 'https://example.com/stale'),
            'title' => 'Stale',
            'url' => 'https://example.com/stale',
            'language' => 'en',
            'fetched_at' => now()->subDays(40),
            'published_at' => now()->subDays(40),
            'force_include' => false,
            'force_exclude' => false,
        ]);

        $keptWithInteraction = RdArticle::query()->create([
            'source_id' => $source->id,
            'external_id' => 'kept-1',
            'url_hash' => hash('sha256', 'https://example.com/kept'),
            'title' => 'Kept',
            'url' => 'https://example.com/kept',
            'language' => 'en',
            'fetched_at' => now()->subDays(40),
            'published_at' => now()->subDays(40),
            'force_include' => false,
            'force_exclude' => false,
        ]);

        RdArticleInteraction::query()->create([
            'user_id' => $user->id,
            'article_id' => $keptWithInteraction->id,
            'event' => 'opened',
            'created_at' => now()->subDays(10),
        ]);

        $recent = RdArticle::query()->create([
            'source_id' => $source->id,
            'external_id' => 'recent-1',
            'url_hash' => hash('sha256', 'https://example.com/recent'),
            'title' => 'Recent',
            'url' => 'https://example.com/recent',
            'language' => 'en',
            'fetched_at' => now()->subDays(5),
            'published_at' => now()->subDays(5),
            'force_include' => false,
            'force_exclude' => false,
        ]);

        $deleted = (new PurgeStaleArticlesHandler)->handle();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('rd_articles', ['id' => $stale->id]);
        $this->assertDatabaseHas('rd_articles', ['id' => $keptWithInteraction->id]);
        $this->assertDatabaseHas('rd_articles', ['id' => $recent->id]);

        $stale->delete();
        $keptWithInteraction->delete();
        $recent->delete();
        $source->delete();
        $user->delete();
    }
}
