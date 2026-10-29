<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRight, Github, Linkedin, Mail, MapPin, Phone } from 'lucide-vue-next'
import { Button } from '@/shared/components/ui/button'
import { siteLinks } from '@/shared/config/site'
import type { SectionId } from '../../domain/entities/portfolio'
import { usePortfolio } from '../composables/use-portfolio'
import LocaleSwitcher from '../components/LocaleSwitcher.vue'
import QuickLinks from '../components/QuickLinks.vue'

const { portfolio, locale, messages, year, loading, error, retry } = usePortfolio()
const cv = computed(() => messages.value?.cv)
const sections: readonly SectionId[] = ['about', 'skills', 'apps', 'contact']
const navItems = computed(() =>
  sections.map((id) => ({ id, label: messages.value?.nav[id] ?? id })),
)
const apps = computed(
  () =>
    portfolio.value?.apps.map((app) => ({
      ...app,
      content: messages.value?.apps.items[app.slug],
      href:
        app.kind === 'project'
          ? `${siteLinks.apps}/${encodeURIComponent(app.slug)}`
          : siteLinks.apps,
    })) ?? [],
)

function scrollToSection(id: string): void {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  document
    .getElementById(id)
    ?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' })
  window.history.replaceState(null, '', `#${id}`)
}
</script>

