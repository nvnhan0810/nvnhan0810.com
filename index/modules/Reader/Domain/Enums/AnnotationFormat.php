<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Enums;

enum AnnotationFormat: string
{
    case PencilKitDrawing = 'pencilkit.pkdrawing';

    /** Portable highlight / note regions (JSON). Sync web ↔ app. */
    case ReaderMarkupV1 = 'reader.markup.v1';

    public static function tryFromHeader(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(trim($value));
    }
}
