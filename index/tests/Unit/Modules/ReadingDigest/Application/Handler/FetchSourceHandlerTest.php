<?php

namespace Tests\Unit\Modules\ReadingDigest\Application\Handler;

use App\Models\RdArticle;
use App\Models\RdSource;
use Illuminate\Support\Facades\Schema;
use Modules\ReadingDigest\Application\DTOs\FetchedArticleDTO;
use Modules\ReadingDigest\Application\Handler\FetchSourceHandler;
use Modules\ReadingDigest\Domain\Repositories\SourceFetcherInterface;
use Modules\ReadingDigest\Infrastructure\Sources\SourceFetcherRegistry;
use Tests\TestCase;

final class FetchSourceHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (! Schema::hasTable('rd_articles') || ! Schema::hasTable('rd_sources')) {
                $this->markTestSkipped('Reading digest tables are unavailable in the testing database.');
            }
        } catch (\Throwable) {
            $this->markTestSkipped('Testing database is not usable for Reading Digest persistence tests.');
        }
    }

    public function test_it_should_skip_articles_with_published_at_in_the_future(): void
    {
        $source = RdSource::query()->create([
            'name' => 'Test Source',
            'type' => 'rss',
            'url' => 'https://example.com/feed.xml',
            'enabled' => true,
        ]);

        $fetcher = \Mockery::mock(SourceFetcherInterface::class);
        $fetcher->shouldReceive('fetch')
            ->once()
            ->andReturn([
                new FetchedArticleDTO(
                    externalId: 'future-1',
                    title: 'Future article',
                    url: 'https://example.com/future',
                    summary: 'Summary about laravel php',
                    contentText: null,
                    contentHtml: null,
                    publishedAt: now()->addDay(),
                    language: 'en',
                ),
                new FetchedArticleDTO(
                    externalId: 'past-1',
                    title: 'Past article',
                    url: 'https://example.com/past',
                    summary: 'Summary about laravel php',
                    contentText: null,
                    contentHtml: null,
                    publishedAt: now()->subHour(),
                    language: 'en',
                ),
            ]);

        $registry = \Mockery::mock(SourceFetcherRegistry::class);
        $registry->shouldReceive('for')->once()->andReturn($fetcher);

        $result = (new FetchSourceHandler($registry))->handle($source->id, 10);

        $this->assertSame(1, $result['stored']);
        $this->assertDatabaseHas('rd_articles', [
            'external_id' => 'past-1',
            'source_id' => $source->id,
        ]);
        $this->assertDatabaseMissing('rd_articles', [
            'external_id' => 'future-1',
        ]);

        RdArticle::query()->where('source_id', $source->id)->delete();
        $source->delete();
    }
}
