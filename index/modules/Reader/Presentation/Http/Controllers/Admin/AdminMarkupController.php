<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reader\Application\Command\PutPageAnnotation;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetPageAnnotation;
use Modules\Reader\Application\Query\ListDocumentMarkups;
use Modules\Reader\Domain\Entities\PageAnnotation;
use Modules\Reader\Domain\Enums\AnnotationFormat;
use Modules\Reader\Domain\Enums\ApiErrorCode;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AdminMarkupController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $payload = $this->queries->ask(new ListDocumentMarkups($userId, $id));
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
                AnnotationFormat::ReaderMarkupV1,
            ));
        } catch (ReaderDomainException $exception) {
            if ($exception->errorCode === ApiErrorCode::NOT_FOUND) {
                return response()->json([
                    'page_index' => $pageIndex,
                    'revision' => 0,
                    'updated_at' => null,
                    'content_sha256' => null,
                    'markup' => [
                        'version' => 1,
                        'items' => [],
                    ],
                ]);
            }

            return ApiErrorResponse::fromDomain($exception);
        }

        try {
            /** @var array<string, mixed> $markup */
            $markup = json_decode((string) $result->binary, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ApiErrorResponse::make(
                ApiErrorCode::VALIDATION_ERROR,
                'Stored markup is not valid JSON',
                422,
            );
        }

        return response()->json([
            'page_index' => $pageIndex,
            'revision' => $result->revision ?? 0,
            'updated_at' => null,
            'content_sha256' => $result->contentSha256,
            'markup' => $markup,
        ]);
    }

    public function upsert(Request $request, string $id, int $pageIndex): JsonResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'markup' => ['required', 'array'],
            'markup.version' => ['required', 'integer', 'in:1'],
            'markup.items' => ['required', 'array'],
            'base_revision' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var array<string, mixed> $markup */
        $markup = $data['markup'];
        $binary = json_encode($markup, JSON_THROW_ON_ERROR);
        $empty = $markup['items'] === [];

        try {
            /** @var PageAnnotation $annotation */
            $annotation = $this->commands->dispatch(new PutPageAnnotation(
                userId: $userId,
                documentId: $id,
                pageIndex: $pageIndex,
                format: AnnotationFormat::ReaderMarkupV1,
                empty: $empty,
                binary: $empty ? null : $binary,
                baseRevision: isset($data['base_revision']) ? (int) $data['base_revision'] : null,
                contentSha256: $empty ? null : hash('sha256', $binary),
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'page_index' => $annotation->pageIndex,
            'revision' => $annotation->revision,
            'empty' => $annotation->isEmpty(),
            'format' => $annotation->format->value,
            'updated_at' => $annotation->updatedAt->format(DATE_ATOM),
            'content_sha256' => $annotation->contentSha256,
            'markup' => $empty
                ? ['version' => 1, 'items' => []]
                : $markup,
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
