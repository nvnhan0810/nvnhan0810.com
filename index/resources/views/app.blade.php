<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow" />
    <meta name="author" content="{{ config('seo.site_name') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="192x192" href="/images/android-chrome-192x192.png" />
    <link rel="manifest" href="/manifest.webmanifest" />
    <meta name="theme-color" content="#171a20" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="Matrix" />
    <script>
      (function () {
        try {
          var key = "vite-ui-theme";
          var stored = localStorage.getItem(key) || "dark";
          var resolved =
            stored === "system"
              ? window.matchMedia("(prefers-color-scheme: dark)").matches
                ? "dark"
                : "light"
              : stored;
          document.documentElement.classList.add(resolved);
          var meta = document.querySelector('meta[name="theme-color"]');
          if (meta) {
            meta.setAttribute(
              "content",
              resolved === "dark" ? "#171a20" : "#ffffff",
            );
          }
        } catch (e) {
          document.documentElement.classList.add("dark");
        }
      })();
    </script>
    {{-- OG/Twitter meta come from SeoHead via @inertiaHead (SSR). Do not duplicate here — crawlers use the first og:* tags. --}}
    @viteReactRefresh
    @vite('resources/sass/app.scss')
    @vite('resources/ts/App.tsx')
    @inertiaHead
    @if(config('services.google_analytics.measurement_id'))
      <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.measurement_id') }}"></script>
      <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('services.google_analytics.measurement_id') }}', { send_page_view: false });
      </script>
    @endif
  </head>
  <body>
    @inertia
  </body>
</html>
