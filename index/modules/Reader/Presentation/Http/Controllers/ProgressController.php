<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\Command\PutReadingProgress;
use Modules\Reader\Application\Query\GetReadingProgress;
use Modules\Reader\Domain\Entities\ReadingProgress;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use App\Models\User;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class ProgressController extends Controller
{
    public function show(Request $request, string $id, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var ReadingProgress $progress */
            $progress = $queryBus->ask(new GetReadingProgress($userId, $id));
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

    public function upsert(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $data = $request->validate([
            'page_index' => ['required', 'integer', 'min:0'],
            'base_revision' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            /** @var ReadingProgress $progress */
            $progress = $commandBus->dispatch(new PutReadingProgress(
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

    private function userId(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : null;
    }
}
