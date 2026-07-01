<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill: older Vietnamese RSS articles were stored with un-decoded HTML
     * entities (e.g. `c&oacute;` instead of `có`) because feeds double-encode
     * content. New fetches are cleaned in RssSourceAdapter::cleanText(); this
     * repairs rows already persisted.
     */
    private const ENTITY_PATTERN = '&(?:[a-zA-Z][a-zA-Z0-9]+|#[0-9]+|#x[0-9a-fA-F]+);';

    public function up(): void
    {
        if (! Schema::hasTable('rd_articles')) {
            return;
        }

        DB::table('rd_articles')
            ->where(function ($q) {
                foreach (['title', 'summary', 'content_text'] as $column) {
                    $q->orWhere($column, '~', self::ENTITY_PATTERN);
                }
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $update = [];

                    foreach (['title', 'summary', 'content_text'] as $column) {
                        $clean = $this->cleanText($row->{$column} ?? null);

                        if ($clean !== ($row->{$column} ?? null)) {
                            $update[$column] = $clean;
                        }
                    }

                    if ($update !== []) {
                        DB::table('rd_articles')->where('id', $row->id)->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        // Text repair is not reversible.
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/'.self::ENTITY_PATTERN.'/', $value) === 1) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
};
