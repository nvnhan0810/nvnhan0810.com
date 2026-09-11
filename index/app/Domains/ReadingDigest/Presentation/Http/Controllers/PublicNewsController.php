<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Application\Handlers\ApplyArticleVoteHandler;
use App\Domains\ReadingDigest\Application\Handlers\RecordInteractionHandler;
use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicNewsController extends Controller
{
    /**
     * Public feed: newest articles across sources.
     */
    public function index(Request $request)
    {
        $articles = DigestArticleModel::query()
            ->with('source:id,name')
            ->where('force_exclude', false)
            ->orderByDesc('published_at')
            ->orderByDesc('fetched_at')
            ->paginate(20)
            ->withPath(route('news.index', absolute: false))
            ->withQueryString()
            ->through(fn (DigestArticleModel $article) => $this->serializeArticle($article));

        return Inertia::render('public/NewsIndexPage', [
            'articles' => $articles,
        ]);
    }

    /**
     * Auth-only: today's digest picks (what Telegram used to list), grouped by source.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $run = DigestRunModel::query()
            ->where('user_id', $user->id)
            ->whereDate('run_date', $today)
            ->with(['items.article.source', 'items.subject'])
            ->first();

        $groups = [];

        if ($run) {
            $items = $run->items->sortBy('rank')->values();

            foreach ($items as $item) {
                $article = $item->article;
                if (! $article) {
                    continue;
                }

                $sourceName = $article->source?->name
                    ?? $item->subject?->name
                    ?? 'Other';

                $groups[$sourceName] ??= [];
                $groups[$sourceName][] = [
                    'id' => $item->id,
                    'rank' => $item->rank,
                    'tracking_token' => $item->tracking_token,
                    'subject' => $item->subject?->only(['id', 'name']),
                    'article' => $this->serializeArticle($article),
                ];
            }
        }

        return Inertia::render('public/NewsTodayPage', [
            'run' => $run ? [
                'id' => $run->id,
                'run_date' => $run->run_date,
                'status' => $run->status,
                'telegram_sent_at' => $run->telegram_sent_at,
            ] : null,
            'groups' => $groups,
        ]);
    }

    public function vote(
        string $token,
        Request $request,
        ApplyArticleVoteHandler $handler,
    ) {
        $item = $this->findOwnedItem($token, $request->user()->id);

        $data = $request->validate([
            'event' => 'required|in:liked,disliked',
        ]);

        $handler->handle(
            $item,
            $request->user()->id,
            InteractionEvent::from($data['event']),
        );

        return back()->with('success', 'Vote recorded.');
    }

    /**
     * Record Opened then redirect to the original article.
     */
    public function open(
        string $token,
        Request $request,
        RecordInteractionHandler $handler,
    ) {
        $item = $this->findOwnedItem($token, $request->user()->id);
        $url = $item->article?->url;

        if (! is_string($url) || $url === '') {
            abort(404);
        }

        try {
            $handler->handle(
                (int) $request->user()->id,
                $item->article_id,
                InteractionEvent::Opened,
                null,
                $item->subject_id,
            );
        } catch (\Throwable) {
            // Counting must not block the redirect.
        }

        return redirect()->away($url);
    }

    private function findOwnedItem(string $token, int $userId): DigestRunItemModel
    {
        $item = DigestRunItemModel::query()
            ->where('tracking_token', $token)
            ->with(['article', 'subject', 'digestRun'])
            ->firstOrFail();

        abort_unless((int) $item->digestRun?->user_id === $userId, 403);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(DigestArticleModel $article): array
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
