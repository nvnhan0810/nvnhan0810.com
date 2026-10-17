<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Enums;

enum AnnotationFormat: string
{
    case PencilKitDrawing = 'pencilkit.pkdrawing';

    /** Portable highlight / note regions (JSON). Sync web ↔ app. */
    case ReaderMarkupV1 = 'reader.markup.v1';

    /** Portable freehand strokes (JSON). App writes; web display-only. */
    case ReaderInkV1 = 'reader.ink.v1';

    public static function tryFromHeader(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(trim($value));
    }

    public function usesJsonPayload(): bool
    {
        return match ($this) {
            self::ReaderMarkupV1, self::ReaderInkV1 => true,
            self::PencilKitDrawing => false,
        };
    }
}
