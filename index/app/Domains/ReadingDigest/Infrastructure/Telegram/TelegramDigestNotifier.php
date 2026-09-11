<?php

namespace App\Domains\ReadingDigest\Infrastructure\Telegram;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestSettingsModel;
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

        $run->load(['items']);

        $settings = DigestSettingsModel::query()
            ->where('user_id', $run->user_id)
            ->first();

        $timezone = $settings?->timezone ?? config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');
        $sentAt = now($timezone);
        $datetimeLabel = $sentAt->format('D, j M Y · H:i').' ('.$timezone.')';
        $count = $run->items->count();
        $newsUrl = $this->newsTodayUrl();

        $text = '<b>📚 Daily Reading</b>'."\n"
            .$this->escape($datetimeLabel)."\n\n";

        if ($count === 0) {
            $text .= "⚠️ <i>No articles selected today.</i>\n\n"
                .'Link at least one source to your subject in Admin → Subjects → Edit, then run Fetch &amp; send again.';
        } else {
            $text .= '<i>'.$count.' article(s) ready.</i>'."\n\n"
                .'Open the digest page to read, upvote, and downvote:'."\n"
                .$this->escape($newsUrl);
        }

        $body = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ];

        if ($count > 0 && $this->isTelegramButtonUrl($newsUrl)) {
            $body['reply_markup'] = [
                'inline_keyboard' => [[
                    ['text' => '📰 Open today\'s digest', 'url' => $newsUrl],
                ]],
            ];
        }

        $response = Http::timeout(30)->asJson()->post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            $body,
        );

        if (! $response->successful()) {
            Log::error('Digest Telegram send failed', ['body' => $response->body()]);

            return false;
        }

        return true;
    }

    private function newsTodayUrl(): string
    {
        $base = rtrim((string) config('reading-digest.public_url', config('app.url')), '/');
        $path = route('news.today', absolute: false);

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    private function isTelegramButtonUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        return ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)
            && ! str_ends_with($host, '.local');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
