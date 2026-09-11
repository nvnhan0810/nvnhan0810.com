<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Application\Handlers\ApplyArticleVoteHandler;
use App\Domains\ReadingDigest\Domain\Enums\InteractionEvent;
use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestRunItemModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handles Telegram callback_query updates for digest vote buttons.
     * callback_data format: `rdv:<u|d>:<tracking_token>`.
     */
    public function handle(Request $request, ApplyArticleVoteHandler $voteHandler): JsonResponse
    {
        $config = config('reading-digest.telegram');
        $secret = $config['webhook_secret'] ?? null;

        if ($secret && ! hash_equals((string) $secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['ok' => false], 403);
        }

        $callback = $request->input('callback_query');
        $token = $config['bot_token'] ?? null;

        // Only vote callbacks are handled; ack everything else.
        if (! is_array($callback)) {
            return response()->json(['ok' => true]);
        }

        $callbackId = $callback['id'] ?? null;
        $data = (string) ($callback['data'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;

        // Restrict to the configured owner chat when it is a numeric id.
        if (isset($config['chat_id']) && is_numeric($config['chat_id']) && (string) $chatId !== (string) $config['chat_id']) {
            $this->answer($token, $callbackId, 'Not allowed.');

            return response()->json(['ok' => true]);
        }

        if (preg_match('/^rdv:([ud]):(.+)$/', $data, $matches) !== 1) {
            $this->answer($token, $callbackId, null);

            return response()->json(['ok' => true]);
        }

        [$direction, $trackingToken] = [$matches[1], $matches[2]];

        $item = DigestRunItemModel::query()
            ->where('tracking_token', $trackingToken)
            ->with(['article', 'subject', 'digestRun'])
            ->first();

        if (! $item || ! $item->digestRun) {
            $this->answer($token, $callbackId, 'Article not found.');

            return response()->json(['ok' => true]);
        }

        $event = $direction === 'u' ? InteractionEvent::Liked : InteractionEvent::Disliked;

        try {
            $voteHandler->handle($item, (int) $item->digestRun->user_id, $event);
        } catch (\Throwable $e) {
            Log::error('Telegram digest vote failed', [
                'error' => $e->getMessage(),
                'tracking_token' => $trackingToken,
            ]);
            $this->answer($token, $callbackId, 'Could not record vote.');

            return response()->json(['ok' => true]);
        }

        $this->answer($token, $callbackId, $direction === 'u' ? '👍 Upvoted — thanks!' : '👎 Downvoted — thanks!');
        $this->markVoted($token, $chatId, $messageId, $trackingToken, $direction);

        return response()->json(['ok' => true]);
    }

    private function answer(?string $token, mixed $callbackId, ?string $text): void
    {
        if (! $token || ! $callbackId) {
            return;
        }

        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", array_filter([
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]));
    }

    private function markVoted(?string $token, mixed $chatId, mixed $messageId, string $trackingToken, string $direction): void
    {
        if (! $token || ! $chatId || ! $messageId) {
            return;
        }

        $rows = [];

        $readUrl = $this->readUrl($trackingToken);
        if ($this->isValidButtonUrl($readUrl)) {
            $rows[] = [['text' => '📖 Read', 'url' => $readUrl]];
        }

        $rows[] = [
            ['text' => $direction === 'u' ? '✅ 👍 Upvoted' : '👍 Upvote', 'callback_data' => 'rdv:u:'.$trackingToken],
            ['text' => $direction === 'd' ? '✅ 👎 Downvoted' : '👎 Downvote', 'callback_data' => 'rdv:d:'.$trackingToken],
        ];

        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/editMessageReplyMarkup", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    private function readUrl(string $trackingToken): string
    {
        $base = rtrim((string) config('reading-digest.public_url', config('app.url')), '/');
        $path = route('reading-digest.article.redirect', ['token' => $trackingToken], absolute: false);

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    private function isValidButtonUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        return ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) && ! str_ends_with($host, '.local');
    }
}
