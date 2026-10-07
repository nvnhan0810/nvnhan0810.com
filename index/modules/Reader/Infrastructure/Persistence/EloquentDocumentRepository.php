<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Infrastructure\Persistence\Eloquent\ReaderDocumentModel;

final class EloquentDocumentRepository implements DocumentRepository
{
    public function findByIdForUser(string $documentId, string $userId, bool $includeDeleted = false): ?Document
    {
        $query = ReaderDocumentModel::query()
            ->whereKey($documentId)
            ->where('user_id', $userId);

        if ($includeDeleted) {
            $query->withTrashed();
        }

        $model = $query->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(string $userId, bool $includeDeleted, int $limit, ?string $cursor): array
    {
        $query = ReaderDocumentModel::query()
            ->where('user_id', $userId)
            ->orderByRaw('last_opened_at DESC NULLS LAST')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($includeDeleted) {
            $query->withTrashed();
        }

        if ($cursor !== null && $cursor !== '') {
            // Cursor is last_opened_at (ISO); never-opened docs use updated_at fallback on client.
            $query->where(function ($builder) use ($cursor): void {
                $builder
                    ->where('last_opened_at', '<', $cursor)
                    ->orWhere(function ($inner) use ($cursor): void {
                        $inner->whereNull('last_opened_at')
                            ->where('updated_at', '<', $cursor);
                    });
            });
        }

        return array_map(
            fn (ReaderDocumentModel $model): Document => $this->toDomain($model),
            $query->get()->all(),
        );
    }

    public function listFavoritesForUser(string $userId): array
    {
        $models = ReaderDocumentModel::query()
            ->where('user_id', $userId)
            ->where('is_favorite', true)
            ->orderByRaw('last_opened_at DESC NULLS LAST')
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return array_map(
            fn (ReaderDocumentModel $model): Document => $this->toDomain($model),
            $models->all(),
        );
    }

    public function create(
        string $id,
        string $userId,
        string $title,
        int $pageCount,
        DocumentStatus $status,
    ): Document {
        $model = new ReaderDocumentModel([
            'id' => $id,
            'user_id' => $userId,
            'title' => $title,
            'page_count' => $pageCount,
            'content_type' => 'application/pdf',
            'byte_size' => 0,
            'status' => $status->value,
            'revision' => 1,
        ]);
        $model->save();

        return $this->toDomain($model->fresh() ?? $model);
    }

    public function save(Document $document): Document
    {
        $model = ReaderDocumentModel::query()->withTrashed()->findOrFail($document->id);
        $model->fill([
            'title' => $document->title,
            'page_count' => $document->pageCount,
            'content_type' => $document->contentType,
            'byte_size' => $document->byteSize,
            'content_sha256' => $document->contentSha256,
            'seaweed_pdf_key' => $document->seaweedPdfKey,
            'seaweed_thumb_key' => $document->seaweedThumbKey,
            'status' => $document->status->value,
            'is_favorite' => $document->isFavorite,
            'revision' => $document->revision,
            'last_opened_at' => $document->lastOpenedAt?->format('Y-m-d H:i:s'),
        ]);

        if ($document->deletedAt !== null) {
            $model->deleted_at = $document->deletedAt->format('Y-m-d H:i:s');
        } else {
            $model->deleted_at = null;
        }

        $model->updated_at = $document->updatedAt->format('Y-m-d H:i:s');
        $model->save();

        return $this->toDomain($model->fresh() ?? $model);
    }

    public function listTrashForUser(string $userId, int $limit, ?string $cursor): array
    {
        $query = ReaderDocumentModel::onlyTrashed()
            ->where('user_id', $userId)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($cursor !== null && $cursor !== '') {
            $query->where('deleted_at', '<', $cursor);
        }

        return array_map(
            fn (ReaderDocumentModel $model): Document => $this->toDomain($model),
            $query->get()->all(),
        );
    }

    public function listTrashDeletedBefore(DateTimeImmutable $before): array
    {
        $models = ReaderDocumentModel::onlyTrashed()
            ->where(
                'deleted_at',
                '<=',
                $before->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP'),
            )
            ->orderBy('deleted_at')
            ->get();

        return array_map(
            fn (ReaderDocumentModel $model): Document => $this->toDomain($model),
            $models->all(),
        );
    }

    public function hardDelete(string $documentId): void
    {
        $model = ReaderDocumentModel::withTrashed()->find($documentId);
        if ($model === null) {
            return;
        }

        $model->forceDelete();
    }

    public function changedSince(string $userId, DateTimeImmutable $since): array
    {
        $models = ReaderDocumentModel::query()
            ->withTrashed()
            ->where('user_id', $userId)
            ->where(
                'updated_at',
                '>',
                $since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP'),
            )
            ->orderBy('updated_at')
            ->get();

        return array_map(
            fn (ReaderDocumentModel $model): Document => $this->toDomain($model),
            $models->all(),
        );
    }

    private function toDomain(ReaderDocumentModel $model): Document
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
