<?php

namespace App\Domains\ReadingDigest\Domain\Services;

class ArticleLanguageService
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $allowed = config('reading-digest.allowed_languages', ['en', 'vi']);

        return is_array($allowed) ? array_values($allowed) : ['en', 'vi'];
    }

    public static function normalize(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $code = strtolower(trim($code));
        $primary = explode('-', str_replace('_', '-', $code))[0];

        return $primary !== '' ? $primary : null;
    }

    public static function detect(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/[\x{4e00}-\x{9fff}\x{3040}-\x{30ff}\x{ac00}-\x{d7af}]/u', $text)) {
            return 'zh';
        }

        if (preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/ui', $text)) {
            return 'vi';
        }

        if (preg_match('/[\p{Latin}]/u', $text)) {
            return 'en';
        }

        return null;
    }

    public static function resolve(?string $declared, string $text): ?string
    {
        $detected = self::detect($text);
        $normalized = self::normalize($declared);

        if ($detected !== null && ! self::isAllowed($detected)) {
            return $detected;
        }

        if ($detected !== null && self::isAllowed($detected)) {
            return $detected;
        }

        if ($normalized !== null && self::isAllowed($normalized)) {
            return $normalized;
        }

        return $detected ?? $normalized;
    }

    public static function isAllowed(?string $language): bool
    {
        return $language !== null && in_array($language, self::allowed(), true);
    }
}
