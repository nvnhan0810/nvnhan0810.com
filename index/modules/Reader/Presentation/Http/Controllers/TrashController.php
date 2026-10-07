<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\Command\EmptyTrash;
use Modules\Reader\Application\Command\PurgeDocument;
use Modules\Reader\Application\Command\RestoreDocument;
use Modules\Reader\Application\DTOs\DocumentPresenter;
use Modules\Reader\Application\Query\ListTrash;
use Modules\Reader\Domain\Entities\Document;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class TrashController extends Controller
{
    public function index(Request $request, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $cursor = $request->query('cursor');

        $documents = $queryBus->ask(new ListTrash(
            userId: $userId,
            limit: (int) $request->integer('limit', 50),
            cursor: is_string($cursor) ? $cursor : null,
        ));

        return response()->json([
            'data' => array_map(
                static fn (Document $document): array => DocumentPresenter::toArray($document),
                $documents,
            ),
            'retention_days' => (int) config('reader.trash_retention_days', 60),
        ]);
    }

    public function restore(Request $request, string $id, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var Document $document */
            $document = $commandBus->dispatch(new RestoreDocument($userId, $id));
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
            $commandBus->dispatch(new PurgeDocument($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['purged' => true, 'document_id' => $id]);
    }

    public function empty(Request $request, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        /** @var array{purged: int} $result */
        $result = $commandBus->dispatch(new EmptyTrash($userId));

        return response()->json($result);
    }

    private function userId(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : null;
    }
}
