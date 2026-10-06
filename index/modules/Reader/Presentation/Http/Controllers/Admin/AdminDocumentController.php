<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Reader\Application\Command\DeleteDocument;
use Modules\Reader\Application\Command\ImportDocumentFromUrl;
use Modules\Reader\Application\Command\IngestUploadedDocument;
use Modules\Reader\Application\Command\SetDocumentFavorite;
use Modules\Reader\Application\Command\SyncDocumentCollections;
use Modules\Reader\Application\Command\UpdateDocument;
use Modules\Reader\Application\DTOs\CollectionPresenter;
use Modules\Reader\Application\DTOs\DocumentPresenter;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetDocument;
use Modules\Reader\Application\Query\GetDocumentFile;
use Modules\Reader\Application\Query\ListCollections;
use Modules\Reader\Application\Query\ListDocumentCollectionIds;
use Modules\Reader\Application\Query\ListDocuments;
use Modules\Reader\Application\Service\ReaderUploadLimits;
use Modules\Reader\Domain\Entities\Collection;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AdminDocumentController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $userId = $this->userId($request);

        /** @var list<Document> $documents */
        $documents = $this->queries->ask(new ListDocuments(
            userId: $userId,
            includeDeleted: false,
            limit: 200,
            cursor: null,
        ));

        /** @var list<Collection> $collections */
        $collections = $this->queries->ask(new ListCollections($userId));

        return Inertia::render('domains/reader/pages/admin/documents/ListPage', [
            'documents' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
            'collections' => array_map(
                static fn (Collection $collection): array => CollectionPresenter::toArray($collection),
                $collections,
            ),
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $userId = $this->userId($request);

        /** @var list<Collection> $collections */
        $collections = $this->queries->ask(new ListCollections($userId));

        return Inertia::render('domains/reader/pages/admin/documents/CreatePage', [
            'collections' => array_map(
                static fn (Collection $collection): array => CollectionPresenter::toArray($collection),
                $collections,
            ),
            'maxPdfMb' => ReaderUploadLimits::maxMegabytesLabel(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);
        $maxPdfKb = ReaderUploadLimits::maxKilobytes();

        $data = $request->validate([
            'source' => ['required', 'in:upload,url'],
            'url' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:'.$maxPdfKb],
            'is_favorite' => ['nullable', 'boolean'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['uuid'],
        ]);

        $collectionIds = array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            $data['collection_ids'] ?? [],
        ));
        $isFavorite = (bool) ($data['is_favorite'] ?? false);
        $errorField = $data['source'] === 'url' ? 'url' : 'file';

        try {
            if ($data['source'] === 'url') {
                $url = trim((string) ($data['url'] ?? ''));
                if ($url === '') {
                    throw ValidationException::withMessages(['url' => 'URL is required.']);
                }

                $this->commands->dispatch(new ImportDocumentFromUrl(
                    userId: $userId,
                    title: '',
                    url: $url,
                    pageCount: 0,
                    collectionIds: $collectionIds,
                    isFavorite: $isFavorite,
                ));
            } else {
                $uploaded = $this->requireUploadedPdf($request);

                $path = $uploaded->getRealPath();
                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'Không đọc được file upload.']);
                }

                $originalName = pathinfo((string) $uploaded->getClientOriginalName(), PATHINFO_FILENAME);
                $title = $originalName !== '' ? $originalName : 'Untitled PDF';

                $this->commands->dispatch(new IngestUploadedDocument(
                    userId: $userId,
                    title: $title,
                    localPath: $path,
                    byteSize: (int) $uploaded->getSize(),
                    pageCount: 0,
                    collectionIds: $collectionIds,
                    isFavorite: $isFavorite,
                ));
            }
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                $errorField => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.documents.index');
    }

    public function edit(Request $request, string $id): InertiaResponse
    {
        $userId = $this->userId($request);

        try {
            /** @var Document $document */
            $document = $this->queries->ask(new GetDocument($userId, $id));
            /** @var list<string> $collectionIds */
            $collectionIds = $this->queries->ask(new ListDocumentCollectionIds($userId, $id));
        } catch (ReaderDomainException) {
            abort(404);
        }

        /** @var list<Collection> $collections */
        $collections = $this->queries->ask(new ListCollections($userId));

        return Inertia::render('domains/reader/pages/admin/documents/EditPage', [
            'document' => DocumentPresenter::toArray($document),
            'collectionIds' => $collectionIds,
            'collections' => array_map(
                static fn (Collection $collection): array => CollectionPresenter::toArray($collection),
                $collections,
            ),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_favorite' => ['nullable', 'boolean'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['uuid'],
        ]);

        $collectionIds = array_values(array_map(
            static fn (mixed $cid): string => (string) $cid,
            $data['collection_ids'] ?? [],
        ));

        try {
            $this->commands->dispatch(new UpdateDocument(
                userId: $userId,
                documentId: $id,
                title: $data['title'],
                baseRevision: null,
            ));

            $this->commands->dispatch(new SetDocumentFavorite(
                userId: $userId,
                documentId: $id,
                favorite: (bool) ($data['is_favorite'] ?? false),
            ));

            $this->commands->dispatch(new SyncDocumentCollections(
                userId: $userId,
                documentId: $id,
                collectionIds: $collectionIds,
            ));
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'title' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.documents.index');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $userId = $this->userId($request);

        try {
            $this->commands->dispatch(new DeleteDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'id' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.documents.index');
    }

    public function show(Request $request, string $id): InertiaResponse
    {
        $userId = $this->userId($request);

        try {
            /** @var Document $document */
            $document = $this->queries->ask(new GetDocument($userId, $id));
        } catch (ReaderDomainException) {
            abort(404);
        }

        $fileAccess = $this->fileAccess($userId, $id);
        $openUrl = route('admin.reader.documents.file', $id);

        return Inertia::render('domains/reader/pages/admin/documents/ViewPage', [
            'document' => DocumentPresenter::toArray($document),
            'fileUrl' => $fileAccess->temporaryUrl ?? $openUrl,
            'fileOpenUrl' => $openUrl,
        ]);
    }

    public function file(Request $request, string $id): RedirectResponse|StreamedResponse
    {
        $userId = $this->userId($request);

        try {
            $fileAccess = $this->fileAccess($userId, $id);
        } catch (ReaderDomainException) {
            abort(404);
        }

        if ($fileAccess->temporaryUrl !== null) {
            return redirect()->away($fileAccess->temporaryUrl);
        }

        try {
            /** @var Document $document */
            $document = $this->queries->ask(new GetDocument($userId, $id));
        } catch (ReaderDomainException) {
            abort(404);
        }

        $key = $document->seaweedPdfKey;
        if ($key === null || $key === '') {
            abort(404);
        }

        $disk = Storage::disk((string) config('reader.disk', 's3'));
        if (! $disk->exists($key)) {
            abort(404);
        }

        $filename = Str::slug($document->title);
        if ($filename === '') {
            $filename = 'document';
        }

        return $disk->response(
            $key,
            $filename.'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, max-age=60',
            ],
            'inline',
        );
    }

    private function fileAccess(string $userId, string $documentId): StoredObjectResult
    {
        /** @var StoredObjectResult $result */
        $result = $this->queries->ask(new GetDocumentFile($userId, $documentId));

        return $result;
    }

    private function requireUploadedPdf(Request $request): UploadedFile
    {
        $uploaded = $request->file('file');
        $maxMb = ReaderUploadLimits::maxMegabytesLabel();

        if ($uploaded instanceof UploadedFile) {
            $error = $uploaded->getError();
            if ($error === UPLOAD_ERR_OK) {
                return $uploaded;
            }

            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "File vượt giới hạn upload (tối đa {$maxMb} MB).",
                UPLOAD_ERR_PARTIAL => 'Upload bị gián đoạn — thử lại.',
                UPLOAD_ERR_NO_FILE => 'Chọn file PDF trước khi tạo.',
                default => 'Upload thất bại (mã lỗi '.$error.').',
            };

            throw ValidationException::withMessages(['file' => $message]);
        }

        $contentLength = (int) $request->header('Content-Length', '0');
        if ($contentLength > ReaderUploadLimits::maxBytes()) {
            throw ValidationException::withMessages([
                'file' => "File vượt giới hạn (tối đa {$maxMb} MB).",
            ]);
        }

        throw ValidationException::withMessages([
            'file' => "Không nhận được file PDF. File quá lớn (>{maxMb} MB), vượt giới hạn PHP/nginx, hoặc upload timeout.",
        ]);
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
