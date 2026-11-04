<script setup lang="ts">
import { RoutePath } from '../../../../app/router/paths'

import { appPath } from '@/path'
import { Head, Link } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'

const gamePaths: Record<string, RoutePath> = {
  scramble: RoutePath.scramble,
  wordle: RoutePath.wordle,
  hangman: RoutePath.hangman,
  'word-search': RoutePath.wordSearch,
}
</script>

<template>
  <Head title="Games" />
  <AppLayout title="Games" heading="Games" game-bg>
    <div class="puzzle-hub-body">
      <p class="puzzle-hub-kicker">Play &amp; learn</p>

      <div class="puzzle-mode-grid">
        <Link
          :href="appPath(`${RoutePath.quiz}?autostart=1`)"
          class="puzzle-mode-card puzzle-mode-live"
        >
          <span class="puzzle-mode-thumb">
            <img
              :src="appPath('/images/puzzle/quiz.svg')"
              alt="Quiz"
              loading="lazy"
            />
            <span class="puzzle-mode-tag puzzle-mode-tag-play">Play</span>
          </span>
          <span class="puzzle-mode-info">
            <span class="puzzle-mode-title">Quiz</span>
            <span class="puzzle-mode-desc"
              >Multiple-choice review of saved words</span
            >
          </span>
        </Link>

        <Link
          v-for="mode in ['scramble', 'wordle', 'hangman', 'word-search']"
          :key="mode"
          :href="appPath(gamePaths[mode]!)"
          class="puzzle-mode-card puzzle-mode-live"
        >
          <span class="puzzle-mode-thumb"
            ><img
              :src="appPath(`/images/puzzle/${mode}.svg`)"
              :alt="mode"
              loading="lazy"
            /><span class="puzzle-mode-tag puzzle-mode-tag-play"
              >Play</span
            ></span
          >
          <span class="puzzle-mode-info"
            ><span class="puzzle-mode-title">{{
              mode === 'word-search'
                ? 'Word Search'
                : mode.charAt(0).toUpperCase() + mode.slice(1)
            }}</span
            ><span class="puzzle-mode-desc">Review your saved words</span></span
          >
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
