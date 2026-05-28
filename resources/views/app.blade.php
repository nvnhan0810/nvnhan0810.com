<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow" />
    <meta name="author" content="Nguyen Van Nhan" />
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
  <body class="dark">
    @inertia
  </body>
</html>
