<?php

namespace App\Domains\ReadingDigest\Presentation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'reading-digest:telegram-webhook {--delete : Remove the webhook instead of registering it}';

    protected $description = 'Register (or delete) the Telegram webhook that handles digest vote callbacks';

    public function handle(): int
    {
        $config = config('reading-digest.telegram');
        $token = $config['bot_token'] ?? null;

        if (! $token) {
            $this->error('DIGEST_TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/deleteWebhook");
            $this->line('deleteWebhook: '.$response->body());

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        }

        $base = rtrim((string) config('reading-digest.public_url', config('app.url')), '/');
        $url = $base.route('reading-digest.telegram.webhook', [], absolute: false);

        $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/setWebhook", array_filter([
            'url' => $url,
            'secret_token' => $config['webhook_secret'] ?? null,
            'allowed_updates' => json_encode(['callback_query']),
        ]));

        $this->line('setWebhook ('.$url.'): '.$response->body());

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}
