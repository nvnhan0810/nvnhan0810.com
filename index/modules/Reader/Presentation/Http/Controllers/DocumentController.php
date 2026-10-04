<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Reader\Application\Command\CreateDocument;
use Modules\Reader\Application\Command\DeleteDocument;
use Modules\Reader\Application\Command\SetDocumentFavorite;
use Modules\Reader\Application\Command\UpdateDocument;
use Modules\Reader\Application\Command\UploadDocumentFile;
use Modules\Reader\Application\Command\UploadThumbnail;
use Modules\Reader\Application\DTOs\DocumentPresenter;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetDocument;
use Modules\Reader\Application\Query\GetDocumentFile;
use Modules\Reader\Application\Query\GetThumbnail;
use Modules\Reader\Application\Query\ListDocuments;
use Modules\Reader\Application\Query\ListFavorites;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use App\Models\User;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends Controller
{
    public function index(Request $request, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $documents = $queryBus->ask(new ListDocuments(
            userId: $userId,
            includeDeleted: $request->boolean('include_deleted'),
            limit: (int) $request->integer('limit', 50),
            cursor: $request->query('cursor'),
        ));

        return response()->json([
            'data' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
        ]);
    }

    public function store(Request $request, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new CreateDocument(
                userId: $userId,
                id: $data['id'] ?? null,
                title: $data['title'],
                pageCount: (int) ($data['page_count'] ?? 0),
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'document' => DocumentPresenter::toArray($document),
            'upload' => [
                'file' => '/api/v1/documents/'.$document->id.'/file',
                'thumbnail' => '/api/v1/documents/'.$document->id.'/thumbnail',
            ],
        ], 201);
    }

    public function show(Request $request, string $id, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var Document $document */
            $document = $queryBus->ask(new GetDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['document' => DocumentPresenter::toArray($document)]);
    }

    public function update(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'base_revision' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new UpdateDocument(
                userId: $userId,
                documentId: $id,
                title: $data['title'],
                baseRevision: isset($data['base_revision']) ? (int) $data['base_revision'] : null,
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['document' => DocumentPresenter::toArray($document)]);
    }

    public function destroy(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new DeleteDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['document' => DocumentPresenter::toArray($document)]);
    }

    public function favorites(Request $request, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $documents = $queryBus->ask(new ListFavorites($userId));

        return response()->json([
            'data' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
        ]);
    }

    public function setFavorite(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'favorite' => ['required', 'boolean'],
        ]);

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new SetDocumentFavorite(
                userId: $userId,
                documentId: $id,
                favorite: (bool) $data['favorite'],
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['document' => DocumentPresenter::toArray($document)]);
    }

    public function uploadFile(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'file' => ['required', 'file'],
            'content_sha256' => ['nullable', 'string', 'size:64'],
            'page_count' => ['required', 'integer', 'min:0'],
        ]);

        $uploaded = $request->file('file');
        if ($uploaded === null) {
            return ApiErrorResponse::make('validation_error', 'file is required', 422);
        }

        $stream = fopen($uploaded->getRealPath(), 'rb');
        if ($stream === false) {
            return ApiErrorResponse::make('validation_error', 'Unable to open uploaded file', 422);
        }

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new UploadDocumentFile(
                userId: $userId,
                documentId: $id,
                stream: $stream,
                byteSize: (int) $uploaded->getSize(),
                pageCount: (int) $data['page_count'],
                contentSha256: $data['content_sha256'] ?? null,
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        } finally {
            fclose($stream);
        }

        return response()->json(['document' => DocumentPresenter::toArray($document)]);
    }

    public function downloadFile(Request $request, string $id, QueryBus $queryBus): JsonResponse|Response|StreamedResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var StoredObjectResult $result */
            $result = $queryBus->ask(new GetDocumentFile($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        if ($result->temporaryUrl !== null) {
            return redirect()->away($result->temporaryUrl);
        }

        return response($result->binary, 200, [
            'Content-Type' => $result->contentType,
            'ETag' => '"'.(string) $result->revision.'"',
            'X-Content-SHA256' => (string) $result->contentSha256,
        ]);
    }

    public function uploadThumbnail(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg'],
        ]);

        $uploaded = $request->file('file');
        if ($uploaded === null) {
            return ApiErrorResponse::make('validation_error', 'file is required', 422);
        }

        $contents = file_get_contents($uploaded->getRealPath());
        if ($contents === false) {
            return ApiErrorResponse::make('validation_error', 'Unable to read thumbnail', 422);
        }

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new UploadThumbnail(
                userId: $userId,
                documentId: $id,
                contents: $contents,
                byteSize: strlen($contents),
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['document' => DocumentPresenter::toArray($document)]);
    }

    public function downloadThumbnail(Request $request, string $id, QueryBus $queryBus): JsonResponse|Response
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var StoredObjectResult $result */
            $result = $queryBus->ask(new GetThumbnail($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        if ($result->temporaryUrl !== null) {
            return redirect()->away($result->temporaryUrl);
        }

        return response($result->binary, 200, [
            'Content-Type' => $result->contentType,
        ]);
    }

    private function userId(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : null;
    }
}
