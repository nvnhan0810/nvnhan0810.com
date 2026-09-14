<?php

namespace Tests\Feature\Modules\ReadingDigest;

use App\Models\RdArticle;
use App\Models\RdSource;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\ReadingDigest\Application\Command\ApplyArticleVote;
use Modules\ReadingDigest\Application\Command\RecordArticleOpened;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\ReadingDigest\Application\Query\GetTodayDigest;
use Modules\ReadingDigest\Domain\Enums\InteractionEvent;
use Modules\Shared\Application\CommandBus;
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

    public function test_it_should_render_news_today_when_query_returns_digest(): void
    {
        $this->withoutVite();

        $user = User::factory()->make(['id' => 1]);

        $payload = [
            'run' => [
                'id' => '33333333-3333-3333-3333-333333333333',
                'run_date' => now()->toDateString(),
                'status' => 'ready',
                'telegram_sent_at' => null,
            ],
            'groups' => [
                'Hacker News' => [[
                    'id' => '44444444-4444-4444-4444-444444444444',
                    'rank' => 1,
                    'tracking_token' => 'tok-abc',
                    'subject' => ['id' => 's1', 'name' => 'Laravel'],
                    'article' => [
                        'id' => 'a1',
                        'title' => 'Today pick',
                        'url' => 'https://example.com/today',
                        'summary' => null,
                        'image_url' => null,
                        'published_at' => null,
                        'source' => ['id' => 'src1', 'name' => 'Hacker News'],
                    ],
                ]],
            ],
        ];

        $this->mock(QueryBus::class, function ($mock) use ($payload): void {
            $mock->shouldReceive('ask')
                ->once()
                ->withArgs(fn ($query): bool => $query instanceof GetTodayDigest)
                ->andReturn($payload);
        });

        $response = $this->actingAs($user)->get(route('news.today'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('public/NewsTodayPage', false)
            ->where('run.id', '33333333-3333-3333-3333-333333333333')
            ->has('groups.Hacker News', 1)
            ->where('groups.Hacker News.0.tracking_token', 'tok-abc')
        );
    }

    public function test_it_should_dispatch_vote_command_when_user_votes(): void
    {
        $user = User::factory()->make(['id' => 9]);

        $this->mock(CommandBus::class, function ($mock): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(function ($command): bool {
                    return $command instanceof ApplyArticleVote
                        && $command->trackingToken === 'tok-vote'
                        && $command->userId === 9
                        && $command->event === InteractionEvent::Liked;
                })
                ->andReturn(null);
        });

        $response = $this->actingAs($user)->post(route('news.vote', 'tok-vote'), [
            'event' => 'liked',
        ]);

        $response->assertRedirect();
    }

    public function test_it_should_redirect_away_when_open_command_returns_url(): void
    {
        $user = User::factory()->make(['id' => 3]);

        $this->mock(CommandBus::class, function ($mock): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(fn ($command): bool => $command instanceof RecordArticleOpened
                    && $command->trackingToken === 'tok-open'
                    && $command->userId === 3)
                ->andReturn('https://example.com/article');
        });

        $response = $this->actingAs($user)->get(route('news.open', 'tok-open'));

        $response->assertRedirect('https://example.com/article');
    }
}
