<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Reader\Domain\Entities\ReadingProgress;
use Modules\Reader\Domain\Ports\ReadingProgressRepository;
use Modules\Reader\Infrastructure\Persistence\Eloquent\ReaderReadingProgressModel;

final class EloquentReadingProgressRepository implements ReadingProgressRepository
{
    public function findByDocument(string $documentId, string $userId): ?ReadingProgress
    {
        $model = ReaderReadingProgressModel::query()
            ->where('document_id', $documentId)
            ->where('user_id', $userId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(ReadingProgress $progress): ReadingProgress
    {
        $model = ReaderReadingProgressModel::query()->find($progress->documentId)
            ?? new ReaderReadingProgressModel(['document_id' => $progress->documentId]);

        $model->fill([
            'user_id' => $progress->userId,
            'page_index' => $progress->pageIndex,
            'revision' => $progress->revision,
            'updated_at' => $progress->updatedAt->format('Y-m-d H:i:s'),
        ]);
        $model->save();

        return $this->toDomain($model->fresh() ?? $model);
    }

    public function changedSince(string $userId, DateTimeImmutable $since): array
    {
        $models = ReaderReadingProgressModel::query()
            ->where('user_id', $userId)
            ->where(
                'updated_at',
                '>',
                $since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP'),
            )
            ->orderBy('updated_at')
            ->get();

        return array_map(
            fn (ReaderReadingProgressModel $model): ReadingProgress => $this->toDomain($model),
            $models->all(),
        );
    }

    private function toDomain(ReaderReadingProgressModel $model): ReadingProgress
    {
        return new ReadingProgress(
            documentId: (string) $model->document_id,
            userId: (string) $model->user_id,
            pageIndex: (int) $model->page_index,
            revision: (int) $model->revision,
            updatedAt: DateTimeImmutable::createFromMutable($model->updated_at->toDateTime()),
        );
    }
}
