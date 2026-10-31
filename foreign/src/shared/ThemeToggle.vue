<script setup lang="ts">
import { Moon, Sun } from 'lucide-vue-next'
import { theme, toggleTheme } from './theme'
</script>
<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="
      theme === 'dark'
        ? 'Chuyển sang giao diện sáng'
        : 'Chuyển sang giao diện tối'
    "
    :title="theme === 'dark' ? 'Giao diện sáng' : 'Giao diện tối'"
    @click="toggleTheme"
  >
    <Sun v-if="theme === 'dark'" :size="20" aria-hidden="true" />
    <Moon v-else :size="20" aria-hidden="true" />
  </button>
</template>
