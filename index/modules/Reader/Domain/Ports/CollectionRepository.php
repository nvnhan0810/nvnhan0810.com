<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Entities\Document;

interface CollectionRepository
{
    public function findByIdForUser(string $collectionId, string $userId): ?Collection;

    /** @return list<Collection> */
    public function listForUser(string $userId): array;

    public function create(string $id, string $userId, string $name): Collection;

    public function save(Collection $collection): Collection;

    /** Deletes collection row only; documents are kept. */
    public function delete(string $collectionId, string $userId): void;

    public function addDocument(string $collectionId, string $documentId): void;

    public function removeDocument(string $collectionId, string $documentId): void;

    public function hasDocument(string $collectionId, string $documentId): bool;

    /** Non-trashed documents in collection. @return list<Document> */
    public function listDocuments(string $collectionId, string $userId): array;
}
