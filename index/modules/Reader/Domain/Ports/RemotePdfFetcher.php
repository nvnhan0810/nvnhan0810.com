<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use Modules\Reader\Domain\ValueObjects\FetchedPdf;

interface RemotePdfFetcher
{
    public function fetch(string $url): FetchedPdf;
}
