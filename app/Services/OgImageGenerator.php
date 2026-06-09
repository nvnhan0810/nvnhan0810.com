<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OgImageGenerator
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function renderResponse(string $title, string $eyebrow = 'Blog', string $footer = 'nvnhan0810.com'): Response
    {
        $binary = $this->renderBinary($title, $eyebrow, $footer);

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function renderBinary(string $title, string $eyebrow = 'Blog', string $footer = 'nvnhan0810.com'): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new \RuntimeException('Unable to create OG image.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $background = imagecolorallocate($image, 10, 10, 10);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $background);

        $accent = imagecolorallocate($image, 16, 185, 129);
        imagefilledrectangle($image, 0, 0, 8, self::HEIGHT, $accent);

        $grid = imagecolorallocatealpha($image, 255, 255, 255, 115);
        for ($x = 0; $x < self::WIDTH; $x += 48) {
            imageline($image, $x, 0, $x, self::HEIGHT, $grid);
        }
        for ($y = 0; $y < self::HEIGHT; $y += 48) {
            imageline($image, 0, $y, self::WIDTH, $y, $grid);
        }

        $overlay = imagecolorallocatealpha($image, 0, 0, 0, 40);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $overlay);

        $fontBold = $this->resolveFontPath(bold: true);
        $fontRegular = $this->resolveFontPath(bold: false);

        $white = imagecolorallocate($image, 245, 245, 245);
        $muted = imagecolorallocate($image, 163, 163, 163);
        $brand = imagecolorallocate($image, 52, 211, 153);

        imagettftext($image, 28, 0, 72, 120, $brand, $fontBold, Str::upper($eyebrow));

        $lines = $this->wrapText($title, 56, $fontBold, 1040);
        $y = 200;
        foreach ($lines as $line) {
            imagettftext($image, 56, 0, 72, $y, $white, $fontBold, $line);
            $y += 78;
        }

        imagettftext($image, 26, 0, 72, self::HEIGHT - 72, $muted, $fontRegular, $footer);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }

    public function renderPortfolioBinary(
        string $name,
        string $title,
        string $tagline,
        string $footer = 'nvnhan0810.com',
    ): string {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new \RuntimeException('Unable to create OG image.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $background = imagecolorallocate($image, 10, 10, 10);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $background);

        $accent = imagecolorallocate($image, 16, 185, 129);
        imagefilledrectangle($image, 0, 0, 8, self::HEIGHT, $accent);

        $grid = imagecolorallocatealpha($image, 255, 255, 255, 115);
        for ($x = 0; $x < self::WIDTH; $x += 48) {
            imageline($image, $x, 0, $x, self::HEIGHT, $grid);
        }
        for ($y = 0; $y < self::HEIGHT; $y += 48) {
            imageline($image, 0, $y, self::WIDTH, $y, $grid);
        }

        $overlay = imagecolorallocatealpha($image, 0, 0, 0, 40);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $overlay);

        $fontBold = $this->resolveFontPath(bold: true);
        $fontRegular = $this->resolveFontPath(bold: false);

        $white = imagecolorallocate($image, 245, 245, 245);
        $muted = imagecolorallocate($image, 163, 163, 163);
        $brand = imagecolorallocate($image, 52, 211, 153);

        $nameLines = $this->wrapText($name, 64, $fontBold, 1040);
        $y = 220;
        foreach ($nameLines as $line) {
            imagettftext($image, 64, 0, 72, $y, $white, $fontBold, $line);
            $y += 86;
        }

        imagettftext($image, 36, 0, 72, $y + 24, $brand, $fontBold, $title);
        imagettftext($image, 28, 0, 72, $y + 84, $muted, $fontRegular, $tagline);
        imagettftext($image, 26, 0, 72, self::HEIGHT - 72, $muted, $fontRegular, $footer);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }

    public function cachePath(string $slug, string $locale): string
    {
        $safeSlug = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $slug) ?: 'post';

        return storage_path("app/og-cache/{$safeSlug}-{$locale}.png");
    }

    /** @return list<string> */
    private function wrapText(string $text, int $fontSize, string $fontPath, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
            $width = abs($box[2] - $box[0]);

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 4);
    }

    private function resolveFontPath(bool $bold): string
    {
        $candidates = $bold
            ? [
                resource_path('fonts/DejaVuSans-Bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ]
            : [
                resource_path('fonts/DejaVuSans.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_string($path) && file_exists($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('OG image font not found. Install DejaVu fonts or add files to resources/fonts/.');
    }
}
