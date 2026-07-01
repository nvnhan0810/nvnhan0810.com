<?php

namespace App\Domains\ReadingDigest\Infrastructure\Telegram;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunModel;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestSettingsModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramDigestNotifier
{
    private static bool $plainUrlWarningLogged = false;
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

        $settings = DigestSettingsModel::query()
            ->where('user_id', $run->user_id)
            ->first();

        $timezone = $settings?->timezone ?? config('reading-digest.timezone', 'Asia/Ho_Chi_Minh');
        $sentAt = now($timezone);
        $datetimeLabel = $sentAt->format('D, j M Y · H:i').' ('.$timezone.')';

        foreach ($this->buildMessagePayloads($run, $datetimeLabel) as $payload) {
            $body = [
                'chat_id' => $chatId,
                'text' => $payload['text'],
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if (isset($payload['reply_markup'])) {
                $body['reply_markup'] = $payload['reply_markup'];
            }

            $response = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendMessage", $body);

            if (! $response->successful()) {
                Log::error('Digest Telegram send failed', ['body' => $response->body()]);

                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{text: string, reply_markup?: array<string, mixed>}>
     */
    private function buildMessagePayloads(DigestRunModel $run, string $datetimeLabel): array
    {
        $items = $run->items->sortBy('rank');

        if ($items->isEmpty()) {
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

        return [
            'text' => $text.$this->linkSuffix($readUrl),
            ...$this->voteMarkup($item->tracking_token, $readUrl),
        ];
    }

    /**
     * Read stays a URL button; the vote is split into two callback buttons so
     * the click is handled inline by the Telegram webhook (no browser needed).
     *
     * @return array<string, mixed>
     */
    private function voteMarkup(string $token, string $readUrl): array
    {
        $rows = [];

        if ($this->isTelegramButtonUrl($readUrl)) {
            $rows[] = [['text' => '📖 Read', 'url' => $readUrl]];
        } elseif (! self::$plainUrlWarningLogged) {
            self::$plainUrlWarningLogged = true;
            Log::warning('Digest Telegram using plain URL for Read link because public URL is not valid for inline buttons', [
                'read_url' => $readUrl,
                'hint' => 'Set DIGEST_PUBLIC_URL to your public HTTPS domain (Telegram rejects localhost).',
            ]);
        }

        $rows[] = [
            ['text' => '👍 Upvote', 'callback_data' => 'rdv:u:'.$token],
            ['text' => '👎 Downvote', 'callback_data' => 'rdv:d:'.$token],
        ];

        return ['reply_markup' => ['inline_keyboard' => $rows]];
    }

    private function linkSuffix(string $readUrl): string
    {
        if ($this->isTelegramButtonUrl($readUrl)) {
            return '';
        }

        return "\n\n📖 Read:\n".$this->escape($readUrl);
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
