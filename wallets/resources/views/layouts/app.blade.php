<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MyWallet">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', 'MyWallet')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .flatpickr-calendar { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); border-radius: 0.5rem; border: 1px solid rgb(229 231 235); }
        .flatpickr-day.selected { background: rgb(79 70 229); border-color: rgb(79 70 229); }
        .flatpickr-day.today { border-color: rgb(79 70 229); }
        .flatpickr-day:hover { background: rgb(224 231 255); }
        .dark .flatpickr-calendar { background: #1f2937; border-color: #374151; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); }
        .dark .flatpickr-day { color: #f3f4f6; }
        .dark .flatpickr-day.selected { background: #6366f1; border-color: #6366f1; color: #fff; }
        .dark .flatpickr-day.today { border-color: #6366f1; }
        .dark .flatpickr-day:hover { background: #374151; }
        .dark .flatpickr-month, .dark .flatpickr-current-month, .dark .flatpickr-weekday { color: #f3f4f6; fill: #f3f4f6; }
        .dark span.flatpickr-weekday { color: #9ca3af; }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
        /* pb riêng — tránh bị py-6 của Tailwind ghi đè */
        @media (max-width: 767px) {
            .main-with-mobile-nav {
                padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px)) !important;
            }
        }
    </style>
</head>
<body class="bg-app text-content font-sans transition-colors duration-200">
    <div class="min-h-screen flex flex-col min-h-[100dvh]">

        {{-- Mobile top bar --}}
        <header class="md:hidden sticky top-0 z-40 bg-app/95 backdrop-blur border-b border-default">
            <div class="flex items-center justify-between h-14 px-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-sm">
                        N
                    </div>
                    <div>
                        <p class="text-[10px] text-content-muted uppercase tracking-wider font-semibold">Chào buổi sáng</p>
                        <p class="text-sm font-bold text-content leading-tight">Nhân</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="toggleTheme()" class="p-2 text-content-muted hover:bg-surface-hover rounded-full">
                        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    </button>
                </div>
            </div>
        </header>

        {{-- Desktop nav --}}
        <nav class="hidden md:block bg-surface border-b border-default sticky top-0 z-50 transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center gap-6">
                        <a href="{{ route('dashboard') }}" class="text-2xl font-bold text-primary-600 dark:text-primary-400 tracking-tight">MyWallet</a>
                        <div class="flex items-center gap-1 text-sm font-medium">
                            @include('partials.nav-links')
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="toggleTheme()" class="p-2 text-content-muted hover:bg-gray-100 dark:hover:bg-slate-700 rounded-full mr-2">
                            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        </button>
                        <a href="{{ route('transactions.create') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/50 hover:bg-primary-100 dark:hover:bg-primary-900">+ Giao dịch</a>
                        <a href="{{ route('loans.create') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 dark:bg-primary-500 hover:bg-primary-700 dark:hover:bg-primary-600 dark:hover:bg-primary-600">+ Khoản vay</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-content-muted hover:bg-gray-100 dark:hover:bg-slate-700">Đăng xuất</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-1 pt-6 md:pt-10 pb-6 md:pb-10 main-with-mobile-nav">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>

        <footer class="hidden md:block bg-surface border-t border-default mt-auto transition-colors duration-200">
            <div class="max-w-7xl mx-auto py-6 px-4 text-center text-sm text-content-muted">&copy; {{ date('Y') }} MyWallet.</div>
        </footer>

        {{-- Mobile bottom navigation --}}
        <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 bg-surface border-t border-default shadow-[0_-4px_20px_rgba(0,0,0,0.06)] dark:shadow-[0_-4px_20px_rgba(0,0,0,0.2)] safe-bottom transition-colors duration-200" aria-label="Điều hướng chính">
            <div class="grid grid-cols-5 h-[4.5rem] max-w-lg mx-auto relative">
                <a href="{{ route('dashboard') }}"
                   class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]
                   {{ request()->routeIs('dashboard') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted' }}">
                    @include('partials.nav-icon', ['name' => 'home', 'active' => request()->routeIs('dashboard')])
                    <span>Tổng quan</span>
                </a>
                <a href="{{ route('transactions.index') }}"
                   class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]
                   {{ request()->routeIs('transactions.*') && !request()->routeIs('transactions.create') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted' }}">
                    @include('partials.nav-icon', ['name' => 'swap', 'active' => request()->routeIs('transactions.*') && !request()->routeIs('transactions.create')])
                    <span>Lịch sử</span>
                </a>
                
                <div class="flex justify-center items-start -mt-5">
                    <a href="{{ route('transactions.create') }}" class="w-14 h-14 rounded-full bg-primary-600 dark:bg-primary-500 shadow-lg shadow-primary-500/40 flex items-center justify-center text-white hover:scale-105 active:scale-95 transition-transform">
                        @include('partials.nav-icon', ['name' => 'plus'])
                    </a>
                </div>

                <a href="{{ route('loans.index') }}"
                   class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]
                   {{ request()->routeIs('loans.*', 'recurring-items.*') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted' }}">
                    @include('partials.nav-icon', ['name' => 'planning', 'active' => request()->routeIs('loans.*', 'recurring-items.*')])
                    <span>Kế hoạch</span>
                </a>
                <a href="{{ route('settings.index') }}"
                   class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]
                   {{ request()->routeIs('settings.*', 'transaction-templates.*', 'holidays.*', 'wallets.*') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted' }}">
                    @include('partials.nav-icon', ['name' => 'profile', 'active' => request()->routeIs('settings.*', 'transaction-templates.*', 'holidays.*', 'wallets.*')])
                    <span>Cá nhân</span>
                </a>
            </div>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
        }

        window.MoneyInput = {
            parse(value) {
                const digits = String(value ?? '').replace(/\D/g, '');
                return digits ? parseInt(digits, 10) : 0;
            },
            format(value) {
                const n = this.parse(value);
                if (!n) return '';
                return new Intl.NumberFormat('vi-VN').format(n);
            },
            onInput(el, callback) {
                const raw = this.parse(el.value);
                el.value = raw ? this.format(raw) : '';
                callback(raw);
            },
            initElement(el) {
                if (el.__moneyAlpine || el.dataset.moneyInit) return;
                el.dataset.moneyInit = '1';
                el.type = 'text';
                el.setAttribute('inputmode', 'numeric');
                if (el.value) el.value = this.format(el.value);
                el.addEventListener('input', () => {
                    const raw = this.parse(el.value);
                    el.value = raw ? this.format(raw) : '';
                });
            },
            initAll() {
                document.querySelectorAll('input.money-input').forEach(el => this.initElement(el));
            },
        };

        document.addEventListener('alpine:init', () => {
            Alpine.magic('money', () => ({
                format(n) {
                    const num = Number(n) || 0;
                    if (!num) return '';
                    return new Intl.NumberFormat('vi-VN').format(num);
                },
                onInput(el, callback) {
                    window.MoneyInput.onInput(el, callback);
                },
            }));
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            form.querySelectorAll('input.money-input').forEach((el) => {
                if (!String(el.value).trim()) {
                    el.value = '';
                    return;
                }
                el.value = String(window.MoneyInput.parse(el.value));
            });
        }, true);

        function initDatepickers() {
            document.querySelectorAll('.datepicker:not(.flatpickr-input)').forEach(function(el) {
                if (el._flatpickr) return;
                flatpickr(el, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    locale: {
                        firstDayOfWeek: 1,
                        weekdays: { shorthand: ['CN','T2','T3','T4','T5','T6','T7'], longhand: ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'] },
                        months: { shorthand: ['Th1','Th2','Th3','Th4','Th5','Th6','Th7','Th8','Th9','Th10','Th11','Th12'], longhand: ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'] }
                    }
                });
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            initDatepickers();
            window.MoneyInput.initAll();
        });
        document.addEventListener('click', function() {
            setTimeout(() => {
                initDatepickers();
                window.MoneyInput.initAll();
            }, 100);
        });
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function() {});
        }
    </script>
</body>
</html>
