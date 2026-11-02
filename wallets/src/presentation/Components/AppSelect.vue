<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

export type AppSelectOption = {
  value: string | number
  label: string
}

const model = defineModel<string | number | null>({ default: '' })

const props = withDefaults(
  defineProps<{
    options: AppSelectOption[]
    placeholder?: string
    searchPlaceholder?: string
    emptyText?: string
    searchable?: boolean
    disabled?: boolean
    required?: boolean
    id?: string
    name?: string
    size?: 'md' | 'sm'
  }>(),
  {
    placeholder: 'Chọn...',
    searchPlaceholder: 'Tìm kiếm...',
    emptyText: 'Không có lựa chọn.',
    searchable: true,
    disabled: false,
    required: false,
    size: 'md',
  },
)

const emit = defineEmits<{
  change: [value: string | number | null]
}>()

const open = ref(false)
const query = ref('')
const rootEl = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const listEl = ref<HTMLElement | null>(null)
const activeIndex = ref(-1)

const selected = computed((): AppSelectOption | undefined =>
  props.options.find((opt) => String(opt.value) === String(model.value ?? '')),
)

const filtered = computed((): AppSelectOption[] => {
  const q = query.value.trim().toLowerCase()
  if (!props.searchable || q === '') {
    return props.options
  }
  return props.options.filter((opt) => {
    const label = opt.label.toLowerCase()
    const value = String(opt.value).toLowerCase()
    return label.includes(q) || value.includes(q)
  })
})

const triggerClass = computed((): string => {
  const base =
    'w-full inline-flex items-center justify-between gap-2 rounded-md border border-strong bg-surface text-content text-left shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed'
  return props.size === 'sm'
    ? `${base} px-2.5 py-1.5 text-sm`
    : `${base} px-3 py-2 text-sm`
})

watch(open, async (isOpen) => {
  if (!isOpen) {
    query.value = ''
    activeIndex.value = -1
    return
  }
  const idx = filtered.value.findIndex(
    (opt) => String(opt.value) === String(model.value ?? ''),
  )
  activeIndex.value = idx >= 0 ? idx : 0
  await nextTick()
  if (props.searchable) {
    searchInput.value?.focus()
  }
})

watch(filtered, () => {
  if (activeIndex.value >= filtered.value.length) {
    activeIndex.value = filtered.value.length > 0 ? 0 : -1
  }
})

function optionKey(opt: AppSelectOption): string {
  return opt.value === '' || opt.value === null ? '__empty' : String(opt.value)
}

function isSelected(opt: AppSelectOption): boolean {
  return String(opt.value) === String(model.value ?? '')
}

function toggle(): void {
  if (props.disabled) {
    return
  }
  open.value = !open.value
}

function close(): void {
  open.value = false
}

function selectOption(opt: AppSelectOption): void {
  model.value = opt.value
  emit('change', opt.value)
  close()
}

function onDocumentPointer(event: MouseEvent): void {
  if (!open.value || !rootEl.value) {
    return
  }
  const target = event.target as Node | null
  if (target && !rootEl.value.contains(target)) {
    close()
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (!open.value) {
    if (
      event.key === 'ArrowDown' ||
      event.key === 'Enter' ||
      event.key === ' '
    ) {
      event.preventDefault()
      open.value = true
    }
    return
  }

  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (filtered.value.length === 0) {
      return
    }
    activeIndex.value = (activeIndex.value + 1) % filtered.value.length
    scrollActiveIntoView()
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (filtered.value.length === 0) {
      return
    }
    activeIndex.value =
      activeIndex.value <= 0 ? filtered.value.length - 1 : activeIndex.value - 1
    scrollActiveIntoView()
    return
  }

  if (event.key === 'Enter') {
    event.preventDefault()
    const opt = filtered.value[activeIndex.value]
    if (opt) {
      selectOption(opt)
    }
  }
}

function scrollActiveIntoView(): void {
  nextTick(() => {
    const active = listEl.value?.querySelector<HTMLElement>(
      '[data-active="true"]',
    )
    active?.scrollIntoView({ block: 'nearest' })
  })
}

onMounted(() => {
  document.addEventListener('mousedown', onDocumentPointer)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentPointer)
})
</script>

<template>
  <div ref="rootEl" class="relative" @keydown="onKeydown">
    <button
      :id="id"
      type="button"
      role="combobox"
      :aria-expanded="open"
      :aria-controls="open ? `${id ?? 'app-select'}-list` : undefined"
      :disabled="disabled"
      :class="triggerClass"
      @click="toggle"
    >
      <span
        class="truncate"
        :class="selected ? 'text-content' : 'text-content-muted'"
      >
        {{ selected?.label ?? placeholder }}
      </span>
      <svg
        class="w-4 h-4 shrink-0 text-content-muted opacity-70"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M8 9l4-4 4 4M8 15l4 4 4-4"
        />
      </svg>
    </button>

    <!-- Native fallback for form required validation -->
    <input
      v-if="required"
      type="text"
      tabindex="-1"
      aria-hidden="true"
      class="sr-only"
      :name="name"
      :value="model === null || model === undefined ? '' : String(model)"
      required
      @focus="open = true"
    />

    <div
      v-if="open"
      :id="`${id ?? 'app-select'}-list`"
      class="absolute z-[80] mt-1 w-full rounded-md border border-default bg-surface shadow-lg overflow-hidden"
      role="listbox"
    >
      <div v-if="searchable" class="p-2 border-b border-subtle">
        <input
          ref="searchInput"
          v-model="query"
          type="text"
          class="w-full rounded-md border border-strong bg-surface text-content px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          :placeholder="searchPlaceholder"
          @keydown.stop="onKeydown"
        />
      </div>

      <div ref="listEl" class="max-h-60 overflow-y-auto py-1">
        <button
          v-for="(opt, index) in filtered"
          :key="optionKey(opt)"
          type="button"
          role="option"
          class="w-full flex items-center gap-2 px-2.5 py-2 text-sm text-left hover:bg-surface-hover"
          :class="
            index === activeIndex ? 'bg-primary-50 dark:bg-primary-900/30' : ''
          "
          :aria-selected="isSelected(opt)"
          :data-active="index === activeIndex ? 'true' : 'false'"
          @mouseenter="activeIndex = index"
          @click="selectOption(opt)"
        >
          <svg
            class="w-4 h-4 shrink-0"
            :class="
              isSelected(opt)
                ? 'opacity-100 text-primary-600 dark:text-primary-400'
                : 'opacity-0'
            "
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          <span class="truncate text-content">{{ opt.label }}</span>
        </button>

        <p
          v-if="!filtered.length"
          class="px-3 py-6 text-sm text-content-muted text-center"
        >
          {{ emptyText }}
        </p>
      </div>
    </div>
  </div>
</template>
