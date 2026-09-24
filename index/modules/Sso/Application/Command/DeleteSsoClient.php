<?php

namespace Modules\Sso\Application\Command;

use Modules\Shared\Application\Command;

final class DeleteSsoClient implements Command
{
    public function __construct(
        public readonly string $id,
    ) {}
}
