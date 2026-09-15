<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_custom_schedules', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('loan_id')->constrained()->nullOnDelete();
            $table->date('due_date')->nullable()->after('month_index');
            $table->string('status')->default('pending')->after('note');
            $table->foreignId('payment_id')->nullable()->after('status')->constrained('payments')->nullOnDelete();
            $table->decimal('paid_amount', 15, 2)->nullable()->after('payment_id');
            $table->timestamp('reminded_telegram_at')->nullable()->after('paid_amount');

            $table->index(['user_id', 'status', 'due_date']);
        });

        // Backfill: bảng cũ chỉ dùng cho khoản vay custom, `paid_at` đang mang nghĩa "ngày kế hoạch".
        // Chuyển sang: due_date = paid_at (kế hoạch); kỳ đến hạn <= hôm nay coi như đã trả (backfill).
        $today = now()->toDateString();

        $rows = DB::table('loan_custom_schedules')
            ->join('loans', 'loans.id', '=', 'loan_custom_schedules.loan_id')
            ->select('loan_custom_schedules.id', 'loan_custom_schedules.paid_at', 'loan_custom_schedules.payment', 'loans.user_id')
            ->get();

        foreach ($rows as $row) {
            $due = $row->paid_at;
            $isPast = $due !== null && $due <= $today;

            DB::table('loan_custom_schedules')->where('id', $row->id)->update([
                'user_id' => $row->user_id,
                'due_date' => $due,
                'status' => $isPast ? 'paid' : 'pending',
                'paid_amount' => $isPast ? $row->payment : null,
                'paid_at' => $isPast ? $due : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loan_custom_schedules', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'due_date']);
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['due_date', 'status', 'paid_amount', 'reminded_telegram_at']);
        });
    }
};
