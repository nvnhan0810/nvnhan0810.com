<script setup lang="ts">
import { ref } from 'vue'
import { useMyWords } from '../composables/use-my-words'
import DictionaryResult from '../components/DictionaryResult.vue'
const { query, items, busy, error, next, load, search } = useMyWords()
const opened = ref<number | null>(null)
</script>
<template>
  <section class="my-words-page">
    <h2>Từ của tôi</h2>
    <form class="my-words-search" role="search" @submit.prevent="search">
      <label class="sr-only" for="my-words-query">Tìm từ đã lưu</label
      ><input
        id="my-words-query"
        v-model="query"
        class="form-control"
        maxlength="100"
        placeholder="Tìm từ đã lưu…"
      /><button class="btn" :disabled="busy">Tìm</button>
    </form>
    <p v-if="busy" role="status">Đang tải…</p>
    <p v-if="error" class="alert" role="alert">
      {{ error }}
      <button class="btn btn-secondary btn-sm" @click="load(!!items.length)">
        Thử lại
      </button>
    </p>
    <p v-if="!busy && !error && !items.length" class="muted">
      {{
        query
          ? 'Không tìm thấy từ phù hợp.'
          : 'Bạn chưa lưu từ nào. Tra từ và chọn Save word để bắt đầu.'
      }}
    </p>
    <div class="my-words-grid">
      <article v-for="item in items" :key="item.id" class="card saved-word">
        <button
          class="saved-word-heading"
          :aria-expanded="opened === item.id"
          @click="opened = opened === item.id ? null : item.id"
        >
          <span
            ><strong>{{ item.word }}</strong
            ><small class="muted">{{ item.entry.phonetic }}</small></span
          ><span>{{ opened === item.id ? '−' : '+' }}</span>
        </button>
        <p v-if="opened !== item.id" class="muted">
          {{ item.entry.meanings[0]?.definitions[0]?.definition }}
        </p>
        <DictionaryResult
          v-if="opened === item.id"
          :entry="item.entry"
          @related="
            (word) => $router.push({ path: '/lookup', query: { q: word } })
          "
        />
      </article>
    </div>
    <button
      v-if="next"
      class="btn btn-secondary"
      :disabled="busy"
      @click="load(true)"
    >
      Tải thêm
    </button>
  </section>
</template>
