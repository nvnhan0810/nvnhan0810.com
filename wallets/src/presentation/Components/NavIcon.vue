<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    name: string
    /** Tailwind size classes. */
    size?: string
  }>(),
  { size: 'w-5 h-5' },
)

// Outline icons on a 24×24 grid, 1.8px stroke.
const PATHS: Record<string, string[]> = {
  home: [
    'M3 10.5 12 3l9 7.5',
    'M5 9v10a2 2 0 0 0 2 2h3v-6h4v6h3a2 2 0 0 0 2-2V9',
  ],
  swap: ['M7 7h13m0 0-4-4m4 4-4 4', 'M17 17H4m0 0 4 4m-4-4 4-4'],
  plus: ['M12 5v14M5 12h14'],
  planning: [
    'M8 3v4m8-4v4M4 10h16',
    'M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z',
  ],
  calendar: [
    'M8 3v4m8-4v4M4 10h16',
    'M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z',
    'M9 14h.01M12 14h.01M15 14h.01M9 17h.01M12 17h.01',
  ],
  profile: ['M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z', 'M4.5 21a7.5 7.5 0 0 1 15 0'],
  wallet: [
    'M4 7a2 2 0 0 1 2-2h11a1 1 0 0 1 1 1v2',
    'M4 7v11a2 2 0 0 0 2 2h13a1 1 0 0 0 1-1v-3',
    'M4 7a1 1 0 0 0 1 1h14a1 1 0 0 1 1 1v3',
    'M20 12h-4a2 2 0 0 0 0 4h4z',
  ],
  loan: [
    'M12 3v18',
    'M17 7.5c0-1.9-2.2-3-5-3s-5 1.1-5 3 2.2 2.8 5 3.4 5 1.6 5 3.6-2.2 3-5 3-5-1.1-5-3',
  ],
  repeat: [
    'M17 2l3 3-3 3',
    'M4 11V9a4 4 0 0 1 4-4h12',
    'M7 22l-3-3 3-3',
    'M20 13v2a4 4 0 0 1-4 4H4',
  ],
  chart: ['M4 20V10m6 10V4m6 16v-7m4 7H3'],
  target: [
    'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
    'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z',
    'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
  ],
  template: [
    'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2',
    'M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2 2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2z',
    'M9 12h6m-6 4h4',
  ],
  settings: [
    'M10.3 4.3c.4-1.7 3-1.7 3.4 0a1.7 1.7 0 0 0 2.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 0 0 1 2.5c1.8.4 1.8 3 0 3.4a1.7 1.7 0 0 0-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 0 0-2.6 1c-.4 1.8-3 1.8-3.4 0a1.7 1.7 0 0 0-2.6-1c-1.5.9-3.3-.9-2.4-2.4a1.7 1.7 0 0 0-1-2.6c-1.8-.4-1.8-3 0-3.4a1.7 1.7 0 0 0 1-2.5c-.9-1.6.9-3.3 2.4-2.4a1.7 1.7 0 0 0 2.6-1.1z',
    'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
  ],
  back: ['M15 19l-7-7 7-7'],
  chevron: ['M9 5l7 7-7 7'],
  sun: [
    'M12 3v1m0 16v1m9-9h-1M4 12H3m15.4 6.4-.7-.7M6.3 6.3l-.7-.7m12.8 0-.7.7M6.3 17.7l-.7.7',
    'M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
  ],
  moon: ['M20.4 15.4A9 9 0 0 1 8.6 3.6 9 9 0 1 0 20.4 15.4z'],
  logout: ['M15 16l4-4-4-4m4 4H9', 'M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6'],
  download: ['M12 4v11m0 0-4-4m4 4 4-4', 'M5 20h14'],
  layers: ['M12 3 3 8l9 5 9-5-9-5z', 'M3 13l9 5 9-5'],
}

const paths = computed(() => PATHS[props.name] ?? [])
</script>

<template>
  <svg
    :class="size"
    class="shrink-0"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path v-for="(d, i) in paths" :key="i" :d="d" />
  </svg>
</template>
