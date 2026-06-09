<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\OgImageGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class OgImageController extends Controller
{
    public function __construct(private readonly OgImageGenerator $ogImages) {}

    public function home(Request $request): Response
    {
        $locale = $request->query('locale', app()->getLocale());

        if (! in_array($locale, ['en', 'vi'], true)) {
            $locale = 'en';
        }

        $portfolio = config("seo.portfolio.{$locale}", config('seo.portfolio.en'));
        $footer = parse_url(config('app.url', 'https://nvnhan0810.com'), PHP_URL_HOST) ?: 'nvnhan0810.com';

        try {
            $binary = $this->ogImages->renderPortfolioBinary(
                $portfolio['name'],
                $portfolio['title'],
                $portfolio['tagline'],
                $footer,
            );
        } catch (\Throwable $e) {
            report($e);

            $fallback = public_path('images/og-default.png');
            if (! is_readable($fallback)) {
                throw $e;
            }

            return response()->file($fallback, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function post(Request $request, string $slug): Response
    {
        $locale = $request->query('locale', Post::DEFAULT_LOCALE);

        if (! in_array($locale, Post::SUPPORTED_LOCALES, true)) {
            $locale = Post::DEFAULT_LOCALE;
        }

        $post = Post::query()
            ->with('translations')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereDate('published_at', '<=', now())
            ->firstOrFail();

        $cachePath = $this->ogImages->cachePath($slug, $locale);
        $cacheDir = dirname($cachePath);

        if (! File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true);
        }

        $needsRegenerate = ! File::exists($cachePath)
            || File::lastModified($cachePath) < $post->updated_at->getTimestamp();

        if ($needsRegenerate) {
            $translation = $post->translations
                ->firstWhere('locale', $locale)
                ?? $post->translations->firstWhere('locale', Post::DEFAULT_LOCALE)
                ?? $post->translations->first();

            if (! $translation) {
                abort(404, 'Post translation not found');
            }

            try {
                $binary = $this->ogImages->renderBinary(
                    $translation->title,
                    'Blog',
                    config('seo.site_name', 'nvnhan0810.com')
                );
            } catch (\Throwable $e) {
                report($e);

                $fallback = public_path('images/og-default.png');
                if (! is_readable($fallback)) {
                    throw $e;
                }

                return response()->file($fallback, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }

            File::put($cachePath, $binary);
        }

        return response()->file($cachePath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
