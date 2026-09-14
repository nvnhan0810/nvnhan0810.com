<?php

namespace Tests\Unit\Modules\ReadingDigest\Application\Handler;

use Modules\ReadingDigest\Application\Handler\RebuildUserEmbeddingHandler;
use Tests\TestCase;

final class RebuildUserEmbeddingHandlerTest extends TestCase
{
    public function test_it_should_collect_distinct_user_ids_from_profiles_and_interactions(): void
    {
        $handler = new class extends RebuildUserEmbeddingHandler
        {
            /** @var list<int> */
            public array $handled = [];

            public function handle(int $userId): void
            {
                $this->handled[] = $userId;
            }

            /**
             * @return list<int>
             */
            public function exposeUserIds(iterable $profileIds, iterable $interactionIds): array
            {
                return $this->uniqueUserIds($profileIds, $interactionIds);
            }
        };

        $ids = $handler->exposeUserIds([1, 2, 2], [2, 3]);

        $this->assertSame([1, 2, 3], $ids);
    }
}
