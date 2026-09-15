<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <title>Đăng nhập — MyWallet</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-app text-content min-h-screen flex items-center justify-center px-4 transition-colors duration-200">
    <div class="w-full max-w-md bg-surface border border-default rounded-2xl shadow-sm p-8">
        <h1 class="text-2xl font-bold text-primary-600 dark:text-primary-400 tracking-tight">MyWallet</h1>
        <p class="mt-2 text-sm text-content-muted">Đăng nhập bằng Google để quản lý ví và khoản vay.</p>

        @if (session('error'))
            <div class="mt-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm px-3 py-2">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="mt-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm px-3 py-2">{{ session('success') }}</div>
        @endif

        <a href="{{ route('auth.sso') }}"
           class="mt-6 flex items-center justify-center gap-2 w-full rounded-lg bg-primary-600 dark:bg-primary-500 hover:bg-primary-700 dark:hover:bg-primary-600 text-white font-medium px-4 py-3">
            Đăng nhập bằng Google
        </a>

        <p class="mt-4 text-xs text-content-muted text-center">
            Email phải nằm trong allowlist mới được vào.
        </p>
    </div>
</body>
</html>
