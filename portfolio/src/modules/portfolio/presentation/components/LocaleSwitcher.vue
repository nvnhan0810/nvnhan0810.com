<script setup lang="ts">
import { Button } from '@/shared/components/ui/button'
import type { Locale } from '../../domain/entities/portfolio'

const locale = defineModel<Locale>({ required: true })
defineProps<{ label: string }>()
const options = ['en', 'vi'] as const
</script>

<template>
  <div
    class="inline-flex rounded-md border border-border p-0.5 text-xs font-medium"
    role="group"
    :aria-label="label"
  >
    <Button
      v-for="option in options"
      :key="option"
      variant="ghost"
      :aria-pressed="locale === option"
      :class="[
        'h-auto rounded px-2 py-1 text-xs font-medium transition-colors',
        locale === option
          ? 'bg-emerald-600 text-white hover:bg-emerald-600 hover:text-white'
          : 'text-muted-foreground hover:text-foreground',
      ]"
      @click="locale = option"
      >{{ option.toUpperCase() }}</Button
    >
  </div>
</template>
