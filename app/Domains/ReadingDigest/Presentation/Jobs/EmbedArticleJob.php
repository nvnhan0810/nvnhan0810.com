<?php

namespace App\Domains\ReadingDigest\Presentation\Jobs;

use App\Domains\ReadingDigest\Application\Handlers\EmbedArticleHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedArticleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $articleId) {}

    public function handle(EmbedArticleHandler $handler): void
    {
        $handler->handle($this->articleId);
    }
}
