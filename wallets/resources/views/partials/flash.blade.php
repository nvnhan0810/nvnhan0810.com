@if (session('success'))
    <div class="mb-6 rounded-md bg-green-50 dark:bg-green-900/30 p-4">
        <p class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-md bg-red-50 dark:bg-red-900/30 p-4">
        @foreach ($errors->all() as $error)
            <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
        @endforeach
    </div>
@endif
