<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\ListDocumentInks;
use Modules\Reader\Domain\Enums\AnnotationFormat;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class ListDocumentInksHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly PageAnnotationRepository $annotations,
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @return array{
     *   document_id: string,
     *   pages: list<array{
     *     page_index: int,
     *     revision: int,
     *     updated_at: string,
     *     content_sha256: string|null,
     *     ink: array<string, mixed>
     *   }>
     * }
     */
    public function handle(Query $query): array
    {
        assert($query instanceof ListDocumentInks);

        $document = $this->documents->findByIdForUser($query->documentId, $query->userId);
        if ($document === null) {
            throw ReaderDomainException::notFound('Document not found');
        }

        $pages = [];
        foreach ($this->annotations->listByDocumentAndFormat(
            $document->id,
            AnnotationFormat::ReaderInkV1,
        ) as $annotation) {
            if ($annotation->isEmpty() || $annotation->seaweedKey === null) {
                continue;
            }

            $raw = $this->storage->get($annotation->seaweedKey);
            try {
                /** @var array<string, mixed> $ink */
                $ink = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $pages[] = [
                'page_index' => $annotation->pageIndex,
                'revision' => $annotation->revision,
                'updated_at' => $annotation->updatedAt->format(DATE_ATOM),
                'content_sha256' => $annotation->contentSha256,
                'ink' => $ink,
            ];
        }

        return [
            'document_id' => $document->id,
            'pages' => $pages,
        ];
    }
}
