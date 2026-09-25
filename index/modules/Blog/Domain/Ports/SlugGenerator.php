<?php

namespace Modules\Blog\Domain\Ports;

interface SlugGenerator
{
    public function fromString(string $value, int $index = 0): string;

    public function uniqueForPost(string $title): string;
}
