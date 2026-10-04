<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Exceptions;

use Modules\Reader\Domain\Enums\ApiErrorCode;
use RuntimeException;

final class ReaderDomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** @param  array<string, mixed>  $details */
    public static function notFound(string $message = 'Resource not found', array $details = []): self
    {
        return new self(ApiErrorCode::NOT_FOUND, $message, 404, $details);
    }

    /** @param  array<string, mixed>  $details */
    public static function forbidden(string $message = 'Forbidden', array $details = []): self
    {
        return new self(ApiErrorCode::FORBIDDEN, $message, 403, $details);
    }

    /** @param  array<string, mixed>  $details */
    public static function conflict(string $message, array $details = []): self
    {
        return new self(ApiErrorCode::CONFLICT, $message, 409, $details);
    }

    /** @param  array<string, mixed>  $details */
    public static function payloadTooLarge(string $message, array $details = []): self
    {
        return new self(ApiErrorCode::PAYLOAD_TOO_LARGE, $message, 413, $details);
    }

    /** @param  array<string, mixed>  $details */
    public static function validation(string $message, array $details = []): self
    {
        return new self(ApiErrorCode::VALIDATION_ERROR, $message, 422, $details);
    }

    /** @param  array<string, mixed>  $details */
    public static function invalidGrant(string $message = 'Invalid authorization grant', array $details = []): self
    {
        return new self(ApiErrorCode::INVALID_GRANT, $message, 401, $details);
    }
}
