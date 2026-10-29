<script setup lang="ts">
import { computed } from 'vue'
import { BookOpen, Boxes, ListTodo, Newspaper } from 'lucide-vue-next'
import { siteLinks } from '@/shared/config/site'
import type { HomeMessages } from '../../domain/entities/portfolio'

const props = defineProps<{ messages: HomeMessages }>()
const links = computed(() => [
  { href: siteLinks.posts, label: props.messages.home.allPosts, icon: BookOpen },
  { href: siteLinks.apps, label: props.messages.nav.apps, icon: Boxes },
  { href: siteLinks.news, label: props.messages.nav.news, icon: Newspaper },
  { href: siteLinks.todos, label: props.messages.nav.todosApp, icon: ListTodo },
])
</script>

<template>
  <nav
    aria-label="Quick links"
    class="fixed top-1/2 right-0 z-40 hidden -translate-y-1/2 flex-col md:flex"
  >
    <a
      v-for="link in links"
      :key="link.href"
      :href="link.href"
      :aria-label="link.label"
      class="group relative inline-flex items-center gap-1.5 rounded-l-sm border border-border p-2 text-sm text-emerald-500 hover:border-emerald-500 hover:bg-primary/10"
    >
      <component :is="link.icon" class="size-6" aria-hidden="true" />
      <span
        class="pointer-events-none absolute top-1/2 right-full mr-1 -translate-y-1/2 rounded bg-foreground px-2 py-1 text-xs whitespace-nowrap text-background opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100"
        >{{ link.label }}</span
      >
    </a>
  </nav>
  <nav
    aria-label="Mobile quick links"
    class="fixed bottom-0 z-40 flex w-full items-center justify-center border-t border-primary/30 bg-background pb-[env(safe-area-inset-bottom)] md:hidden"
  >
    <a
      v-for="link in links"
      :key="link.href"
      :href="link.href"
      class="inline-flex min-w-0 flex-col items-center gap-1.5 p-2 text-xs text-emerald-500 hover:bg-primary/10 sm:text-sm"
    >
      <component :is="link.icon" class="size-6" aria-hidden="true" /><span>{{ link.label }}</span>
    </a>
  </nav>
</template>
