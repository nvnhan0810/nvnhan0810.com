<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Http\Controllers\Controller;

class ArticleRedirectController extends Controller
{
    public function show(
        string $token,
        \App\Domains\ReadingDigest\Application\Handlers\RecordInteractionHandler $handler,
    ) {
        $item = \App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel::query()
            ->where('tracking_token', $token)
            ->with(['article', 'digestRun'])
            ->firstOrFail();

        $handler->handle(
            $item->digestRun->user_id,
            $item->article_id,
            InteractionEvent::Opened,
            null,
            $item->subject_id,
        );

        return redirect()->away($item->article->url);
    }
}
