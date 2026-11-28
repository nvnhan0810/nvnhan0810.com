<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@/application/navigation'
import {
  BOTTOM_TABS_END,
  BOTTOM_TABS_START,
  NAV_SECTIONS,
  isItemActive,
  isMenuActive,
  isRootScreen,
  parentPath,
  sectionFor,
} from '@/application/app-navigation'
import DebtGoalProgressCard from '@/Components/DebtGoalProgressCard.vue'
import FlashMessages from '@/Components/FlashMessages.vue'
import BankHandoffPrompt from '@/Components/BankHandoffPrompt.vue'
import NavIcon from '@/Components/NavIcon.vue'
import InstallAppPrompt from '@/Components/InstallAppPrompt.vue'
import { greetingLabel, toggleTheme } from '@/domain'
import type { SharedPageProps } from '@/types/inertia'

const props = withDefaults(
  defineProps<{
    title?: string
    wide?: boolean
  }>(),
  { wide: true },
)

const page = usePage<SharedPageProps>()
const user = computed(() => page.props.auth?.user)
const userName = computed(() => user.value?.name || 'Bạn')
const initial = computed(() => userName.value.charAt(0).toUpperCase())
const url = computed(() => page.url)
const isRoot = computed(() => isRootScreen(url.value))
const section = computed(() => sectionFor(url.value))
/** Sibling tabs on mobile for the Planning / Personal sections. */
const sectionTabs = computed(() =>
  isRoot.value && section.value && section.value.id !== 'overview'
    ? section.value.items
    : [],
)
const menuOpen = ref(false)

function goBack(): void {
  const state = window.history.state as { back?: string | null } | null
  if (state?.back) window.history.back()
  else router.visit(parentPath(url.value))
}

function logout(): void {
  menuOpen.value = false
  router.post(route('logout'))
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') menuOpen.value = false
}

