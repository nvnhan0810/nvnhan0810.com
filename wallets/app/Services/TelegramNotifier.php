<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.telegram.bot_token'));
    }

    public function send(string $chatId, string $text, ?string $buttonUrl = null, ?string $buttonText = null): bool
    {
        $token = config('services.telegram.bot_token');

        if (empty($token) || $chatId === '') {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($buttonUrl) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => [[
                    ['text' => $buttonText ?? 'Mở', 'url' => $buttonUrl],
                ]],
            ]);
        }

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            if (! $response->successful()) {
                Log::warning('Telegram send failed', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram send exception: '.$e->getMessage());

            return false;
        }
    }
}
