<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { todayVi } from '@/domain';

defineProps({
    holidays: { type: Object, required: true },
});

const addForm = useForm({
    date: todayVi(),
    name: '',
    type: 'public',
});

const importForm = useForm({
    file: null,
});

function submitAdd() {
    addForm.post(route('holidays.store'));
}

function submitImport() {
    importForm.post(route('holidays.import'), { forceFormData: true });
}

function onFileChange(e) {
    importForm.file = e.target.files[0] ?? null;
}

function deleteHoliday(h) {
    if (!confirm('Xóa ngày lễ này?')) return;
    router.delete(route('holidays.destroy', h.id));
}

function typeLabel(type) {
    if (type === 'public') return { text: 'Công cộng', class: 'bg-green-100 text-green-800' };
    if (type === 'bank') return { text: 'Ngân hàng', class: 'bg-blue-100 text-blue-800' };
    return { text: 'Tùy chỉnh', class: 'bg-muted text-content-secondary' };
}
</script>

<template>
    <Head title="Ngày lễ" />
    <AppLayout title="Ngày lễ">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-content">Quản lý Ngày Lễ</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-500">Thêm các ngày lễ, nghỉ để tính toán chính xác ngày trả nợ.</p>
        </div>

        <div class="bg-surface shadow sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-content mb-4">Thêm Ngày Lễ Mới</h3>
                <form class="grid grid-cols-1 gap-4 sm:grid-cols-4" @submit.prevent="submitAdd">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Ngày</label>
                        <div class="field-date"><DatePicker v-model="addForm.date" required /></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Tên ngày lễ</label>
                        <input v-model="addForm.name" type="text" required placeholder="Tết Nguyên Đán" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Loại</label>
                        <select v-model="addForm.type" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm">
                            <option value="public">Ngày lễ công cộng</option>
                            <option value="bank">Ngày nghỉ ngân hàng</option>
                            <option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-md bg-primary-600 py-2 px-4 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" :disabled="addForm.processing">Thêm</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-surface shadow sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-content mb-2">Import từ Excel / CSV</h3>
                <p class="text-sm text-content-muted mb-4">
                    File gồm 3 cột: <strong>Ngày</strong> (dd/mm/yyyy), <strong>Tên ngày lễ</strong>, <strong>Loại</strong> (public / bank / custom).
                    Nếu ngày đã có thì <strong>cập nhật tên và loại</strong>; ngày mới sẽ được thêm.
                </p>
                <form class="flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitImport">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-content-secondary">Chọn file (.csv, .xlsx, .xls)</label>
                        <input type="file" accept=".csv,.txt,.xlsx,.xls" required class="mt-1 block w-full text-sm text-content-secondary file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100" @change="onFileChange" />
                        <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-600">{{ importForm.errors.file }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="route('holidays.import.template')" class="inline-flex justify-center rounded-md border border-strong bg-surface py-2 px-4 text-sm font-medium text-content shadow-sm hover:bg-surface-hover">
                            Tải file mẫu (VN 2020–2026)
                        </a>
                        <button type="submit" class="inline-flex justify-center rounded-md bg-primary-600 py-2 px-4 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" :disabled="importForm.processing">Import</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-surface shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-default">
                <li v-for="holiday in holidays.data" :key="holiday.id" class="px-4 py-4 sm:px-6 hover:bg-surface-hover">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center min-w-0 gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-content">{{ holiday.name }}</p>
                                <p class="text-sm text-content-muted">{{ holiday.date }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5" :class="typeLabel(holiday.type).class">{{ typeLabel(holiday.type).text }}</span>
                        </div>
                        <button type="button" class="ml-4 text-red-600 dark:text-red-400 hover:text-red-900 text-sm font-medium" @click="deleteHoliday(holiday)">Xóa</button>
                    </div>
                </li>
                <li v-if="!holidays.data?.length" class="px-4 py-8 text-center text-sm text-content-muted">
                    Chưa có ngày lễ nào. Hãy thêm các ngày lễ Việt Nam để tính toán chính xác.
                </li>
            </ul>
        </div>

        <nav v-if="holidays.links?.length > 3" class="mt-4 flex flex-wrap gap-1 justify-center">
            <Link
                v-for="(link, i) in holidays.links"
                :key="i"
                :href="link.url || '#'"
                class="px-3 py-1 text-sm rounded-md border"
                :class="link.active ? 'bg-primary-600 text-white border-primary-600' : (link.url ? 'bg-surface border-subtle text-content hover:bg-surface-hover' : 'opacity-40 pointer-events-none border-subtle text-content-muted')"
                v-html="link.label"
            />
        </nav>

        <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <h4 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">Gợi ý: Các ngày lễ cố định hàng năm</h4>
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Tết Dương lịch (01/01), Giỗ Tổ Hùng Vương (10/03 AL), Giải phóng miền Nam (30/04),
                Quốc tế Lao động (01/05), Quốc khánh (02/09).
                <strong>Lưu ý:</strong> Tết Nguyên Đán thay đổi hàng năm theo Âm lịch, bạn cần thêm thủ công.
            </p>
        </div>
    </AppLayout>
</template>