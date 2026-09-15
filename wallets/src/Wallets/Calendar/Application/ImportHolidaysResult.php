<?php

namespace Wallets\Calendar\Application;

final class ImportHolidaysResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        public readonly array $errors = [],
    ) {}
}
