<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Reader\Infrastructure\Persistence\Eloquent\ReaderCollectionModel;
use Modules\Reader\Infrastructure\Persistence\Eloquent\ReaderDocumentModel;

final class EloquentCollectionRepository implements CollectionRepository
{
    public function findByIdForUser(string $collectionId, string $userId): ?Collection
    {
        $model = ReaderCollectionModel::query()
            ->withCount(['documents as document_count' => static function ($query): void {
                $query->whereNull('reader_documents.deleted_at');
            }])
            ->whereKey($collectionId)
            ->where('user_id', $userId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(string $userId): array
    {
        $models = ReaderCollectionModel::query()
            ->withCount(['documents as document_count' => static function ($query): void {
                $query->whereNull('reader_documents.deleted_at');
            }])
            ->where('user_id', $userId)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return array_map(
            fn (ReaderCollectionModel $model): Collection => $this->toDomain($model),
            $models->all(),
        );
    }

    public function create(string $id, string $userId, string $name): Collection
    {
        $model = new ReaderCollectionModel([
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'revision' => 1,
        ]);
        $model->save();

        return $this->toDomain($model->fresh() ?? $model, documentCount: 0);
    }

    public function save(Collection $collection): Collection
    {
        $model = ReaderCollectionModel::query()->findOrFail($collection->id);
        $model->fill([
            'name' => $collection->name,
            'revision' => $collection->revision,
        ]);
        $model->updated_at = $collection->updatedAt->format('Y-m-d H:i:s');
        $model->save();

        return $this->toDomain($model->fresh() ?? $model, documentCount: $collection->documentCount);
    }

    public function delete(string $collectionId, string $userId): void
    {
        ReaderCollectionModel::query()
            ->whereKey($collectionId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function addDocument(string $collectionId, string $documentId): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP');
        $collection = ReaderCollectionModel::query()->findOrFail($collectionId);
        $collection->documents()->syncWithoutDetaching([
            $documentId => ['added_at' => $now],
        ]);
    }

    public function removeDocument(string $collectionId, string $documentId): void
    {
        $collection = ReaderCollectionModel::query()->findOrFail($collectionId);
        $collection->documents()->detach($documentId);
    }

    public function hasDocument(string $collectionId, string $documentId): bool
    {
        return ReaderCollectionModel::query()
            ->whereKey($collectionId)
            ->whereHas('documents', static fn ($q) => $q->whereKey($documentId))
            ->exists();
    }

    public function listDocuments(string $collectionId, string $userId): array
    {
        $collection = ReaderCollectionModel::query()
            ->whereKey($collectionId)
            ->where('user_id', $userId)
            ->first();

        if ($collection === null) {
            return [];
        }

        $models = $collection->documents()
            ->where('reader_documents.user_id', $userId)
            ->orderByRaw('reader_documents.last_opened_at DESC NULLS LAST')
            ->orderBy('reader_documents.title')
            ->orderBy('reader_documents.id')
            ->get();

        return array_map(
            fn (ReaderDocumentModel $model): Document => $this->documentToDomain($model),
            $models->all(),
        );
    }

    private function toDomain(ReaderCollectionModel $model, ?int $documentCount = null): Collection
    {
        $count = $documentCount ?? (int) ($model->document_count ?? 0);

        return new Collection(
            id: (string) $model->id,
            userId: (string) $model->user_id,
            name: (string) $model->name,
            revision: (int) $model->revision,
            documentCount: $count,
            createdAt: DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
            updatedAt: DateTimeImmutable::createFromMutable($model->updated_at->toDateTime()),
        );
    }

    private function documentToDomain(ReaderDocumentModel $model): Document
    {
        return new Document(
            id: (string) $model->id,
            userId: (string) $model->user_id,
            title: (string) $model->title,
            pageCount: (int) $model->page_count,
            contentType: (string) $model->content_type,
            byteSize: (int) $model->byte_size,
            contentSha256: $model->content_sha256 !== null ? (string) $model->content_sha256 : null,
            seaweedPdfKey: $model->seaweed_pdf_key !== null ? (string) $model->seaweed_pdf_key : null,
            seaweedThumbKey: $model->seaweed_thumb_key !== null ? (string) $model->seaweed_thumb_key : null,
            status: DocumentStatus::from((string) $model->status),
            isFavorite: (bool) $model->is_favorite,
            revision: (int) $model->revision,
            deletedAt: $model->deleted_at !== null
                ? DateTimeImmutable::createFromMutable($model->deleted_at->toDateTime())
                : null,
            lastOpenedAt: $model->last_opened_at !== null
                ? DateTimeImmutable::createFromMutable($model->last_opened_at->toDateTime())
                : null,
            createdAt: DateTimeImmutable::createFromMutable($model->created_at->toDateTime()),
            updatedAt: DateTimeImmutable::createFromMutable($model->updated_at->toDateTime()),
        );
    }
}
