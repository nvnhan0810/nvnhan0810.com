<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;

interface DocumentRepository
{
    public function findByIdForUser(string $documentId, string $userId, bool $includeDeleted = false): ?Document;

    /** @return list<Document> */
    public function listForUser(string $userId, bool $includeDeleted, int $limit, ?string $cursor): array;

    public function create(
        string $id,
        string $userId,
        string $title,
        int $pageCount,
        DocumentStatus $status,
    ): Document;

    public function save(Document $document): Document;

    /** Favorites only (not trashed). No pagination. @return list<Document> */
    public function listFavoritesForUser(string $userId): array;

    /** Soft-deleted only (trash). @return list<Document> */
    public function listTrashForUser(string $userId, int $limit, ?string $cursor): array;

    /** Soft-deleted with deleted_at <= $before (all users). @return list<Document> */
    public function listTrashDeletedBefore(DateTimeImmutable $before): array;

    /** Permanently remove row (cascades progress + annotations). */
    public function hardDelete(string $documentId): void;

    /** @return list<Document> */
    public function changedSince(string $userId, DateTimeImmutable $since): array;
}
