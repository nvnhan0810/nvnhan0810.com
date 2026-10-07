<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
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
use Modules\Reader\Application\Query\ListDocuments;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AdminCollectionController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $userId = $this->userId($request);

        /** @var list<Collection> $collections */
        $collections = $this->queries->ask(new ListCollections($userId));

        return Inertia::render('domains/reader/pages/admin/collections/ListPage', [
            'collections' => array_map(
                static fn (Collection $collection): array => CollectionPresenter::toArray($collection),
                $collections,
            ),
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $userId = $this->userId($request);

        /** @var list<Document> $documents */
        $documents = $this->queries->ask(new ListDocuments(
            userId: $userId,
            includeDeleted: false,
            limit: 200,
            cursor: null,
        ));

        return Inertia::render('domains/reader/pages/admin/collections/FormPage', [
            'collection' => null,
            'memberIds' => [],
            'documents' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['uuid'],
        ]);

        $documentIds = array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            $data['document_ids'] ?? [],
        ));

        try {
            /** @var Collection $collection */
            $collection = $this->commands->dispatch(new CreateCollection(
                userId: $userId,
                name: $data['name'],
                id: null,
            ));

            foreach ($documentIds as $documentId) {
                $this->commands->dispatch(new AddDocumentToCollection(
                    userId: $userId,
                    collectionId: $collection->id,
                    documentId: $documentId,
                ));
            }
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'name' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.collections.index');
    }

    public function edit(Request $request, string $id): InertiaResponse
    {
        $userId = $this->userId($request);

        try {
            /** @var Collection $collection */
            $collection = $this->queries->ask(new GetCollection($userId, $id));
            /** @var list<Document> $members */
            $members = $this->queries->ask(new ListCollectionDocuments($userId, $id));
        } catch (ReaderDomainException) {
            abort(404);
        }

        /** @var list<Document> $documents */
        $documents = $this->queries->ask(new ListDocuments(
            userId: $userId,
            includeDeleted: false,
            limit: 200,
            cursor: null,
        ));

        return Inertia::render('domains/reader/pages/admin/collections/FormPage', [
            'collection' => CollectionPresenter::toArray($collection),
            'memberIds' => array_map(
                static fn (Document $document): string => $document->id,
                $members,
            ),
            'documents' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['uuid'],
        ]);

        $desired = array_values(array_unique(array_map(
            static fn (mixed $docId): string => (string) $docId,
            $data['document_ids'] ?? [],
        )));

        try {
            $this->commands->dispatch(new RenameCollection(
                userId: $userId,
                collectionId: $id,
                name: $data['name'],
            ));

            /** @var list<Document> $members */
            $members = $this->queries->ask(new ListCollectionDocuments($userId, $id));
            $current = array_map(
                static fn (Document $document): string => $document->id,
                $members,
            );

            foreach (array_diff($desired, $current) as $documentId) {
                $this->commands->dispatch(new AddDocumentToCollection(
                    userId: $userId,
                    collectionId: $id,
                    documentId: $documentId,
                ));
            }

            foreach (array_diff($current, $desired) as $documentId) {
                $this->commands->dispatch(new RemoveDocumentFromCollection(
                    userId: $userId,
                    collectionId: $id,
                    documentId: $documentId,
                ));
            }
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'name' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.collections.index');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $userId = $this->userId($request);

        try {
            $this->commands->dispatch(new DeleteCollection($userId, $id));
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'id' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.collections.index');
    }

    private function userId(Request $request): string
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (string) $user->id;
    }
}
