<script setup lang="ts">
import { nextTick, onMounted, ref, useId, watch } from 'vue'
import { X } from 'lucide-vue-next'
const props = defineProps<{ open: boolean; title: string; wide?: boolean; busy?: boolean }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement>()
const titleId = useId()
async function sync() {
  await nextTick()
  if (props.open && !dialog.value?.open) dialog.value?.showModal()
  else if (!props.open && dialog.value?.open) dialog.value.close()
}
watch(() => props.open, sync)
onMounted(sync)
function close() {
  if (!props.busy) emit('close')
}
</script>
<template>
  <dialog
    ref="dialog"
    :class="['modal', { 'modal-wide': wide }]"
    :aria-labelledby="titleId"
    @cancel.prevent="close"
    @click="
      (event) => {
        if (event.target === event.currentTarget) close()
      }
    "
  >
    <div class="modal-heading">
      <h2 :id="titleId">{{ title }}</h2>
      <button class="icon-button" :disabled="busy" aria-label="Đóng cửa sổ" @click="close">
        <X :size="20" />
      </button>
    </div>
    <slot />
  </dialog>
</template>
