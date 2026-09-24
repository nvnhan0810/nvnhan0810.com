<?php

namespace Modules\Sso\Domain\Exceptions;

use DomainException;

final class DuplicateSsoClientIdException extends DomainException
{
    public function __construct(public readonly string $clientId)
    {
        parent::__construct(sprintf('SSO client_id [%s] already exists.', $clientId));
    }
}
