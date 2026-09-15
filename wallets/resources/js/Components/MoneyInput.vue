<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { parseMoney } from '@/domain/money/money';

const model = defineModel<number | string>({ default: '' });

withDefaults(
    defineProps<{
        name?: string;
        required?: boolean;
        readonly?: boolean;
        placeholder?: string;
        id?: string;
    }>(),
    {
        required: false,
        readonly: false,
        placeholder: '0',
    },
);

const inputEl = ref<HTMLInputElement | null>(null);
const focused = ref(false);

function formatDisplay(value: unknown): string {
    const n = Math.round(Number(value) || 0);
    if (!n) {
        return '';
    }
    return new Intl.NumberFormat('vi-VN').format(n);
}

function syncFromModel(): void {
    if (!inputEl.value || focused.value) {
        return;
    }
    inputEl.value.value = formatDisplay(model.value);
}

onMounted((): void => {
    syncFromModel();
});

watch(model, (): void => {
    syncFromModel();
});

function onFocus(): void {
    focused.value = true;
}

function onInput(e: Event): void {
    const target = e.target as HTMLInputElement;
    model.value = parseMoney(target.value);
}

function onBlur(e: Event): void {
    focused.value = false;
    const target = e.target as HTMLInputElement;
    const raw = parseMoney(target.value);
    model.value = raw;
    target.value = formatDisplay(raw);
}
</script>

<template>
    <input
        ref="inputEl"
        type="text"
        inputmode="numeric"
        class="money-input"
        :id="id"
        :name="name"
        :required="required"
        :readonly="readonly"
        :placeholder="placeholder"
        @focus="onFocus"
        @input="onInput"
        @blur="onBlur"
    />
</template>
