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
  <Head title="Cụm từ" />
  <AppLayout title="Cụm từ" heading="Cụm từ / mệnh đề">
    <p class="muted">
      Các cụm từ được lưu riêng. Chọn một cụm để xem nghĩa, copy prompt hoặc cập
      nhật JSON.
    </p>
    <label for="phrase-search">Tìm cụm từ</label>
    <input
      id="phrase-search"
      v-model="query"
      type="search"
      class="form-control"
      placeholder="Tìm theo cụm từ hoặc nghĩa..."
    />
    <p v-if="!items.length" class="empty-state">
      Chưa có cụm từ. Dùng Copy prompt ở Lookup rồi lưu JSON có danh sách
      phrases.
    </p>
    <p v-else-if="!filtered.length" class="empty-state">
      Không tìm thấy cụm từ phù hợp.
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
