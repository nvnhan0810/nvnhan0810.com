<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

final class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID keys for Web Push and print .env lines';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Add these to your .env:');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:nguyenvannhan0810@gmail.com');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Then run: php artisan config:clear');

        return self::SUCCESS;
    }
}