<template>
  <div
    class="min-h-screen max-w-[100vw] overflow-x-hidden bg-background font-sans text-foreground antialiased"
  >
    <a
      href="#main-content"
      class="sr-only z-60 rounded bg-foreground p-3 text-background focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
      >Skip to content</a
    >
    <div v-if="loading && !portfolio" role="status" class="py-32 text-center text-muted-foreground">
      Loading portfolio…
    </div>
    <div v-else-if="error" role="alert" class="space-y-4 py-32 text-center">
      <p>{{ error }}</p>
      <Button :disabled="loading" @click="retry">Try again</Button>
    </div>
    <template v-else-if="portfolio && messages && cv">
      <nav
        aria-label="Main navigation"
        class="sticky top-0 z-50 w-full max-w-[100vw] overflow-x-hidden border-b border-border/60 bg-background/80 backdrop-blur-md"
      >
        <div
          class="mx-auto flex w-full min-w-0 max-w-5xl items-center justify-between gap-2 px-4 py-3 sm:gap-4 sm:px-6"
        >
          <a
            href="#top"
            class="min-w-0 truncate text-sm font-semibold tracking-tight"
            @click.prevent="scrollToSection('top')"
            >{{ cv.name }}</a
          >
          <div class="hidden min-w-0 items-center gap-4 text-sm text-muted-foreground sm:flex">
            <a
              v-for="item in navItems"
              :key="item.id"
              :href="`#${item.id}`"
              class="transition-colors hover:text-foreground"
              @click.prevent="scrollToSection(item.id)"
              >{{ item.label }}</a
            >
            <LocaleSwitcher v-model="locale" :label="messages.common.language" />
          </div>
          <div class="flex shrink-0 items-center gap-3 sm:hidden">
            <LocaleSwitcher v-model="locale" :label="messages.common.language" />
          </div>
        </div>
      </nav>
      <main id="top" class="mx-auto mb-8 max-w-5xl px-4 pt-12 pb-8 sm:px-6 sm:pt-16 md:mb-0">
        <div id="main-content" tabindex="-1" />
        <section
          id="about"
          class="mb-20 scroll-mt-20 text-center sm:text-left"
          aria-labelledby="profile-name"
        >
          <p class="mb-3 text-sm font-medium tracking-widest text-emerald-500 uppercase">
            {{ messages.home.portfolioLabel }}
          </p>
          <h1 id="profile-name" class="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
            {{ cv.name }}
          </h1>
          <p class="mt-3 text-xl text-muted-foreground sm:text-2xl">{{ cv.title }}</p>
          <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:mx-0">
            {{ cv.summary }}
          </p>
          <div class="mt-8 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
            <Button as-child variant="outline" class="h-auto px-4 py-2 font-normal"
              ><a :href="`mailto:${cv.email}`"
                ><Mail class="size-4" aria-hidden="true" />{{ messages.home.contactMe }}</a
              ></Button
            >
            <Button as-child variant="outline" class="h-auto px-4 py-2 font-normal"
              ><a :href="portfolio.profile.githubLink" target="_blank" rel="noopener noreferrer"
                ><Github class="size-4" aria-hidden="true" />GitHub</a
              ></Button
            >
            <Button as-child variant="outline" class="h-auto px-4 py-2 font-normal"
              ><a :href="portfolio.profile.linkedinLink" target="_blank" rel="noopener noreferrer"
                ><Linkedin class="size-4" aria-hidden="true" />LinkedIn</a
              ></Button
            >
          </div>
        </section>
        <section class="mb-20 scroll-mt-20" aria-labelledby="about-title">
          <h2 id="about-title" class="mb-6 text-2xl font-bold">{{ messages.home.about }}</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5">
              <p class="text-sm text-muted-foreground">{{ messages.home.location }}</p>
              <p class="mt-1 flex items-center gap-2 font-medium">
                <MapPin class="size-4 text-emerald-500" aria-hidden="true" />{{ cv.location }}
              </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
              <p class="text-sm text-muted-foreground">{{ messages.home.phone }}</p>
              <p class="mt-1 flex items-center gap-2 font-medium">
                <Phone class="size-4 text-emerald-500" aria-hidden="true" />{{ cv.phone }}
              </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-5 sm:col-span-2">
              <p class="text-sm text-muted-foreground">{{ messages.home.education }}</p>
              <p class="mt-1 font-medium">{{ cv.education.school }}</p>
              <p class="mt-1 text-sm text-muted-foreground">
                {{ cv.education.major }} · {{ cv.education.period }} · GPA {{ cv.education.gpa }}
              </p>
            </div>
          </div>
        </section>
        <section id="skills" class="mb-20 scroll-mt-20" aria-labelledby="skills-title">
          <h2 id="skills-title" class="mb-6 text-2xl font-bold">{{ messages.home.skills }}</h2>
          <ul class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
            <li v-for="tool in portfolio.techStack" :key="`${tool.slug}-${tool.name}`">
              <div
                class="group flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-4 transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5"
                :title="tool.name"
              >
                <img
                  :src="tool.iconUrl ?? `https://cdn.simpleicons.org/${tool.slug}/${tool.color}`"
                  alt=""
                  width="32"
                  height="32"
                  class="size-8 opacity-90 transition-opacity group-hover:opacity-100"
                  loading="lazy"
                  decoding="async"
                />
                <span
                  class="text-center text-xs text-muted-foreground transition-colors group-hover:text-foreground"
                  >{{ tool.name }}</span
                >
              </div>
            </li>
          </ul>
        </section>
        <section id="apps" class="mb-20 scroll-mt-20" aria-labelledby="apps-title">
          <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="mb-2 text-sm font-medium tracking-widest text-emerald-500 uppercase">
                {{ messages.apps.label }}
              </p>
              <h2 id="apps-title" class="text-2xl font-bold">{{ messages.home.appsTitle }}</h2>
              <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                {{ messages.apps.description }}
              </p>
            </div>
            <a
              :href="siteLinks.apps"
              class="inline-flex shrink-0 items-center gap-1.5 text-sm text-emerald-500 transition-colors hover:text-emerald-400"
              >{{ messages.home.viewAllApps }}<ArrowRight class="size-4" aria-hidden="true"
            /></a>
          </div>
          <div class="grid gap-5 md:grid-cols-2">
            <a
              v-for="app in apps"
              :key="app.slug"
              :href="app.href"
              class="group rounded-xl border border-border bg-card p-5 transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5"
            >
              <p class="text-xs text-muted-foreground">{{ app.label }}</p>
              <h3
                class="mt-2 text-lg font-semibold text-foreground transition-colors group-hover:text-emerald-500"
              >
                {{ app.content?.name ?? app.label }}
              </h3>
              <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                {{ app.content?.summary }}
              </p>
              <div class="mt-4 flex flex-wrap gap-2">
                <span
                  v-for="tag in app.tags"
                  :key="tag"
                  class="rounded-full border border-emerald-600/30 bg-emerald-600/10 px-2 py-0.5 text-xs font-medium text-emerald-500"
                  >{{ tag }}</span
                >
              </div>
            </a>
          </div>
        </section>
        <section id="contact" class="scroll-mt-20" aria-labelledby="contact-title">
          <h2 id="contact-title" class="mb-6 text-2xl font-bold">{{ messages.home.contact }}</h2>
          <div class="rounded-xl border border-border bg-card p-6">
            <p class="text-muted-foreground">{{ cv.languages }}</p>
            <p class="mt-3 text-sm text-muted-foreground">
              {{ messages.home.interests }}: {{ cv.interests.join(' · ') }}
            </p>
            <div class="mt-6 flex flex-wrap gap-4">
              <a
                :href="`mailto:${cv.email}`"
                class="text-sm text-foreground hover:text-emerald-500"
                >{{ cv.email }}</a
              ><a :href="siteLinks.posts" class="text-sm text-emerald-500 hover:text-emerald-400"
                >{{ messages.home.readBlog }} →</a
              >
            </div>
          </div>
        </section>
        <footer class="mt-16 border-t border-border pt-8 text-center text-sm text-muted-foreground">
          © {{ year }} {{ cv.name }}
        </footer>
      </main>
      <QuickLinks :messages="messages" />
    </template>
  </div>
</template>