watch(url, () => (menuOpen.value = false))
watch(menuOpen, (open) => {
  document.documentElement.classList.toggle('overflow-hidden', open)
  if (open) window.addEventListener('keydown', onKeydown)
  else window.removeEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  document.documentElement.classList.remove('overflow-hidden')
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="app-shell min-h-[100dvh] lg:pl-64">
    <Head v-if="props.title" :title="props.title" />

    <!-- Desktop / laptop sidebar -->
    <aside
      class="hidden lg:flex fixed inset-y-0 left-0 z-40 w-64 flex-col bg-surface border-r border-default"
      aria-label="Điều hướng chính"
    >
      <Link
        :href="route('dashboard')"
        class="flex items-center gap-2.5 h-16 px-5 shrink-0"
      >
        <img src="/icons/icon.svg" alt="" class="w-8 h-8 rounded-lg" />
        <span class="text-lg font-bold tracking-tight text-content"
          >MyWallet</span
        >
      </Link>

      <div class="px-4 pb-2 flex gap-2">
        <Link
          :href="route('transactions.create')"
          class="btn btn-primary flex-1"
        >
          <NavIcon name="plus" size="w-4 h-4" />
          Giao dịch
        </Link>
        <Link
          :href="route('loans.create')"
          class="btn btn-secondary px-3"
          title="Tạo khoản vay"
          aria-label="Tạo khoản vay"
        >
          <NavIcon name="loan" size="w-4 h-4" />
        </Link>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-5">
        <div v-for="group in NAV_SECTIONS" :key="group.id">
          <p
            class="px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-content-muted"
          >
            {{ group.label }}
          </p>
          <ul class="space-y-0.5">
            <li v-for="item in group.items" :key="item.route">
              <Link
                :href="route(item.route)"
                class="nav-link"
                :class="{ 'nav-link-active': isItemActive(url, item) }"
                :aria-current="isItemActive(url, item) ? 'page' : undefined"
              >
                <NavIcon :name="item.icon" />
                <span class="truncate">{{ item.label }}</span>
              </Link>
            </li>
          </ul>
        </div>
      </nav>

      <div class="p-3 border-t border-default shrink-0">
        <div class="flex items-center gap-3 rounded-xl p-2">
          <div class="avatar w-9 h-9 text-sm">{{ initial }}</div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-content truncate">
              {{ userName }}
            </p>
            <p class="text-xs text-content-muted truncate">
              {{ greetingLabel() }}
            </p>
          </div>
          <button
            type="button"
            class="icon-btn"
            title="Đổi giao diện sáng/tối"
            aria-label="Đổi giao diện sáng/tối"
            @click="toggleTheme"
          >
            <NavIcon name="sun" class="hidden dark:block" />
            <NavIcon name="moon" class="dark:hidden" />
          </button>
          <button
            type="button"
            class="icon-btn"
            title="Đăng xuất"
            aria-label="Đăng xuất"
            @click="logout"
          >
            <NavIcon name="logout" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Mobile / tablet top bar -->
    <header
      class="lg:hidden sticky top-0 z-40 bg-app/90 backdrop-blur-md border-b border-default/70 pt-[env(safe-area-inset-top)]"
    >
      <div class="flex items-center gap-2 h-14 px-safe">
        <template v-if="isRoot">
          <div class="avatar w-9 h-9 text-sm">{{ initial }}</div>
          <div class="min-w-0 flex-1">
            <p
              class="text-[10px] text-content-muted uppercase tracking-wider font-semibold"
            >
              {{ greetingLabel() }}
            </p>
            <p class="text-sm font-bold text-content leading-tight truncate">
              {{ userName }}
            </p>
          </div>
        </template>
        <template v-else>
          <button
            type="button"
            class="icon-btn -ml-2"
            aria-label="Quay lại"
            @click="goBack"
          >
            <NavIcon name="back" size="w-6 h-6" />
          </button>
          <h1
            class="min-w-0 flex-1 text-base font-semibold text-content truncate"
          >
            {{ props.title || 'MyWallet' }}
          </h1>
        </template>
        <button
          type="button"
          class="icon-btn"
          aria-label="Đổi giao diện sáng/tối"
          @click="toggleTheme"
        >
          <NavIcon name="sun" class="hidden dark:block" />
          <NavIcon name="moon" class="dark:hidden" />
        </button>
      </div>

      <nav
        v-if="sectionTabs.length"
        class="flex gap-2 overflow-x-auto hide-scrollbar px-safe pb-2.5 -mt-0.5"
        :aria-label="section?.label"
      >
        <Link
          v-for="item in sectionTabs"
          :key="item.route"
          :href="route(item.route)"
          class="chip"
          :class="{ 'chip-active': isItemActive(url, item) }"
          :aria-current="isItemActive(url, item) ? 'page' : undefined"
        >
          {{ item.short || item.label }}
        </Link>
      </nav>
    </header>

    <main class="main-content pt-5 lg:pt-8 pb-8">
      <div class="max-w-7xl mx-auto px-safe lg:px-8">
        <InstallAppPrompt />
        <BankHandoffPrompt />
        <FlashMessages />
        <DebtGoalProgressCard />
        <slot />
      </div>
    </main>

    <!-- Mobile / tablet bottom bar -->
    <nav
      class="lg:hidden fixed bottom-0 inset-x-0 z-50 bg-surface/95 backdrop-blur-md border-t border-default pb-[env(safe-area-inset-bottom)]"
      aria-label="Điều hướng chính"
    >
      <div class="grid grid-cols-5 h-16 max-w-lg mx-auto">
        <Link
          v-for="tab in BOTTOM_TABS_START"
          :key="tab.route"
          :href="route(tab.route)"
          class="tab-link"
          :class="{ 'tab-link-active': tab.match(url) }"
          :aria-current="tab.match(url) ? 'page' : undefined"
        >
          <NavIcon :name="tab.icon" size="w-6 h-6" />
          <span>{{ tab.label }}</span>
        </Link>
        <div class="flex justify-center items-start">
          <Link
            :href="route('transactions.create')"
            class="-mt-4 w-14 h-14 rounded-2xl bg-primary-600 dark:bg-primary-500 shadow-lg shadow-primary-600/30 flex items-center justify-center text-white active:scale-95 transition-transform"
            aria-label="Thêm giao dịch"
          >
            <NavIcon name="plus" size="w-7 h-7" />
          </Link>
        </div>
        <Link
          v-for="tab in BOTTOM_TABS_END"
          :key="tab.route"
          :href="route(tab.route)"
          class="tab-link"
          :class="{ 'tab-link-active': tab.match(url) }"
          :aria-current="tab.match(url) ? 'page' : undefined"
        >
          <NavIcon :name="tab.icon" size="w-6 h-6" />
          <span>{{ tab.label }}</span>
        </Link>
        <button
          type="button"
          class="tab-link"
          :class="{ 'tab-link-active': isMenuActive(url) || menuOpen }"
          :aria-expanded="menuOpen"
          aria-controls="mobile-menu"
          @click="menuOpen = !menuOpen"
        >
          <NavIcon name="layers" size="w-6 h-6" />
          <span>Thêm</span>
        </button>
      </div>
    </nav>

    <!-- Mobile menu sheet -->
    <Transition name="fade">
      <div
        v-if="menuOpen"
        class="lg:hidden fixed inset-0 z-[45] bg-black/40"
        aria-hidden="true"
        @click="menuOpen = false"
      />
    </Transition>
    <Transition name="sheet">
      <div
        v-if="menuOpen"
        id="mobile-menu"
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
        class="lg:hidden fixed inset-x-0 z-[46] bottom-[calc(4rem+env(safe-area-inset-bottom))] max-h-[75dvh] overflow-y-auto rounded-t-3xl bg-surface border-t border-default shadow-2xl px-safe pt-3 pb-4"
      >
        <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-strong" />
        <div class="max-w-lg mx-auto space-y-5">
          <section
            v-for="group in NAV_SECTIONS.filter((g) => g.id !== 'overview')"
            :key="group.id"
          >
            <h2
              class="px-1 mb-2 text-[11px] font-semibold uppercase tracking-wider text-content-muted"
            >
              {{ group.label }}
            </h2>
            <div class="grid grid-cols-4 gap-2">
              <Link
                v-for="item in group.items"
                :key="item.route"
                :href="route(item.route)"
                class="menu-tile"
                :class="{ 'menu-tile-active': isItemActive(url, item) }"
              >
                <span class="menu-tile-icon">
                  <NavIcon :name="item.icon" />
                </span>
                <span class="leading-tight">{{
                  item.short || item.label
                }}</span>
              </Link>
            </div>
          </section>
          <Link
            :href="route('transactions.create', { scan: 1 })"
            class="btn btn-primary w-full"
          >
            <NavIcon name="qr" size="w-4 h-4" />
            Quét QR chuyển khoản
          </Link>
          <div class="grid grid-cols-2 gap-2">
            <Link :href="route('loans.create')" class="btn btn-secondary">
              <NavIcon name="loan" size="w-4 h-4" />
              Tạo khoản vay
            </Link>
            <Link :href="route('transactions.bulk')" class="btn btn-secondary">
              <NavIcon name="swap" size="w-4 h-4" />
              Nhập nhiều
            </Link>
          </div>
          <button
            type="button"
            class="btn btn-ghost-danger w-full"
            @click="logout"
          >
            <NavIcon name="logout" size="w-4 h-4" />
            Đăng xuất
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.main-content {
  padding-bottom: calc(6rem + env(safe-area-inset-bottom, 0px));
}
@media (min-width: 1024px) {
  .main-content {
    padding-bottom: 2.5rem;
  }
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.sheet-enter-active,
.sheet-leave-active {
  transition:
    transform 0.25s cubic-bezier(0.2, 0, 0, 1),
    opacity 0.2s ease;
}
.sheet-enter-from,
.sheet-leave-to {
  transform: translateY(1.5rem);
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .sheet-enter-active,
  .sheet-leave-active,
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}
</style>
