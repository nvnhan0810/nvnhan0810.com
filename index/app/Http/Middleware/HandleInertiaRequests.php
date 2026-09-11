<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->is('admin', 'admin/*')) {
            config(['inertia.ssr.enabled' => false]);
        }

        return [
            ...parent::share($request),
            'auth' => Auth::user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatar' => $request->user()->avatar,
            ] : null,
            'locale' => App::getLocale(),
            'seo' => [
                'siteName' => config('seo.site_name'),
                'siteUrl' => rtrim(config('app.url'), '/'),
                'defaultOgImage' => url(config('seo.default_og_image')),
                'twitterSite' => config('seo.twitter_site'),
            ],
            'postAgent' => fn () => $request->user() && $request->is('admin', 'admin/*')
                ? ['configured' => filled(config('post-agent.cursor_api_key'))]
                : null,
        ];
    }
}
