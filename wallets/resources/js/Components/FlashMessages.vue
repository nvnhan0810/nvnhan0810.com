<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types/inertia';

const page = usePage<SharedPageProps>();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
const importErrors = computed(() => page.props.flash?.import_errors ?? []);
const errors = computed(() => page.props.errors ?? {});
const errorList = computed(() => Object.values(errors.value).flat());
</script>

<template>
    <div class="space-y-3 mb-4">
        <div v-if="success" class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
            {{ success }}
        </div>
        <div v-if="error" class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ error }}
        </div>
        <div v-if="errorList.length" class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            <ul class="list-disc list-inside space-y-1">
                <li v-for="(msg, i) in errorList" :key="i">{{ msg }}</li>
            </ul>
        </div>
        <div v-if="importErrors.length" class="rounded-md bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
            <p class="font-medium mb-1">Lỗi import:</p>
            <ul class="list-disc list-inside space-y-1">
                <li v-for="(msg, i) in importErrors" :key="i">{{ msg }}</li>
            </ul>
        </div>
    </div>
</template>
