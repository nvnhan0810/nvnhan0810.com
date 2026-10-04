<?php

declare(strict_types=1);

namespace Modules\Reader\Application\DTOs;

use DateTimeZone;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;

final class DocumentPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Document $document): array
    {
        $deleted = $document->deletedAt !== null
            || $document->status === DocumentStatus::Deleted;

        $purgeAt = null;
        if ($deleted && $document->deletedAt !== null) {
            $days = max(1, (int) config('reader.trash_retention_days', 60));
            $purgeAt = $document->deletedAt
                ->setTimezone(new DateTimeZone('UTC'))
                ->modify("+{$days} days")
                ->format('Y-m-d\TH:i:s\Z');
        }

        return [
            'id' => $document->id,
            'title' => $document->title,
            'page_count' => $document->pageCount,
            'content_type' => $document->contentType,
            'byte_size' => $document->byteSize,
            'content_sha256' => $document->contentSha256,
            'status' => $document->status->value,
            'is_favorite' => $document->isFavorite,
            'revision' => $document->revision,
            'has_thumbnail' => $document->hasThumbnail(),
            'deleted' => $deleted,
            'deleted_at' => $document->deletedAt?->format(DATE_ATOM),
            'purge_at' => $purgeAt,
            'last_opened_at' => $document->lastOpenedAt?->format(DATE_ATOM),
            'created_at' => $document->createdAt->format(DATE_ATOM),
            'updated_at' => $document->updatedAt->format(DATE_ATOM),
        ];
    }
}
