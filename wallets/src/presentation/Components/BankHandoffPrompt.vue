<script setup lang="ts">
import { computed } from 'vue'
import { dismissBankHandoff, pendingHandoff } from '@/application/bank-handoff'

const handoff = computed(() => pendingHandoff.handoff)
const copiedAccount = computed(() => pendingHandoff.copiedAccount)
</script>

<template>
  <section
    v-if="handoff"
    class="card mb-4 flex items-start gap-3 p-4 border-sky-200 dark:border-sky-800"
    role="status"
    aria-label="Mở app ngân hàng"
    data-testid="bank-handoff-prompt"
  >
    <div class="min-w-0 flex-1">
      <p class="font-semibold text-content">
        Đã lưu giao dịch. Chuyển khoản trên {{ handoff.appName }}
      </p>
      <p class="text-sm text-content-muted">
        <template v-if="copiedAccount">
          Đã sao chép số tài khoản người nhận
          <strong class="font-mono">{{ copiedAccount }}</strong
          >, dán vào app để chuyển.
        </template>
        <template v-else-if="handoff.autofill">
          App sẽ điền sẵn người nhận và số tiền.
        </template>
        <template v-else> Nếu app chưa tự mở, bấm nút bên dưới. </template>
      </p>
      <div class="mt-3 flex flex-wrap gap-2">
        <!-- A real link: opening another app needs a fresh tap. -->
        <a
          :href="handoff.link"
          class="btn btn-primary"
          rel="noopener"
          @click="dismissBankHandoff"
          >Mở app {{ handoff.appName }}</a
        >
        <button
          type="button"
          class="btn btn-secondary"
          @click="dismissBankHandoff"
        >
          Để sau
        </button>
      </div>
    </div>
  </section>
</template>
