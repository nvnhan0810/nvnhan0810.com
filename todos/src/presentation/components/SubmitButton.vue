<script setup lang="ts">
import { Loader2 } from "lucide-vue-next";
// Submit button with a loading state. The label stays in the layout (only hidden) while the
// spinner is drawn on top, so the button keeps the same width in both states.
defineProps<{ busy?: boolean; disabled?: boolean }>();
</script>
<template>
  <button
    type="submit"
    class="primary submit-button"
    :class="{ loading: busy }"
    :disabled="busy || disabled"
    :aria-busy="busy ? 'true' : undefined"
  >
    <span class="submit-label"><slot /></span>
    <span v-if="busy" class="submit-spinner" aria-hidden="true"
      ><Loader2 :size="16"
    /></span>
  </button>
</template>
