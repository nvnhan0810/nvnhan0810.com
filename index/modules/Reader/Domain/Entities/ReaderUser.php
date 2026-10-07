<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Entities;

/** App user (`users` table) as seen by Reader API. */
final readonly class ReaderUser
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public ?string $avatarUrl,
    ) {}
}
