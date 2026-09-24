<?php

namespace Modules\Sso\Domain\Exceptions;

use DomainException;

final class SsoClientNotFoundException extends DomainException
{
    public function __construct(public readonly string $id)
    {
        parent::__construct(sprintf('SSO client [%s] not found.', $id));
    }
}
