<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Reader\Application\Command\PutPageAnnotation;
use Modules\Reader\Application\DTOs\StoredObjectResult;
use Modules\Reader\Application\Query\GetPageAnnotation;
use Modules\Reader\Application\Query\ListAnnotations;
use Modules\Reader\Domain\Entities\PageAnnotation;
use Modules\Reader\Domain\Enums\AnnotationFormat;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AnnotationController extends Controller
{
    public function index(Request $request, string $id, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            $payload = $queryBus->ask(new ListAnnotations($userId, $id));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json($payload);
    }

    public function show(Request $request, string $id, int $pageIndex, QueryBus $queryBus): JsonResponse|Response
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $format = $this->resolveFormat($request);

        try {
            /** @var StoredObjectResult $result */
            $result = $queryBus->ask(new GetPageAnnotation($userId, $id, $pageIndex, $format));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response($result->binary, 200, [
            'Content-Type' => $result->contentType,
            'ETag' => '"'.(string) $result->revision.'"',
            'X-Content-SHA256' => (string) $result->contentSha256,
            'X-Annotation-Format' => $format->value,
        ]);
    }

    public function upsert(Request $request, string $id, int $pageIndex, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $format = $this->resolveFormat($request);
        $emptyHeader = $request->header('X-Empty') === '1';
        $binary = $request->getContent();
        $ifMatch = $request->header('If-Match');
        $baseRevision = null;
        if (is_string($ifMatch) && $ifMatch !== '') {
            $baseRevision = (int) trim($ifMatch, '"');
        }

        $sha = $request->header('X-Content-SHA256');

        try {
            /** @var PageAnnotation $annotation */
            $annotation = $commandBus->dispatch(new PutPageAnnotation(
                userId: $userId,
                documentId: $id,
                pageIndex: $pageIndex,
                format: $format,
                empty: $emptyHeader || $binary === '',
                binary: $binary === '' ? null : $binary,
                baseRevision: $baseRevision,
                contentSha256: is_string($sha) && $sha !== '' ? $sha : null,
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
        ]);
    }

    private function resolveFormat(Request $request): AnnotationFormat
    {
        $header = $request->header('X-Annotation-Format');
        $query = $request->query('format');
        $raw = is_string($header) && $header !== ''
            ? $header
            : (is_string($query) ? $query : null);

        $format = AnnotationFormat::tryFromHeader($raw);
        if ($format !== null) {
            return $format;
        }

        // Backward compatible default for Apple PencilKit clients.
        return AnnotationFormat::PencilKitDrawing;
    }

    private function userId(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : null;
    }
}
