<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Enums;

enum DocumentStatus: string
{
    case Ready = 'ready';
    case Uploading = 'uploading';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
