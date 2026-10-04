<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Responses;

use Illuminate\Http\JsonResponse;
use Modules\Reader\Domain\Enums\ApiErrorCode;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;

final class ApiErrorResponse
{
    /** @param  array<string, mixed>  $details */
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }

    public static function fromDomain(ReaderDomainException $exception): JsonResponse
    {
        return self::make(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->httpStatus,
            $exception->details,
        );
    }

    public static function unauthenticated(): JsonResponse
    {
        return self::make(ApiErrorCode::UNAUTHENTICATED, 'Unauthenticated', 401);
    }
}
