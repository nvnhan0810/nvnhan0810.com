<?php

namespace Modules\Sso\Domain\Exceptions;

use RuntimeException;

final class UnknownSsoClientException extends RuntimeException
{
    public function __construct(string $clientId)
    {
        parent::__construct('Unknown SSO client: '.$clientId);
    }
}
