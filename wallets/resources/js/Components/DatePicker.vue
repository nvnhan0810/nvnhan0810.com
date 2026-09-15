<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import type { Instance as FlatpickrInstance } from 'flatpickr/dist/types/instance';
import 'flatpickr/dist/flatpickr.min.css';

const model = defineModel<string>({ default: '' });

withDefaults(
    defineProps<{
        name?: string;
        required?: boolean;
        id?: string;
    }>(),
    { required: false },
);

const input = ref<HTMLInputElement | null>(null);
let fp: FlatpickrInstance | null = null;

onMounted((): void => {
    if (!input.value) {
        return;
    }

    fp = flatpickr(input.value, {
        dateFormat: 'd/m/Y',
        allowInput: true,
        defaultDate: model.value || undefined,
        locale: {
            firstDayOfWeek: 1,
            weekdays: {
                shorthand: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
                longhand: ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'],
            },
            months: {
                shorthand: ['Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6', 'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'],
                longhand: [
                    'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
                    'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12',
                ],
            },
        },
        onChange(_selected, dateStr): void {
            model.value = dateStr;
        },
    });
});

watch(model, (v) => {
    if (fp && v && fp.input.value !== v) {
        fp.setDate(v, false);
    }
});

onBeforeUnmount((): void => {
    fp?.destroy();
});

function onNativeInput(e: Event): void {
    const target = e.target as HTMLInputElement;
    model.value = target.value;
}
</script>

<template>
    <input
        ref="input"
        type="text"
        class="datepicker"
        :id="id"
        :name="name"
        :required="required"
        :value="model"
        @input="onNativeInput"
    />
</template>

<style>
.flatpickr-calendar { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); border-radius: 0.5rem; border: 1px solid rgb(229 231 235); }
.flatpickr-day.selected { background: rgb(79 70 229); border-color: rgb(79 70 229); }
.flatpickr-day.today { border-color: rgb(79 70 229); }
.dark .flatpickr-calendar { background: #1f2937; border-color: #374151; }
.dark .flatpickr-day { color: #f3f4f6; }
.dark .flatpickr-day.selected { background: #6366f1; border-color: #6366f1; color: #fff; }
.dark .flatpickr-day.today { border-color: #6366f1; }
.dark .flatpickr-month, .dark .flatpickr-current-month, .dark .flatpickr-weekday { color: #f3f4f6; fill: #f3f4f6; }
</style>
