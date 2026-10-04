<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Enums;

final class ApiErrorCode
{
    public const UNAUTHENTICATED = 'unauthenticated';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const CONFLICT = 'conflict';
    public const PAYLOAD_TOO_LARGE = 'payload_too_large';
    public const VALIDATION_ERROR = 'validation_error';
    public const RATE_LIMITED = 'rate_limited';
    public const INVALID_GRANT = 'invalid_grant';
}
