<?php

namespace Modules\Blog\Domain\Enums;

enum SpecialTag: string
{
    case Install = 'install';

    public function label(): string
    {
        return match ($this) {
            self::Install => 'Install',
        };
    }

    /**
     * Query param: when "1", admin blog home includes Install-tagged posts.
     */
    public const INCLUDE_QUERY = 'include_install';

    /**
     * @return list<string>
     */
    public static function protectedSlugs(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isProtectedSlug(string $slug): bool
    {
        return in_array($slug, self::protectedSlugs(), true);
    }
}
