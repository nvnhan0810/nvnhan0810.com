<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\Query\GetSyncChanges;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use App\Models\User;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\QueryBus;

final class SyncController extends Controller
{
    public function changes(Request $request, QueryBus $queryBus): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiErrorResponse::unauthenticated();
        }

        $since = $request->query('since');
        $sinceString = is_string($since) ? $since : null;

        try {
            $payload = $queryBus->ask(new GetSyncChanges((string) $user->id, $sinceString));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json($payload);
    }
}
