<?php

declare(strict_types=1);

namespace Modules\Reader\Application\DTOs;

use Modules\Reader\Domain\Entities\Collection;

final class CollectionPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Collection $collection): array
    {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'document_count' => $collection->documentCount,
            'revision' => $collection->revision,
            'created_at' => $collection->createdAt->format(DATE_ATOM),
            'updated_at' => $collection->updatedAt->format(DATE_ATOM),
        ];
    }
}
