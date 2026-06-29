<?php

namespace App\Domains\ReadingDigest\Infrastructure\Telegram;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramDigestNotifier
{
    public function send(DigestRunModel $run): bool
    {
        $config = config('reading-digest.telegram');

        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        $token = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (! $token || ! $chatId) {
            Log::warning('Digest Telegram not configured');

            return false;
        }

        $run->load(['items.article.source', 'items.subject']);

        $lines = [
            '📚 Daily Reading — '.$run->run_date->format('D, j M'),
            '',
        ];

        $grouped = $run->items->sortBy('rank')->groupBy('subject_id');

        foreach ($grouped as $items) {
            $subject = $items->first()->subject;
            $lines[] = '━━ '.$subject->name.' ━━';

            foreach ($items->values() as $index => $item) {
                $article = $item->article;
                $meta = $article->metadata ?? [];
                $topics = implode(' · ', array_slice($meta['topics'] ?? [], 0, 2));
                $difficulty = ucfirst($meta['difficulty'] ?? 'unknown');
                $readTime = $article->estimated_read_time_minutes ?? '?';

                $lines[] = ($index + 1).'. ('.round($item->llm_score ?? $item->retrieval_score ?? 0).') '.$article->title;
                $lines[] = '   '.$topics.' · '.$difficulty.' · '.$readTime.' min';
                $lines[] = '   '.url('/reading-digest/a/'.$item->tracking_token);
                $lines[] = '';
            }
        }

        $message = implode("\n", $lines);

        $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ]);

        if (! $response->successful()) {
            Log::error('Digest Telegram send failed', ['body' => $response->body()]);

            return false;
        }

        return true;
    }
}
