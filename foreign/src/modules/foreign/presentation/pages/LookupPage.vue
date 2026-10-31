<script setup lang="ts">
import { useLookup } from '../composables/use-lookup'
import WordActions from '../components/WordActions.vue'
import LookupSearch from '../components/LookupSearch.vue'
import DictionaryResult from '../components/DictionaryResult.vue'
const { query, entries, busy, error, searched, search, clear, draft } =
  useLookup()
</script>
<template>
  <div class="lookup-page">
    <section class="lookup-search-pane" aria-label="Dictionary search">
      <div class="lookup-pane-heading">
        <p class="lookup-kicker">ENGLISH DICTIONARY</p>
        <p class="lookup-pane-title">Look up a word</p>
        <p class="muted">Find meanings, examples and pronunciation.</p>
      </div>
      <LookupSearch
        v-model="query"
        :busy="busy"
        @search="search()"
        @clear="clear"
      />
      <p class="lookup-search-note muted">
        Type a word or phrase, then press Enter. Select a related word to look
        it up.
      </p>
    </section>
    <section
      class="lookup-content-pane"
      aria-label="Dictionary results"
      :aria-busy="busy"
    >
      <p v-if="busy" role="status">Looking up…</p>
      <div v-if="error" class="alert" role="alert">
        {{ error }}
        <button
          v-if="searched"
          class="btn btn-secondary btn-sm lookup-retry"
          @click="search(searched)"
        >
          Try again
        </button>
      </div>
      <WordActions v-if="draft" :entry="draft" :allow-save="false" />
      <div v-if="entries.length" class="lookup-result-panel">
        <DictionaryResult
          v-for="(entry, i) in entries"
          :key="`${searched}-${i}`"
          :entry="entry"
          lookup-actions
          @related="search"
        />
      </div>
      <div v-if="!busy && !error && !entries.length" class="lookup-empty">
        <span class="lookup-empty-icon" aria-hidden="true">Aa</span>
        <h2>Your next word starts here</h2>
        <p class="muted">
          Search to explore definitions, examples and related words.
        </p>
      </div>
    </section>
  </div>
</template>
