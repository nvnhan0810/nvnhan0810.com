<?php

namespace App\Jobs\ReadingDigest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\ReadingDigest\Application\Handler\EmbedArticleHandler;

class EmbedArticleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $articleId) {}

    public function handle(EmbedArticleHandler $handler): void
    {
        $handler->handle($this->articleId);
    }
}
