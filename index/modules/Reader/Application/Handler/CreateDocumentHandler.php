<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\CreateDocument;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Enums\DocumentStatus;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Ramsey\Uuid\Uuid;

final class CreateDocumentHandler implements CommandHandler
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function handle(Command $command): Document
    {
        assert($command instanceof CreateDocument);

        $id = $command->id ?? Uuid::uuid4()->toString();
        if (! Uuid::isValid($id)) {
            throw ReaderDomainException::validation('Invalid document id');
        }

        $existing = $this->documents->findByIdForUser($id, $command->userId, includeDeleted: true);
        if ($existing !== null) {
            $deleted = $existing->deletedAt !== null
                || $existing->status === DocumentStatus::Deleted;

            throw ReaderDomainException::conflict(
                $deleted
                    ? 'Document was deleted; do not recreate — remove local copy'
                    : 'Document already exists',
                [
                    'document_id' => $id,
                    'server_revision' => $existing->revision,
                    'deleted' => $deleted,
                    'deleted_at' => $existing->deletedAt?->format(DATE_ATOM),
                ],
            );
        }

        return $this->documents->create(
            $id,
            $command->userId,
            $command->title,
            max(0, $command->pageCount),
            DocumentStatus::Uploading,
        );
    }
}
