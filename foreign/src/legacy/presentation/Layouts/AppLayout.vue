<script setup lang="ts">
import { RoutePath } from '../../../app/router/paths'

import { useRoute } from 'vue-router'
import type { PropType } from 'vue'
import { appPath } from '@/path'
import { computed, onMounted, onUnmounted, useSlots, watch } from 'vue'
import { Link, usePage } from '@/application/navigation'
import PullToRefresh from '@/Components/PullToRefresh.vue'

const props = defineProps({
  title: { type: String as PropType<string>, default: 'FLC' },
  heading: { type: String as PropType<string | null>, default: null },
  hideNav: { type: Boolean as PropType<boolean>, default: false },
  hideHeader: { type: Boolean as PropType<boolean>, default: false },
  gameScreen: { type: Boolean as PropType<boolean>, default: false },
  gameBg: { type: Boolean as PropType<boolean>, default: false },
  backHref: { type: String as PropType<string | null>, default: null },
  /** Disable pull-to-refresh (embed WebView sheets). */
  disablePullToRefresh: { type: Boolean as PropType<boolean>, default: false },
})

const slots = useSlots()
const page = usePage()
const route = useRoute()
const flash = computed(() => page.props.flash || {})
const errors = computed(() => Object.values(page.props.errors || {}))
const isFlcApp = computed(() => !!page.props.isFlcApp)
const url = computed(() => route?.path ?? page.url.split('?')[0] ?? '/')
const hasCustomHeader = computed(() => !!slots.header)

const nav = [
  {
    href: appPath(RoutePath.lookup),
    label: 'Lookup',
    icon: '📖',
    match: (u: string) => u.startsWith(appPath(RoutePath.lookup)),
  },
  {
    href: appPath(RoutePath.vocabulary),
    label: 'Vocabulary',
    icon: '🔖',
    match: (u: string) => u.startsWith(appPath(RoutePath.vocabulary)),
  },
  {
    href: appPath(RoutePath.media),
    label: 'Listen',
    icon: '🎧',
    match: (u: string) =>
      u.startsWith(appPath(RoutePath.media)) || u === RoutePath.channels,
  },
  {
    href: appPath(RoutePath.games),
    label: 'Games',
    icon: '🎮',
    match: (u: string) =>
      u.startsWith(appPath(RoutePath.games)) ||
      u.startsWith(appPath(RoutePath.puzzle)),
  },
  {
    href: appPath(RoutePath.profile),
    label: 'Profile',
    icon: '👤',
    match: (u: string) => u.startsWith(appPath(RoutePath.profile)),
  },
]

function syncBodyClass() {
  const body = document.body
  body.classList.add('user-body')
  body.classList.toggle('flc-app', isFlcApp.value)
  body.classList.toggle('user-no-nav', props.hideNav)
  body.classList.toggle('user-no-header', props.hideHeader)
  body.classList.toggle('user-game', props.gameScreen)
  body.classList.toggle('user-game-bg', props.gameBg)
}

function applyTheme(choice: string | null) {
  const key = 'flc-theme'
  const stored = choice || localStorage.getItem(key) || 'system'
  localStorage.setItem(key, stored)
  const dark =
    stored === 'dark' ||
    (stored === 'system' &&
      window.matchMedia('(prefers-color-scheme: dark)').matches)
  document.documentElement.dataset.theme = dark ? 'dark' : 'light'
  const meta = document.querySelector('meta[name="theme-color"]')
  if (meta) meta.setAttribute('content', dark ? '#1a2332' : '#4361ee')
}

onMounted(() => {
  applyTheme(page.props.flcTheme || null)
  syncBodyClass()
})

watch(
  () => [
    props.hideNav,
    props.hideHeader,
    props.gameScreen,
    props.gameBg,
    isFlcApp.value,
  ],
  syncBodyClass,
)

watch(
  () => page.props.flcTheme,
  (value) => {
    if (value) applyTheme(value)
  },
)

onUnmounted(() => {
  document.body.classList.remove(
    'user-no-nav',
    'user-no-header',
    'user-game',
    'user-game-bg',
  )
})
</script>

<template>
  <div class="user-shell">
    <PullToRefresh :disabled="gameScreen || disablePullToRefresh" />

    <template v-if="!hideHeader">
      <slot v-if="hasCustomHeader" name="header" />
      <header v-else class="user-header">
        <Link
          v-if="backHref"
          :href="backHref"
          class="user-header-back"
          aria-label="Back"
          >←</Link
        >
        <span v-else class="user-header-spacer" aria-hidden="true" />
        <h1>{{ heading ?? title }}</h1>
        <span class="user-header-spacer" aria-hidden="true" />
      </header>
    </template>

    <slot name="below-header" />

    <main class="user-main" :class="{ 'user-main-game': gameScreen }">
      <div v-if="flash.success" class="alert alert-success">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="alert alert-error">{{ flash.error }}</div>
      <div v-if="errors.length" class="alert alert-error">
        <ul style="margin: 0; padding-left: 18px">
          <li v-for="(error, i) in errors" :key="i">{{ error }}</li>
        </ul>
      </div>

      <slot />
    </main>

    <nav v-if="!hideNav" class="user-nav" aria-label="Main navigation">
      <Link
        v-for="item in nav"
        :key="item.href"
        :href="item.href"
        :class="{ active: item.match(url) }"
      >
        <span class="icon">{{ item.icon }}</span>
        {{ item.label }}
      </Link>
    </nav>
  </div>
</template>
