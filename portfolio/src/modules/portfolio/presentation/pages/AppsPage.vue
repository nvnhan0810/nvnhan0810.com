<script setup lang="ts">
import { computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { usePortfolio } from '../composables/use-portfolio'
import LocaleSwitcher from '../components/LocaleSwitcher.vue'

const route = useRoute()
const { portfolio, locale, messages, loading, error, retry } = usePortfolio()
const copy = computed(() => portfolio.value?.appsMessages?.[locale.value].apps)
const app = computed(() => portfolio.value?.apps.find((item) => item.slug === route.params.slug))
const detail = computed(() => app.value && copy.value?.details[app.value.slug])
const tables = computed(() => {
  const d = detail.value
  if (!d) return []
  return [
    ...(d.learningFlow
      ? [
          {
            title: d.learningFlow.title,
            intro: d.learningFlow.intro,
            loop: d.learningFlow.loop,
            note: d.learningFlow.note,
            headers: [
              d.learningFlow.stepColumn,
              d.learningFlow.extensionColumn,
              d.learningFlow.mobileColumn,
            ],
            rows: d.learningFlow.steps.map((r) => [r.step, r.extension, r.mobile]),
          },
        ]
      : []),
    ...(d.whenToUse
      ? [
          {
            title: d.whenToUse.title,
            headers: [d.whenToUse.situationColumn, d.whenToUse.suggestionColumn],
            rows: d.whenToUse.items.map((r) => [r.situation, r.suggestion]),
          },
        ]
      : []),
    ...(d.shortcuts
      ? [
          {
            title: d.shortcuts.title,
            headers: [d.shortcuts.shortcutColumn, d.shortcuts.actionColumn],
            rows: d.shortcuts.items.map((r) => [r.shortcut, r.action]),
          },
        ]
      : []),
  ]
})
function updateMetadata() {
  if (typeof document === 'undefined' || !copy.value) return
  document.title = `${detail.value?.name ?? copy.value.title} | ${copy.value.label}`
  document
    .querySelector('meta[name="description"]')
    ?.setAttribute('content', detail.value?.metaDescription ?? copy.value.metaDescription)
}
watch([copy, detail], updateMetadata, { immediate: true, flush: 'post' })
onMounted(() => {
  void nextTick(updateMetadata)
})
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <nav v-if="copy" aria-label="Main navigation" class="border-b border-border bg-background/80">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <RouterLink to="/" class="font-semibold">{{ messages?.cv.name ?? 'Portfolio' }}</RouterLink>
        <div class="flex items-center gap-4">
          <RouterLink to="/apps" class="text-emerald-500">{{ copy.label }}</RouterLink>
          <LocaleSwitcher v-model="locale" :label="messages?.common.language ?? 'Language'" />
        </div>
      </div>
    </nav>
    <main class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
      <p v-if="loading && !portfolio" role="status">Loading…</p>
      <div v-else-if="error" role="alert">
        <p>{{ error }}</p>
        <button @click="retry">Try again</button>
      </div>
      <template v-else-if="portfolio && copy">
        <RouterLink
          v-if="detail"
          to="/apps"
          class="mb-8 inline-block text-sm text-muted-foreground hover:text-emerald-500"
          >← {{ copy.backToApps }}</RouterLink
        >
        <header class="mb-10">
          <p class="mb-2 text-sm font-medium tracking-widest text-emerald-500 uppercase">
            {{ copy.label }}
          </p>
          <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">
            {{ detail?.name ?? copy.title }}
          </h1>
          <p class="mt-3 max-w-2xl text-muted-foreground">
            {{ detail?.tagline ?? copy.description }}
          </p>
          <div v-if="app" class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="tag in app.tags"
              :key="tag"
              class="rounded-full border border-emerald-600/30 bg-emerald-600/10 px-3 py-1 text-xs text-emerald-500"
              >{{ tag }}</span
            >
          </div>
        </header>
        <div v-if="!detail" class="grid gap-6">
          <article
            v-for="item in portfolio.apps"
            :key="item.slug"
            class="overflow-hidden rounded-xl border border-border bg-card"
          >
            <header class="border-b border-border bg-muted/30 px-6 py-4">
              <h2 class="text-xl font-bold">{{ copy.items[item.slug]?.name }}</h2>
              <p class="mt-1 text-sm text-muted-foreground">{{ item.label }}</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <span
                  v-for="tag in item.tags"
                  :key="tag"
                  class="rounded-full bg-emerald-600/10 px-3 py-1 text-xs text-emerald-500"
                  >{{ tag }}</span
                >
              </div>
            </header>
            <div class="space-y-5 px-6 py-5">
              <p class="leading-relaxed text-muted-foreground">
                {{ copy.items[item.slug]?.summary }}
              </p>
              <h3 class="text-sm font-semibold uppercase">{{ copy.featuresLabel }}</h3>
              <ul class="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                <li v-for="feature in copy.items[item.slug]?.features" :key="feature">
                  {{ feature }}
                </li>
              </ul>
              <div class="flex flex-wrap gap-3">
                <RouterLink
                  v-if="item.kind === 'project'"
                  :to="`/apps/${item.slug}`"
                  class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                  >{{ copy.viewDetails }} →</RouterLink
                >
                <a
                  v-if="item.packagistUrl"
                  :href="item.packagistUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="rounded-lg border border-border px-4 py-2 text-sm"
                  >{{ copy.viewOnPackagist }} ↗</a
                >
                <a
                  v-if="item.npmUrl"
                  :href="item.npmUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="rounded-lg border border-border px-4 py-2 text-sm"
                  >{{ copy.viewOnNpm }} ↗</a
                >
                <a
                  :href="item.githubUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="rounded-lg border border-border px-4 py-2 text-sm"
                  >{{ copy.viewOnGithub }} ↗</a
                >
              </div>
            </div>
          </article>
        </div>
        <div v-else class="space-y-10">
          <section class="rounded-xl border border-border bg-card p-6 md:p-8">
            <p class="leading-relaxed text-muted-foreground">{{ detail.summary }}</p>
            <ul class="mt-5 list-inside list-disc space-y-2 text-sm text-muted-foreground">
              <li v-for="highlight in detail.highlights" :key="highlight">{{ highlight }}</li>
            </ul>
          </section>
          <section v-for="group in detail.featureGroups" :key="group.title">
            <h2 class="mb-4 text-xl font-bold">{{ group.title }}</h2>
            <ul
              class="list-inside list-disc space-y-2 rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground"
            >
              <li v-for="item in group.items" :key="item">{{ item }}</li>
            </ul>
          </section>
          <section v-for="table in tables" :key="table.title">
            <h2 class="mb-4 text-xl font-bold">{{ table.title }}</h2>
            <p v-if="'intro' in table" class="mb-4 text-sm text-muted-foreground">
              {{ table.intro }}
            </p>
            <p v-if="'loop' in table" class="mb-4 text-emerald-500">{{ table.loop.join(' → ') }}</p>
            <div class="overflow-x-auto rounded-xl border border-border">
              <table class="w-full min-w-[480px] text-left text-sm">
                <thead class="bg-muted/50">
                  <tr>
                    <th v-for="heading in table.headers" :key="heading" class="px-4 py-3">
                      {{ heading }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <tr v-for="(row, index) in table.rows" :key="index" class="bg-card">
                    <td
                      v-for="(cell, column) in row"
                      :key="column"
                      class="px-4 py-3 text-muted-foreground"
                    >
                      {{ cell }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="'note' in table" class="mt-3 text-sm text-muted-foreground">
              {{ table.note }}
            </p>
          </section>
          <section v-if="detail.layers">
            <h2 class="mb-4 text-xl font-bold">{{ detail.layers.title }}</h2>
            <p class="mb-4 text-sm text-muted-foreground">{{ detail.layers.description }}</p>
            <div class="grid gap-4 md:grid-cols-3">
              <article
                v-for="layer in detail.layers.items"
                :key="layer.name"
                class="rounded-xl border border-border bg-card p-5"
              >
                <h3 class="font-semibold">{{ layer.name }}</h3>
                <p class="mt-2 text-sm text-muted-foreground">{{ layer.description }}</p>
                <p class="mt-3 font-mono text-xs text-emerald-500">{{ layer.stack }}</p>
              </article>
            </div>
          </section>
          <section>
            <h2 class="mb-4 text-xl font-bold">{{ detail.techStack.title }}</h2>
            <div class="overflow-x-auto rounded-xl border border-border">
              <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                  <tr v-for="row in detail.techStack.items" :key="row.component" class="bg-card">
                    <th scope="row" class="px-4 py-3">{{ row.component }}</th>
                    <td class="px-4 py-3 text-muted-foreground">{{ row.tech }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
          <section>
            <h2 class="mb-4 text-xl font-bold">{{ detail.repoStructure.title }}</h2>
            <div class="divide-y divide-border rounded-xl border border-border bg-card">
              <div
                v-for="row in detail.repoStructure.items"
                :key="row.folder"
                class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:gap-4"
              >
                <code class="text-sm break-all text-emerald-500">{{ row.folder }}</code
                ><span class="text-sm text-muted-foreground">{{ row.description }}</span>
              </div>
            </div>
          </section>
          <p v-if="detail.platformsNote" class="text-sm text-muted-foreground">
            {{ detail.platformsNote }}
          </p>
          <a
            :href="app?.githubUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-block rounded-lg bg-emerald-600 px-5 py-3 text-sm font-medium text-white"
            >{{ copy.viewOnGithub }} ↗</a
          >
        </div>
      </template>
    </main>
  </div>
</template>
