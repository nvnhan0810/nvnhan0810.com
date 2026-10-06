<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Http;

use Illuminate\Support\Facades\Http;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\RemotePdfFetcher;
use Modules\Reader\Domain\ValueObjects\FetchedPdf;

final class HttpRemotePdfFetcher implements RemotePdfFetcher
{
    private const TIMEOUT_SECONDS = 120;

    public function fetch(string $url): FetchedPdf
    {
        $trimmed = trim($url);
        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw ReaderDomainException::validation('Invalid URL');
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ReaderDomainException::validation('URL must be http or https');
        }

        $maxBytes = (int) config('reader.max_pdf_bytes');

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Accept' => 'application/pdf,*/*'])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($trimmed);
        } catch (\Throwable $exception) {
            throw ReaderDomainException::validation('Unable to download PDF from URL', [
                'reason' => $exception->getMessage(),
            ]);
        }

        if (! $response->successful()) {
            throw ReaderDomainException::validation('Unable to download PDF from URL', [
                'http_status' => $response->status(),
            ]);
        }

        $contents = $response->body();
        $byteSize = strlen($contents);

        if ($byteSize === 0) {
            throw ReaderDomainException::validation('Downloaded file is empty');
        }

        if ($byteSize > $maxBytes) {
            throw ReaderDomainException::payloadTooLarge('PDF exceeds size limit', [
                'max_bytes' => $maxBytes,
            ]);
        }

        if (! str_starts_with($contents, '%PDF')) {
            throw ReaderDomainException::validation('URL did not return a PDF');
        }

        return new FetchedPdf(
            contents: $contents,
            byteSize: $byteSize,
            suggestedTitle: $this->suggestedTitle($trimmed, $response->header('Content-Disposition')),
        );
    }

    private function suggestedTitle(string $url, ?string $contentDisposition): ?string
    {
        if ($contentDisposition !== null
            && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $contentDisposition, $matches) === 1
        ) {
            $name = rawurldecode(trim($matches[1]));
            $name = pathinfo($name, PATHINFO_FILENAME);

            return $name !== '' ? $name : null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $base = pathinfo($path, PATHINFO_FILENAME);

        return $base !== '' && $base !== '/' ? $base : null;
    }
}
