<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rd_digest_run_items')) {
            return;
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('rd_digest_run_items', 'telegram_message_id') ? 'telegram_message_id' : null,
            Schema::hasColumn('rd_digest_run_items', 'telegram_read_url') ? 'telegram_read_url' : null,
            Schema::hasColumn('rd_digest_run_items', 'telegram_sent_at') ? 'telegram_sent_at' : null,
        ]));

        if ($drop === []) {
            return;
        }

        Schema::table('rd_digest_run_items', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rd_digest_run_items')) {
            return;
        }

        Schema::table('rd_digest_run_items', function (Blueprint $table) {
            if (! Schema::hasColumn('rd_digest_run_items', 'telegram_message_id')) {
                $table->unsignedBigInteger('telegram_message_id')->nullable()->after('tracking_token');
            }
            if (! Schema::hasColumn('rd_digest_run_items', 'telegram_read_url')) {
                $table->string('telegram_read_url', 512)->nullable()->after('telegram_message_id');
            }
            if (! Schema::hasColumn('rd_digest_run_items', 'telegram_sent_at')) {
                $table->timestamp('telegram_sent_at')->nullable()->after('telegram_read_url');
            }
        });
    }
};
