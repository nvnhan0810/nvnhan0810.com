<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@/application/navigation'
import { pathOf } from '@/application/app-navigation'
import {
  dismissInstall,
  promptInstall,
  pwaInstall,
} from '@/application/pwa-install'

const props = withDefaults(
  defineProps<{
    /** `banner` shows on the dashboard until dismissed; `panel` always shows. */
    variant?: 'banner' | 'panel'
  }>(),
  { variant: 'banner' },
)

const page = usePage()
const visible = computed(() => {
  if (props.variant === 'panel') return true
  return (
    pathOf(page.url) === '/' &&
    !pwaInstall.dismissed &&
    (pwaInstall.mode === 'prompt' || pwaInstall.mode === 'ios')
  )
})
</script>

<template>
  <div
    v-if="visible"
    class="card flex items-start gap-3 p-4"
    :class="variant === 'banner' ? 'mb-4' : ''"
  >
    <img
      src="/icons/icon-192.png"
      alt=""
      class="w-11 h-11 rounded-xl shrink-0"
    />
    <div class="min-w-0 flex-1">
      <template v-if="pwaInstall.mode === 'installed'">
        <p class="font-semibold text-content">Đã cài ứng dụng</p>
        <p class="text-sm text-content-muted">
          MyWallet đang chạy như một ứng dụng trên thiết bị này.
        </p>
      </template>
      <template v-else-if="pwaInstall.mode === 'ios'">
        <p class="font-semibold text-content">Cài MyWallet lên iPhone</p>
        <p class="text-sm text-content-muted">
          Nhấn nút <strong>Chia sẻ</strong>
          <svg
            class="inline w-4 h-4 -mt-0.5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 15V3m0 0L8 7m4-4 4 4M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"
            />
          </svg>
          của Safari rồi chọn <strong>Thêm vào MH chính</strong>.
        </p>
      </template>
      <template v-else-if="pwaInstall.mode === 'prompt'">
        <p class="font-semibold text-content">Cài MyWallet như ứng dụng</p>
        <p class="text-sm text-content-muted">
          Mở nhanh từ màn hình chính, toàn màn hình, không cần trình duyệt.
        </p>
        <button
          type="button"
          class="btn btn-primary mt-3"
          @click="promptInstall"
        >
          Cài đặt
        </button>
      </template>
      <template v-else>
        <p class="font-semibold text-content">Cài như ứng dụng</p>
        <p class="text-sm text-content-muted">
          Mở trang này bằng Chrome (Android) hoặc Safari (iPhone), rồi chọn “Cài
          đặt ứng dụng” / “Thêm vào màn hình chính” trong menu trình duyệt.
        </p>
      </template>
    </div>
    <button
      v-if="variant === 'banner'"
      type="button"
      class="icon-btn -mr-2 -mt-2"
      aria-label="Để sau"
      title="Để sau"
      @click="dismissInstall"
    >
      <svg
        class="w-5 h-5"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
      </svg>
    </button>
  </div>
</template>
