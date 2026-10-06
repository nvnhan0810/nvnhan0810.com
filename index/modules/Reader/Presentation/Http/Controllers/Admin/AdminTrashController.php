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
use Modules\Reader\Application\Command\EmptyTrash;
use Modules\Reader\Application\Command\PurgeDocument;
use Modules\Reader\Application\Command\RestoreDocument;
use Modules\Reader\Application\DTOs\DocumentPresenter;
use Modules\Reader\Application\Query\ListTrash;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AdminTrashController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $userId = $this->userId($request);

        /** @var list<Document> $documents */
        $documents = $this->queries->ask(new ListTrash(
            userId: $userId,
            limit: 200,
            cursor: null,
        ));

        return Inertia::render('domains/reader/pages/admin/trash/ListPage', [
            'documents' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
            'retentionDays' => (int) config('reader.trash_retention_days', 60),
        ]);
    }

    public function restore(Request $request, string $id): RedirectResponse
    {
        $userId = $this->userId($request);

        try {
            $this->commands->dispatch(new RestoreDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'id' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.trash.index');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $userId = $this->userId($request);

        try {
            $this->commands->dispatch(new PurgeDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            throw ValidationException::withMessages([
                'id' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.reader.trash.index');
    }

    public function empty(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        $this->commands->dispatch(new EmptyTrash($userId));

        return redirect()->route('admin.reader.trash.index');
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
