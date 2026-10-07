<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use DateTimeImmutable;
use DateMalformedStringException;
use DateTimeZone;
use Modules\Reader\Application\DTOs\DocumentPresenter;
use Modules\Reader\Application\Query\GetSyncChanges;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Reader\Domain\Ports\ReadingProgressRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetSyncChangesHandler implements QueryHandler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly ReadingProgressRepository $progress,
        private readonly PageAnnotationRepository $annotations,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Query $query): array
    {
        assert($query instanceof GetSyncChanges);

        $since = $this->parseSince($query->since);

        // UTC "Z" cursor avoids "+" → space breakage in query strings.
        $serverTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $cursor = $serverTime->format('Y-m-d\TH:i:s\Z');

        $documents = [];
        foreach ($this->documents->changedSince($query->userId, $since) as $document) {
            $documents[] = DocumentPresenter::toArray($document);
        }

        $progress = [];
        foreach ($this->progress->changedSince($query->userId, $since) as $item) {
            $progress[] = [
                'document_id' => $item->documentId,
                'page_index' => $item->pageIndex,
                'revision' => $item->revision,
                'updated_at' => $item->updatedAt
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        $annotations = [];
        foreach ($this->annotations->changedSince($query->userId, $since) as $annotation) {
            $annotations[] = [
                'document_id' => $annotation->documentId,
                'page_index' => $annotation->pageIndex,
                'format' => $annotation->format->value,
                'revision' => $annotation->revision,
                'updated_at' => $annotation->updatedAt
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z'),
                'empty' => $annotation->isEmpty(),
                'content_sha256' => $annotation->contentSha256,
            ];
        }

        return [
            'server_time' => $cursor,
            'next_cursor' => $cursor,
            'documents' => $documents,
            'progress' => $progress,
            'annotations' => $annotations,
        ];
    }

    private function parseSince(?string $since): DateTimeImmutable
    {
        if ($since === null || $since === '') {
            return new DateTimeImmutable('@0');
        }

        // Query strings decode "+" as space, so "+07:00" becomes " 07:00".
        $normalized = preg_replace(
            '/T(\d{2}:\d{2}:\d{2}(?:\.\d+)?) (\d{2}:\d{2})$/',
            'T$1+$2',
            $since,
        ) ?? $since;

        try {
            return new DateTimeImmutable($normalized);
        } catch (DateMalformedStringException) {
            throw ReaderDomainException::validation('Invalid since cursor', [
                'since' => $since,
            ]);
        }
    }
}
