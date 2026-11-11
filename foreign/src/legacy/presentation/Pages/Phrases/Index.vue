<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PhraseEntry } from '@/domain/meanings-json'
import { RoutePath } from '../../../../app/router/paths'
const props = defineProps<{ items: PhraseEntry[] }>()
const query = ref('')
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return props.items.filter((item) =>
    `${item.word} ${item.meanings.map((m) => m.definition).join(' ')}`
      .toLowerCase()
      .includes(q),
  )
})
</script>
<template>
  <Head title="Phrases" />
  <AppLayout title="Phrases" heading="Phrases / clauses">
    <p class="muted">
      Phrases are saved separately. Select a phrase to view its meanings, copy a
      prompt, or update its JSON.
    </p>
    <label for="phrase-search">Search phrases</label>
    <input
      id="phrase-search"
      v-model="query"
      type="search"
      class="form-control"
      placeholder="Search by phrase or meaning..."
    />
    <p v-if="!items.length" class="empty-state">
      No phrases saved yet. Use Copy prompt in Lookup, then save JSON containing
      a phrases list.
    </p>
    <p v-else-if="!filtered.length" class="empty-state">
      No matching phrases found.
    </p>
    <article v-for="item in filtered" :key="item.word" class="card">
      <h2 class="card-title">
        <Link
          :href="`${RoutePath.lookup}?q=${encodeURIComponent(item.word)}&kind=phrase`"
          >{{ item.word }}</Link
        >
      </h2>
      <p v-for="(meaning, i) in item.meanings" :key="i">
        {{ meaning.definition }}
      </p>
    </article>
  </AppLayout>
</template>
