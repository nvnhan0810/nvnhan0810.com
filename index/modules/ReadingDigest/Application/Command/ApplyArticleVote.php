<?php

namespace Modules\ReadingDigest\Application\Command;

use Modules\ReadingDigest\Domain\Enums\InteractionEvent;
use Modules\Shared\Application\Command;

final class ApplyArticleVote implements Command
{
    public function __construct(
        public readonly string $trackingToken,
        public readonly int $userId,
        public readonly InteractionEvent $event,
        /** @var list<string> */
        public readonly array $customTags = [],
        public readonly ?string $note = null,
    ) {}
}
