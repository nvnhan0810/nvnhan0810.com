<script setup lang="ts">
import { ref, onMounted } from "vue";
import { X } from "lucide-vue-next";
defineProps<{ title: string }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
onMounted(() => dialog.value?.showModal());
</script>
<template>
  <dialog
    ref="dialog"
    class="modal"
    @cancel.prevent="emit('close')"
    @click="
      (event) => {
        if (event.target === dialog) emit('close');
      }
    "
  >
    <div class="modal-content">
      <div class="section-heading">
        <h2>{{ title }}</h2>
        <button class="icon-button" aria-label="Đóng" @click="emit('close')">
          <X :size="20" />
        </button>
      </div>
      <slot />
    </div>
  </dialog>
</template>
