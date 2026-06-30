<?php

namespace App\Domains\ReadingDigest\Infrastructure\Telegram;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestSettingsModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramDigestNotifier
{
    private static bool $plainUrlWarningLogged = false;

    public function send(DigestRunModel $run): bool
    {
        Log::warning('[ReadingDigest] TelegramDigestNotifier: started', [
            'digest_run_id' => $run->id,
            'user_id' => $run->user_id,
        ]);

        try {
            $config = config('reading-digest.telegram');

            Log::warning('[ReadingDigest] TelegramDigestNotifier: config loaded', [
                'digest_run_id' => $run->id,
                'enabled' => $config['enabled'] ?? false,
                'bot_token_set' => ! empty($config['bot_token'] ?? null),
                'chat_id_set' => ! empty($config['chat_id'] ?? null),
                'public_url' => config('reading-digest.public_url'),
                'app_url' => config('app.url'),
            ]);

            if (! ($config['enabled'] ?? false)) {
                Log::warning('[ReadingDigest] TelegramDigestNotifier: disabled (DIGEST_TELEGRAM_ENABLED=false)', [
                    'digest_run_id' => $run->id,
                ]);

                return false;
            }

            $token = $config['bot_token'] ?? null;
            $chatId = $config['chat_id'] ?? null;

            if (! $token || ! $chatId) {
                Log::warning('[ReadingDigest] TelegramDigestNotifier: not configured (missing bot_token or chat_id)', [
                    'digest_run_id' => $run->id,
                    'bot_token_set' => (bool) $token,
                    'chat_id_set' => (bool) $chatId,
                ]);

                return false;
            }

            $run->load(['items.article.source', 'items.subject']);

            Log::warning('[ReadingDigest] TelegramDigestNotifier: run items loaded', [
                'digest_run_id' => $run->id,
                'items_count' => $run->items->count(),
            ]);

            $settings = DigestSettingsModel::query()
                ->where('user_id', $run->user_id)
                ->first();

            $timezone = $settings?->timezone ?? config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');
            $sentAt = now($timezone);
            $datetimeLabel = $sentAt->format('D, j M Y · H:i').' ('.$timezone.')';

            $payloads = $this->buildMessagePayloads($run, $datetimeLabel);

            Log::warning('[ReadingDigest] TelegramDigestNotifier: message payloads built', [
                'digest_run_id' => $run->id,
                'payloads_count' => count($payloads),
            ]);

            foreach ($payloads as $index => $payload) {
                $body = [
                    'chat_id' => $chatId,
                    'text' => $payload['text'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ];

                if (isset($payload['reply_markup'])) {
                    $body['reply_markup'] = $payload['reply_markup'];
                }

                Log::warning('[ReadingDigest] TelegramDigestNotifier: sending message', [
                    'digest_run_id' => $run->id,
                    'message_index' => $index,
                    'text_length' => mb_strlen($payload['text']),
                    'has_reply_markup' => isset($payload['reply_markup']),
                ]);

                try {
                    $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", $body);

                    Log::warning('[ReadingDigest] TelegramDigestNotifier: API response', [
                        'digest_run_id' => $run->id,
                        'message_index' => $index,
                        'status' => $response->status(),
                        'successful' => $response->successful(),
                        'body_preview' => mb_substr($response->body(), 0, 500),
                    ]);

                    if (! $response->successful()) {
                        Log::warning('[ReadingDigest] TelegramDigestNotifier: send failed', [
                            'digest_run_id' => $run->id,
                            'message_index' => $index,
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        return false;
                    }
                } catch (Throwable $e) {
                    Log::warning('[ReadingDigest] TelegramDigestNotifier: HTTP request failed', [
                        'digest_run_id' => $run->id,
                        'message_index' => $index,
                        'error' => $e->getMessage(),
                        'exception' => $e::class,
                    ]);

                    return false;
                }
            }

            Log::warning('[ReadingDigest] TelegramDigestNotifier: all messages sent successfully', [
                'digest_run_id' => $run->id,
                'messages_sent' => count($payloads),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('[ReadingDigest] TelegramDigestNotifier: unexpected error', [
                'digest_run_id' => $run->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * @return list<array{text: string, reply_markup?: array<string, mixed>}>
     */
    private function buildMessagePayloads(DigestRunModel $run, string $datetimeLabel): array
    {
        $items = $run->items->sortBy('rank');

        if ($items->isEmpty()) {
            Log::warning('[ReadingDigest] TelegramDigestNotifier: no items — sending empty digest notice', [
                'digest_run_id' => $run->id,
            ]);

            return [[
                'text' => $this->header($datetimeLabel)
                    ."\n\n⚠️ <i>No articles selected today.</i>"
                    ."\n\nLink at least one source to your subject in Admin → Subjects → Edit, then run Fetch &amp; send again.",
            ]];
        }

        $payloads = [[
            'text' => $this->header($datetimeLabel)
                ."\n\n<i>{$items->count()} article(s) below — one message each.</i>",
        ]];

        foreach ($items as $item) {
            $payload = $this->buildArticlePayload($item);
            if ($payload !== null) {
                $payloads[] = $payload;
            } else {
                Log::warning('[ReadingDigest] TelegramDigestNotifier: skipped item (missing article or tracking_token)', [
                    'digest_run_id' => $run->id,
                    'item_id' => $item->id,
                    'article_id' => $item->article_id,
                    'has_article' => (bool) $item->article,
                    'has_tracking_token' => (bool) $item->tracking_token,
                ]);
            }
        }

        return $payloads;
    }

    /**
     * @return array{text: string, reply_markup: array<string, mixed>}|null
     */
    private function buildArticlePayload(DigestRunItemModel $item): ?array
    {
        $article = $item->article;
        if (! $article || ! $item->tracking_token) {
            return null;
        }

        $subjectName = $this->escape($item->subject?->name ?? 'Subject');
        $title = $this->escape($article->title);
        $sourceName = $this->escape($article->source?->name ?? '');

        $text = "<b>{$title}</b>\n";
        $text .= "<i>{$subjectName}</i>";
        if ($sourceName !== '') {
            $text .= " · {$sourceName}";
        }

        if ($article->summary) {
            $text .= "\n\n".$this->escape(mb_substr($article->summary, 0, 280));
            if (mb_strlen($article->summary) > 280) {
                $text .= '…';
            }
        }

        $readUrl = $this->readUrl($item->tracking_token);
        $voteUrl = $this->voteUrl($item->tracking_token);

        return [
            'text' => $text.$this->linkSuffix($readUrl, $voteUrl),
            ...$this->linkMarkup($readUrl, $voteUrl),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linkMarkup(string $readUrl, string $voteUrl): array
    {
        if (! $this->isTelegramButtonUrl($readUrl) || ! $this->isTelegramButtonUrl($voteUrl)) {
            if (! self::$plainUrlWarningLogged) {
                self::$plainUrlWarningLogged = true;
                Log::warning('[ReadingDigest] TelegramDigestNotifier: using plain URL links (public URL invalid for inline buttons)', [
                    'read_url' => $readUrl,
                    'vote_url' => $voteUrl,
                    'hint' => 'Set DIGEST_PUBLIC_URL to your public HTTPS domain (Telegram rejects localhost).',
                ]);
            }

            return [];
        }

        return [
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '📖 Read', 'url' => $readUrl],
                    ['text' => '👍 Vote', 'url' => $voteUrl],
                ]],
            ],
        ];
    }

    private function linkSuffix(string $readUrl, string $voteUrl): string
    {
        if ($this->isTelegramButtonUrl($readUrl)) {
            return '';
        }

        return "\n\n📖 Read:\n".$this->escape($readUrl)
            ."\n\n👍 Vote:\n".$this->escape($voteUrl);
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

    private function readUrl(string $token): string
    {
        return $this->absoluteRoute('reading-digest.article.redirect', ['token' => $token]);
    }

    private function voteUrl(string $token): string
    {
        return $this->absoluteRoute('reading-digest.article.vote', ['token' => $token]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function absoluteRoute(string $name, array $parameters): string
    {
        $base = rtrim((string) config('reading-digest.public_url', config('app.url')), '/');
        $path = route($name, $parameters, absolute: false);

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    private function header(string $datetimeLabel): string
    {
        return '<b>📚 Daily Reading</b>'."\n".$this->escape($datetimeLabel);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
