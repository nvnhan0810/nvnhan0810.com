<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetPageAnnotation;
use Modules\Reader\Application\Query\ListDocumentInks;
use Modules\Reader\Domain\Enums\AnnotationFormat;
use Modules\Reader\Domain\Enums\ApiErrorCode;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\QueryBus;

/** Web read-only freehand (`reader.ink.v1`). No PUT — app writes via API. */
final class AdminInkController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $payload = $this->queries->ask(new ListDocumentInks($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json($payload);
    }

    public function show(Request $request, string $id, int $pageIndex): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            /** @var StoredObjectResult $result */
            $result = $this->queries->ask(new GetPageAnnotation(
                $userId,
                $id,
                $pageIndex,
                AnnotationFormat::ReaderInkV1,
            ));
        } catch (ReaderDomainException $exception) {
            if ($exception->errorCode === ApiErrorCode::NOT_FOUND) {
                return response()->json([
                    'page_index' => $pageIndex,
                    'revision' => 0,
                    'updated_at' => null,
                    'content_sha256' => null,
                    'ink' => [
                        'version' => 1,
                        'strokes' => [],
                    ],
                ]);
            }

            return ApiErrorResponse::fromDomain($exception);
        }

        try {
            /** @var array<string, mixed> $ink */
            $ink = json_decode((string) $result->binary, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ApiErrorResponse::make(
                ApiErrorCode::VALIDATION_ERROR,
                'Stored ink is not valid JSON',
                422,
            );
        }

        return response()->json([
            'page_index' => $pageIndex,
            'revision' => $result->revision ?? 0,
            'updated_at' => null,
            'content_sha256' => $result->contentSha256,
            'ink' => $ink,
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
