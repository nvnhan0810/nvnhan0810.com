<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Application\Handlers\ApplyArticleVoteHandler;
use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArticleVoteController extends Controller
{
    public function show(string $token, Request $request)
    {
        $item = $this->findItem($token);

        abort_unless($request->user()->id === $item->digestRun->user_id, 403);

        return Inertia::render('domains/reading-digest/pages/VotePage', [
            'item' => [
                'id' => $item->id,
                'tracking_token' => $item->tracking_token,
                'read_url' => route('reading-digest.article.redirect', $item->tracking_token),
                'subject' => $item->subject?->only(['id', 'name']),
                'article' => [
                    'id' => $item->article->id,
                    'title' => $item->article->title,
                    'summary' => $item->article->summary,
                    'url' => $item->article->url,
                    'metadata' => $item->article->metadata,
                    'source' => $item->article->source?->only(['id', 'name']),
                ],
            ],
        ]);
    }

    public function store(string $token, Request $request, ApplyArticleVoteHandler $handler)
    {
        $item = $this->findItem($token);

        abort_unless($request->user()->id === $item->digestRun->user_id, 403);

        $data = $request->validate([
            'event' => 'required|in:liked,disliked,dismissed',
            'custom_tags' => 'nullable|array',
            'custom_tags.*' => 'string|max:50',
            'note' => 'nullable|string|max:500',
        ]);

        $event = InteractionEvent::from($data['event']);
        $customTags = collect($data['custom_tags'] ?? [])
            ->map(fn ($tag) => strtolower(trim($tag)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $handler->handle(
            $item,
            $request->user()->id,
            $event,
            $customTags,
            $data['note'] ?? null,
        );

        return back()->with('success', 'Vote recorded. Thanks for the feedback!');
    }

    private function findItem(string $token): DigestRunItemModel
    {
        return DigestRunItemModel::query()
            ->where('tracking_token', $token)
            ->with([
                'article.source',
                'subject',
                'digestRun',
            ])
            ->firstOrFail();
    }
}
