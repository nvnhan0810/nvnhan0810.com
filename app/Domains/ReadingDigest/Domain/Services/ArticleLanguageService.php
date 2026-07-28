<?php

namespace App\Domains\ReadingDigest\Domain\Services;

class ArticleLanguageService
{
    /**
     * Vietnamese-only letters / tone stacks not shared with PT/ES/FR.
     * Generic accents (á, é, ã, ç, …) alone must NOT imply Vietnamese —
     * Portuguese "trás" was previously mislabeled as vi and allowed through.
     */
    private const VIETNAMESE_UNIQUE = '/[ăằắẳẵặầấẩẫậềếểễệồốổỗộơờớởỡợưừứửữựảạẻẽẹỉĩịỏọủũụỳỷỹỵđĐ]/u';

    /** Latin diacritics common in Romance/Germanic languages (not English ASCII). */
    private const NON_ENGLISH_LATIN_DIACRITICS = '/[àáâãäåæçèéêëìíîïñòóôõöùúûüýÿœ]/iu';

    /**
     * Strong Portuguese / Spanish function-word signals (ASCII-friendly titles).
     */
    private const IBERIAN_WORD_PATTERN = '/\b(?:o\s+que|pra|não|voce|você|também|tambem|estão|são|introdução|introducao|código|codigo|função|funcao|através|atraves|também|también|españa|español|qué\s+es|construindo|plataforma)\b/iu';

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

        if (preg_match(self::VIETNAMESE_UNIQUE, $text)) {
            return 'vi';
        }

        if (preg_match(self::IBERIAN_WORD_PATTERN, $text)) {
            return 'pt';
        }

        // Dense non-English diacritics (PT/ES/FR body text), not a lone "El Niño".
        if (self::hasDenseNonEnglishDiacritics($text)) {
            return 'und';
        }

        if (preg_match('/[\p{Latin}]/u', $text)) {
            return 'en';
        }

        return null;
    }

    private static function hasDenseNonEnglishDiacritics(string $text): bool
    {
        $letters = preg_match_all('/\p{L}/u', $text);
        if ($letters < 40) {
            // Short titles: any Romance diacritic without Vietnamese-unique → reject.
            return (bool) preg_match(self::NON_ENGLISH_LATIN_DIACRITICS, $text);
        }

        $diacritics = preg_match_all(self::NON_ENGLISH_LATIN_DIACRITICS, $text);

        return $diacritics >= 3 && ($diacritics / $letters) >= 0.015;
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
