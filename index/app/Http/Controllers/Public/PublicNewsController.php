<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RdArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ReadingDigest\Application\Command\ApplyArticleVote;
use Modules\ReadingDigest\Application\Command\RecordArticleOpened;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\ReadingDigest\Application\Query\GetTodayDigest;
use Modules\ReadingDigest\Domain\Enums\InteractionEvent;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PublicNewsController extends Controller
{
    /**
     * Public feed: newest articles across sources.
     */
    public function index(QueryBus $queryBus): Response
    {
        $articles = $queryBus
            ->ask(new GetArticleList)
            ->through(fn (RdArticle $article) => $this->serializeArticle($article));

        return Inertia::render('public/NewsIndexPage', [
            'articles' => $articles,
        ]);
    }

    /**
     * Auth-only: today's digest picks, grouped by source.
     */
    public function today(Request $request, QueryBus $queryBus): Response
    {
        $payload = $queryBus->ask(new GetTodayDigest(
            userId: (int) $request->user()->id,
            date: now()->toDateString(),
        ));

        return Inertia::render('public/NewsTodayPage', $payload);
    }

    public function vote(
        string $token,
        Request $request,
        CommandBus $commandBus,
    ): RedirectResponse {
        $data = $request->validate([
            'event' => 'required|in:liked,disliked',
        ]);

        $commandBus->dispatch(new ApplyArticleVote(
            trackingToken: $token,
            userId: (int) $request->user()->id,
            event: InteractionEvent::from($data['event']),
        ));

        return back()->with('success', 'Vote recorded.');
    }

    /**
     * Record Opened then redirect to the original article.
     */
    public function open(
        string $token,
        Request $request,
        CommandBus $commandBus,
    ): RedirectResponse {
        $url = $commandBus->dispatch(new RecordArticleOpened(
            trackingToken: $token,
            userId: (int) $request->user()->id,
        ));

        return redirect()->away($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(RdArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'url' => $article->url,
            'summary' => $article->summary,
            'image_url' => $article->image_url,
            'published_at' => $article->published_at,
            'source' => $article->source?->only(['id', 'name']),
        ];
    }
}
