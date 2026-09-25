<?php

namespace Modules\Blog\Application\Command;

use Modules\Shared\Application\Command;

final class DeletePost implements Command
{
    public function __construct(
        public readonly int $id,
    ) {}
}
