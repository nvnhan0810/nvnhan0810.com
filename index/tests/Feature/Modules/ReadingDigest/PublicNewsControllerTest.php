<?php

namespace Tests\Feature\Modules\ReadingDigest;

use App\Models\RdArticle;
use App\Models\RdSource;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\Shared\Application\QueryBus;
use Tests\TestCase;

class PublicNewsControllerTest extends TestCase
{
    public function test_it_should_render_news_index_when_query_returns_articles(): void
    {
        $this->withoutVite();

        $source = new RdSource(['name' => 'Hacker News']);
        $source->id = '11111111-1111-1111-1111-111111111111';

        $article = new RdArticle([
            'title' => 'Laravel modules guide',
            'url' => 'https://example.com/laravel-modules',
            'summary' => 'How to structure modules.',
            'image_url' => null,
            'published_at' => now()->subHour(),
        ]);
        $article->id = '22222222-2222-2222-2222-222222222222';
        $article->setRelation('source', $source);

        $paginator = new LengthAwarePaginator(
            [$article],
            1,
            50,
            1,
            ['path' => '/news'],
        );

        $this->mock(QueryBus::class, function ($mock) use ($paginator): void {
            $mock->shouldReceive('ask')
                ->once()
                ->withArgs(fn ($query): bool => $query instanceof GetArticleList)
                ->andReturn($paginator);
        });

        $response = $this->get(route('news.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('public/NewsIndexPage', false)
            ->has('articles.data', 1)
            ->where('articles.data.0.id', '22222222-2222-2222-2222-222222222222')
            ->where('articles.data.0.title', 'Laravel modules guide')
            ->where('articles.data.0.url', 'https://example.com/laravel-modules')
            ->where('articles.data.0.source.name', 'Hacker News')
        );
    }
}
