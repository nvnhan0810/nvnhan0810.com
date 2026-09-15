<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessages from '@/Components/FlashMessages.vue';
import NavIcon from '@/Components/NavIcon.vue';
import { greetingLabel, toggleTheme } from '@/domain';
import type { SharedPageProps } from '@/types/inertia';

withDefaults(
    defineProps<{
        title?: string;
        wide?: boolean;
    }>(),
    { wide: true },
);

const page = usePage<SharedPageProps>();
const user = computed(() => page.props.auth?.user);
const initial = computed(() => (user.value?.name || 'U').charAt(0).toUpperCase());

function isActive(...patterns: string[]): boolean {
    const url = page.url.split('?')[0] ?? '';
    return patterns.some((p) => {
        if (p.endsWith('*')) {
            const base = p.slice(0, -1);
            return url === base.slice(0, -1) || url.startsWith(base);
        }
        return url === p || url.startsWith(`${p}/`);
    });
}

function logout(): void {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen flex flex-col min-h-[100dvh]">
        <Head v-if="title" :title="title" />

        <!-- Mobile top bar -->
        <header class="md:hidden sticky top-0 z-40 bg-app/95 backdrop-blur border-b border-default">
            <div class="flex items-center justify-between h-14 px-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-sm">
                        {{ initial }}
                    </div>
                    <div>
                        <p class="text-[10px] text-content-muted uppercase tracking-wider font-semibold">{{ greetingLabel() }}</p>
                        <p class="text-sm font-bold text-content leading-tight">{{ user?.name || 'Bạn' }}</p>
                    </div>
                </div>
                <button type="button" class="p-2 text-content-muted hover:bg-surface-hover rounded-full" @click="toggleTheme">
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>
            </div>
        </header>

        <!-- Desktop nav -->
        <nav class="hidden md:block bg-surface border-b border-default sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center gap-6">
                        <Link :href="route('dashboard')" class="text-2xl font-bold text-primary-600 dark:text-primary-400 tracking-tight">MyWallet</Link>
                        <div class="flex items-center gap-1 text-sm font-medium">
                            <Link :href="route('dashboard')" class="px-3 py-2 rounded-md" :class="isActive('/') ? 'bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300' : 'text-content-secondary hover:text-primary-600'">Tổng quan</Link>
                            <Link :href="route('transactions.index')" class="px-3 py-2 rounded-md" :class="isActive('/transactions') ? 'bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300' : 'text-content-secondary hover:text-primary-600'">Giao dịch</Link>
                            <Link :href="route('wallets.index')" class="px-3 py-2 rounded-md" :class="isActive('/wallets') ? 'bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300' : 'text-content-secondary hover:text-primary-600'">Ví</Link>
                            <Link :href="route('loans.index')" class="px-3 py-2 rounded-md" :class="isActive('/loans') || isActive('/recurring-items') || isActive('/fixed-expenses') ? 'bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300' : 'text-content-secondary hover:text-primary-600'">Kế hoạch</Link>
                            <Link :href="route('settings.index')" class="px-3 py-2 rounded-md" :class="isActive('/settings') || isActive('/holidays') || isActive('/transaction-templates') ? 'bg-primary-50 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300' : 'text-content-secondary hover:text-primary-600'">Cá nhân</Link>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="p-2 text-content-muted hover:bg-gray-100 dark:hover:bg-slate-700 rounded-full mr-2" @click="toggleTheme">
                            <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        </button>
                        <Link :href="route('transactions.create')" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/50">+ Giao dịch</Link>
                        <Link :href="route('loans.create')" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 dark:bg-primary-500">+ Khoản vay</Link>
                        <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-content-muted hover:bg-gray-100 dark:hover:bg-slate-700" @click="logout">Đăng xuất</button>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-1 pt-6 md:pt-10 pb-6 md:pb-10 main-with-mobile-nav">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <FlashMessages />
                <slot />
            </div>
        </main>

        <footer class="hidden md:block bg-surface border-t border-default mt-auto">
            <div class="max-w-7xl mx-auto py-6 px-4 text-center text-sm text-content-muted">&copy; {{ new Date().getFullYear() }} MyWallet.</div>
        </footer>

        <!-- Mobile bottom nav -->
        <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 bg-surface border-t border-default shadow-[0_-4px_20px_rgba(0,0,0,0.06)] dark:shadow-[0_-4px_20px_rgba(0,0,0,0.2)] safe-bottom" aria-label="Điều hướng chính">
            <div class="grid grid-cols-5 h-[4.5rem] max-w-lg mx-auto relative">
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]" :class="isActive('/') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted'">
                    <NavIcon name="home" />
                    <span>Tổng quan</span>
                </Link>
                <Link :href="route('transactions.index')" class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]" :class="isActive('/transactions') && !isActive('/transactions/create') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted'">
                    <NavIcon name="swap" />
                    <span>Lịch sử</span>
                </Link>
                <div class="flex justify-center items-start -mt-5">
                    <Link :href="route('transactions.create')" class="w-14 h-14 rounded-full bg-primary-600 dark:bg-primary-500 shadow-lg shadow-primary-500/40 flex items-center justify-center text-white">
                        <NavIcon name="plus" />
                    </Link>
                </div>
                <Link :href="route('loans.index')" class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]" :class="isActive('/loans') || isActive('/recurring-items') || isActive('/fixed-expenses') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted'">
                    <NavIcon name="planning" />
                    <span>Kế hoạch</span>
                </Link>
                <Link :href="route('settings.index')" class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium min-h-[44px]" :class="isActive('/settings') || isActive('/wallets') || isActive('/holidays') || isActive('/transaction-templates') ? 'text-primary-700 dark:text-primary-400' : 'text-content-muted'">
                    <NavIcon name="profile" />
                    <span>Cá nhân</span>
                </Link>
            </div>
        </nav>
    </div>
</template>

<style>
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
@media (max-width: 767px) {
    .main-with-mobile-nav {
        padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px)) !important;
    }
}
</style>
