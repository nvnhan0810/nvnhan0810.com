<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function store(Request $request, \App\Domains\ReadingDigest\Application\Handlers\RecordInteractionHandler $handler)
    {
        $data = $request->validate([
            'article_id' => 'required|uuid|exists:rd_articles,id',
            'event' => 'required|string',
            'subject_id' => 'nullable|uuid|exists:rd_subjects,id',
            'metadata' => 'nullable|array',
        ]);

        $event = InteractionEvent::tryFrom($data['event']);
        if (! $event) {
            abort(422, 'Invalid event');
        }

        $handler->handle(
            $request->user()->id,
            $data['article_id'],
            $event,
            $data['metadata'] ?? null,
            $data['subject_id'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
