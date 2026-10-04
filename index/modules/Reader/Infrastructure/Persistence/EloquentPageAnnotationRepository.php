<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\PageAnnotation;
use Modules\Reader\Domain\Enums\AnnotationFormat;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Reader\Infrastructure\Persistence\Eloquent\ReaderPageAnnotationModel;

final class EloquentPageAnnotationRepository implements PageAnnotationRepository
{
    public function findByDocumentAndPage(string $documentId, int $pageIndex): ?PageAnnotation
    {
        $model = ReaderPageAnnotationModel::query()
            ->where('document_id', $documentId)
            ->where('page_index', $pageIndex)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listByDocument(string $documentId): array
    {
        $models = ReaderPageAnnotationModel::query()
            ->where('document_id', $documentId)
            ->orderBy('page_index')
            ->get();

        return array_map(
            fn (ReaderPageAnnotationModel $model): PageAnnotation => $this->toDomain($model),
            $models->all(),
        );
    }

    public function save(PageAnnotation $annotation): PageAnnotation
    {
        $model = ReaderPageAnnotationModel::query()->find($annotation->id) ?? new ReaderPageAnnotationModel([
            'id' => $annotation->id,
        ]);

        $model->fill([
            'document_id' => $annotation->documentId,
            'user_id' => $annotation->userId,
            'page_index' => $annotation->pageIndex,
            'format' => $annotation->format->value,
            'seaweed_key' => $annotation->seaweedKey,
            'byte_size' => $annotation->byteSize,
            'content_sha256' => $annotation->contentSha256,
            'revision' => $annotation->revision,
            'updated_at' => $annotation->updatedAt->format('Y-m-d H:i:s'),
        ]);
        $model->save();

        return $this->toDomain($model->fresh() ?? $model);
    }

    public function changedSince(string $userId, DateTimeImmutable $since): array
    {
        $models = ReaderPageAnnotationModel::query()
            ->where('user_id', $userId)
            ->where(
                'updated_at',
                '>',
                $since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP'),
            )
            ->orderBy('updated_at')
            ->get();

        return array_map(
            fn (ReaderPageAnnotationModel $model): PageAnnotation => $this->toDomain($model),
            $models->all(),
        );
    }

    private function toDomain(ReaderPageAnnotationModel $model): PageAnnotation
    {
        return new PageAnnotation(
            id: (string) $model->id,
            documentId: (string) $model->document_id,
            userId: (string) $model->user_id,
            pageIndex: (int) $model->page_index,
            format: AnnotationFormat::from((string) $model->format),
            seaweedKey: $model->seaweed_key !== null ? (string) $model->seaweed_key : null,
            byteSize: (int) $model->byte_size,
            contentSha256: $model->content_sha256 !== null ? (string) $model->content_sha256 : null,
            revision: (int) $model->revision,
            updatedAt: DateTimeImmutable::createFromMutable($model->updated_at->toDateTime()),
        );
    }
}
