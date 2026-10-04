<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\ListAnnotations;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListAnnotationsHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly PageAnnotationRepository $annotations,
    ) {}

    /** @return array{document_id: string, pages: list<array<string, mixed>>} */
    public function handle(Query $query): array
    {
        assert($query instanceof ListAnnotations);
        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $pages = [];
        foreach ($this->annotations->listByDocument($document->id) as $annotation) {
            if ($annotation->isEmpty()) {
                continue;
            }
            $pages[] = [
                'page_index' => $annotation->pageIndex,
                'revision' => $annotation->revision,
                'empty' => false,
                'content_sha256' => $annotation->contentSha256,
                'format' => $annotation->format->value,
                'updated_at' => $annotation->updatedAt->format(DATE_ATOM),
            ];
        }

        return [
            'document_id' => $document->id,
            'pages' => $pages,
        ];
    }
}
