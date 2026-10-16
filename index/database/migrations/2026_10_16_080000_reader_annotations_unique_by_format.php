<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow PencilKit drawings and portable markup JSON on the same page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_page_annotations', function (Blueprint $table) {
            $table->dropUnique(['document_id', 'page_index']);
            $table->unique(['document_id', 'page_index', 'format']);
        });
    }

    public function down(): void
    {
        Schema::table('reader_page_annotations', function (Blueprint $table) {
            $table->dropUnique(['document_id', 'page_index', 'format']);
            $table->unique(['document_id', 'page_index']);
        });
    }
};
