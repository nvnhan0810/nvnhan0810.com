<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\Command\AddDocumentToCollection;
use Modules\Reader\Application\Command\CreateCollection;
use Modules\Reader\Application\Command\DeleteCollection;
use Modules\Reader\Application\Command\RemoveDocumentFromCollection;
use Modules\Reader\Application\Command\RenameCollection;
use Modules\Reader\Application\DTOs\CollectionPresenter;
use Modules\Reader\Application\DTOs\DocumentPresenter;
use Modules\Reader\Application\Query\GetCollection;
use Modules\Reader\Application\Query\ListCollectionDocuments;
use Modules\Reader\Application\Query\ListCollections;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class CollectionController extends Controller
{
    public function index(Request $request, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $collections = $queryBus->ask(new ListCollections($userId));

        return response()->json([
            'data' => array_map(
                static fn (Collection $collection): array => CollectionPresenter::toArray($collection),
                $collections,
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
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            /** @var Collection $collection */
            $collection = $commandBus->dispatch(new CreateCollection(
                userId: $userId,
                name: $data['name'],
                id: $data['id'] ?? null,
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'collection' => CollectionPresenter::toArray($collection),
        ], 201);
    }

    public function show(Request $request, string $id, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var Collection $collection */
            $collection = $queryBus->ask(new GetCollection($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'collection' => CollectionPresenter::toArray($collection),
        ]);
    }

    public function update(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            /** @var Collection $collection */
            $collection = $commandBus->dispatch(new RenameCollection(
                userId: $userId,
                collectionId: $id,
                name: $data['name'],
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'collection' => CollectionPresenter::toArray($collection),
        ]);
    }

    public function destroy(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            $commandBus->dispatch(new DeleteCollection($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'deleted' => true,
            'collection_id' => $id,
        ]);
    }

    public function documents(Request $request, string $id, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            $documents = $queryBus->ask(new ListCollectionDocuments($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'data' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
        ]);
    }

    public function addDocument(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'document_id' => ['required', 'uuid'],
        ]);

        try {
            /** @var Collection $collection */
            $collection = $commandBus->dispatch(new AddDocumentToCollection(
                userId: $userId,
                collectionId: $id,
                documentId: $data['document_id'],
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'collection' => CollectionPresenter::toArray($collection),
        ]);
    }

    public function removeDocument(
        Request $request,
        string $id,
        string $documentId,
        CommandBus $commandBus,
    ): JsonResponse {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var Collection $collection */
            $collection = $commandBus->dispatch(new RemoveDocumentFromCollection(
                userId: $userId,
                collectionId: $id,
                documentId: $documentId,
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'collection' => CollectionPresenter::toArray($collection),
        ]);
    }

    private function userId(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : null;
    }
}
