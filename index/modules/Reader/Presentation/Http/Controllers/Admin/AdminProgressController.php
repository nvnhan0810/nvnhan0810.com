<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\Command\PutReadingProgress;
use Modules\Reader\Application\Query\GetDocument;
use Modules\Reader\Application\Query\GetReadingProgress;
use Modules\Reader\Domain\Entities\ReadingProgress;
use Modules\Reader\Domain\Enums\ApiErrorCode;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AdminProgressController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $this->queries->ask(new GetDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        try {
            /** @var ReadingProgress $progress */
            $progress = $this->queries->ask(new GetReadingProgress($userId, $id));
        } catch (ReaderDomainException $exception) {
            if ($exception->errorCode === ApiErrorCode::NOT_FOUND) {
                return response()->json([
                    'document_id' => $id,
                    'page_index' => 0,
                    'revision' => 0,
                    'updated_at' => null,
                ]);
            }

            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'document_id' => $progress->documentId,
            'page_index' => $progress->pageIndex,
            'revision' => $progress->revision,
            'updated_at' => $progress->updatedAt->format(DATE_ATOM),
        ]);
    }

    public function upsert(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'page_index' => ['required', 'integer', 'min:0'],
            'base_revision' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            /** @var ReadingProgress $progress */
            $progress = $this->commands->dispatch(new PutReadingProgress(
                userId: $userId,
                documentId: $id,
                pageIndex: (int) $data['page_index'],
                baseRevision: isset($data['base_revision']) ? (int) $data['base_revision'] : null,
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'document_id' => $progress->documentId,
            'page_index' => $progress->pageIndex,
            'revision' => $progress->revision,
            'updated_at' => $progress->updatedAt->format(DATE_ATOM),
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
