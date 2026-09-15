<?php

namespace Wallets\Shared\Application;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
