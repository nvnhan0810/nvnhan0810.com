<?php

namespace Modules\Blog\Domain\Enums;

enum PostStatus: string
{
    case Public = 'public';
    case Private = 'private';
    case Draft = 'draft';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Công khai',
            self::Private => 'Riêng tư',
            self::Draft => 'Nháp',
        };
    }
}
