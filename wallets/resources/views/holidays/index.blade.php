@extends('layouts.app')

@section('content')
@include('partials.flash')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-content">Quản lý Ngày Lễ</h2>
    <p class="mt-1 text-sm text-gray-600 dark:text-slate-500">Thêm các ngày lễ, nghỉ để tính toán chính xác ngày trả nợ.</p>
</div>

<!-- Add Holiday Form -->
<div class="bg-surface shadow sm:rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium leading-6 text-content mb-4">Thêm Ngày Lễ Mới</h3>
        <form action="{{ route('holidays.store') }}" method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            @csrf
            <div>
                <label for="date" class="block text-sm font-medium text-content-secondary">Ngày</label>
                <input type="text" name="date" id="date" required class="datepicker mt-1 block w-full rounded-md border-strong bg-surface text-content shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm border p-2" placeholder="dd/mm/yyyy">
            </div>
            <div>
                <label for="name" class="block text-sm font-medium text-content-secondary">Tên ngày lễ</label>
                <input type="text" name="name" id="name" required placeholder="Tết Nguyên Đán" class="mt-1 block w-full rounded-md border-strong bg-surface text-content shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm border p-2">
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-content-secondary">Loại</label>
                <select name="type" id="type" class="mt-1 block w-full rounded-md border-strong bg-surface text-content shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm border p-2">
                    <option value="public">Ngày lễ công cộng</option>
                    <option value="bank">Ngày nghỉ ngân hàng</option>
                    <option value="custom">Tùy chỉnh</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 dark:hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Thêm
                </button>
            </div>
        </form>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 dark:bg-red-900/30 p-4">
                <div class="text-sm text-red-700 dark:text-red-300">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Import Holidays -->
<div class="bg-surface shadow sm:rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium leading-6 text-content mb-2">Import từ Excel / CSV</h3>
        <p class="text-sm text-content-muted mb-4">
            File gồm 3 cột: <strong>Ngày</strong> (dd/mm/yyyy), <strong>Tên ngày lễ</strong>, <strong>Loại</strong> (public / bank / custom — có thể để trống).
            Nếu ngày đã có trong hệ thống thì <strong>cập nhật tên và loại</strong>; ngày mới sẽ được thêm.
        </p>
        <form action="{{ route('holidays.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="file" class="block text-sm font-medium text-content-secondary">Chọn file (.csv, .xlsx, .xls)</label>
                <input type="file" name="file" id="file" accept=".csv,.txt,.xlsx,.xls" required class="mt-1 block w-full text-sm text-content-secondary file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100">
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('holidays.import.template') }}" class="inline-flex justify-center rounded-md border border-strong bg-surface py-2 px-4 text-sm font-medium text-content shadow-sm hover:bg-surface-hover">
                    Tải file mẫu (VN 2020–2026)
                </a>
                <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 dark:hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Import
                </button>
            </div>
        </form>

        @if (session('import_errors') && count(session('import_errors')) > 0)
            <div class="mt-4 rounded-md bg-amber-50 dark:bg-amber-900/30 p-4">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-2">Chi tiết lỗi / cảnh báo:</p>
                <ul class="list-disc list-inside text-sm text-amber-700 dark:text-amber-300 space-y-1">
                    @foreach (session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

<!-- Holidays List -->
<div class="bg-surface shadow overflow-hidden sm:rounded-md">
    <ul role="list" class="divide-y divide-default">
        @forelse($holidays as $holiday)
        <li class="px-4 py-4 sm:px-6 hover:bg-surface-hover">
            <div class="flex items-center justify-between">
                <div class="flex items-center min-w-0 gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-content">{{ $holiday->name }}</p>
                        <p class="text-sm text-content-muted">{{ $holiday->date->format('d/m/Y - l') }}</p>
                    </div>
                    <div>
                        @if($holiday->type === 'public')
                            <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Công cộng</span>
                        @elseif($holiday->type === 'bank')
                            <span class="inline-flex rounded-full bg-blue-100 px-2 text-xs font-semibold leading-5 text-blue-800">Ngân hàng</span>
                        @else
                            <span class="inline-flex rounded-full bg-muted px-2 text-xs font-semibold leading-5 text-content-secondary">Tùy chỉnh</span>
                        @endif
                    </div>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <form action="{{ route('holidays.destroy', $holiday) }}" method="POST" onsubmit="return confirm('Xóa ngày lễ này?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 dark:text-red-200 text-sm font-medium">Xóa</button>
                    </form>
                </div>
            </div>
        </li>
        @empty
        <li class="px-4 py-8 text-center text-sm text-content-muted">
            Chưa có ngày lễ nào. Hãy thêm các ngày lễ Việt Nam để tính toán chính xác.
        </li>
        @endforelse
    </ul>
</div>

@if($holidays->hasPages())
<div class="mt-4">
    {{ $holidays->links() }}
</div>
@endif

<!-- Quick Add Common Holidays -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <h4 class="text-sm font-medium text-blue-900 mb-2">💡 Gợi ý: Các ngày lễ cố định hàng năm</h4>
    <p class="text-sm text-blue-700">
        Tết Dương lịch (01/01), Giỗ Tổ Hùng Vương (10/03 AL), Giải phóng miền Nam (30/04),
        Quốc tế Lao động (01/05), Quốc khánh (02/09). <br>
        <strong>Lưu ý:</strong> Tết Nguyên Đán thay đổi hàng năm theo Âm lịch, bạn cần thêm thủ công.
    </p>
</div>
@endsection

