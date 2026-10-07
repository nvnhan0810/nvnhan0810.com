<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\GetReadingProgress;
use Modules\Reader\Domain\Entities\ReadingProgress;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ReadingProgressRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetReadingProgressHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ReadingProgressRepository $progress,
    ) {}

    public function handle(Query $query): ReadingProgress
    {
        assert($query instanceof GetReadingProgress);
        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $progress = $this->progress->findByDocument($document->id, $query->userId);
        if ($progress === null) {
            throw ReaderDomainException::notFound('Progress not found');
        }

        return $progress;
    }
}
