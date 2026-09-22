<?php

namespace App\Http\Controllers\Admin\Todo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Todo\Application\SubscribeWebPush;
use Modules\Todo\Application\UnsubscribeWebPush;
use Modules\Todo\Application\UpdateWebPushPresence;
use Modules\Todo\Domain\Ports\WebPushSender;
use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;

class WebPushController extends Controller
{
    public function publicKey(WebPushSender $sender): JsonResponse
    {
        if (! $sender->isConfigured()) {
            return response()->json(['configured' => false, 'publicKey' => null], 503);
        }

        return response()->json([
            'configured' => true,
            'publicKey' => (string) config('web-push.vapid.public_key'),
        ]);
    }

    public function subscribe(Request $request, SubscribeWebPush $subscribe): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $userId = (int) Auth::id();
        $subscribe->execute(
            $userId,
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
            $request->userAgent(),
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request, UnsubscribeWebPush $unsubscribe): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        $unsubscribe->execute((int) Auth::id(), $data['endpoint']);

        return response()->json(['ok' => true]);
    }

    public function presence(Request $request, UpdateWebPushPresence $presence): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
            'focused' => ['required', 'boolean'],
        ]);

        $presence->execute(
            (int) Auth::id(),
            $data['endpoint'],
            $request->boolean('focused'),
        );

        return response()->json(['ok' => true]);
    }

    public function status(WebPushSubscriptionRepository $repository): JsonResponse
    {
        $userId = (int) Auth::id();
        $count = count($repository->listByUserId($userId));

        return response()->json([
            'configured' => filled(config('web-push.vapid.public_key'))
                && filled(config('web-push.vapid.private_key')),
            'subscriptionCount' => $count,
        ]);
    }
}
