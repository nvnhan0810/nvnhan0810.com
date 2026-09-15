<?php

namespace Modules\Sso\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sso\Application\Handler\ExchangeAuthorizationCodeHandler;

final class SsoTokenController extends Controller
{
    public function __construct(
        private readonly ExchangeAuthorizationCodeHandler $exchange,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grant_type' => ['required', 'string', 'in:authorization_code'],
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:256'],
            'code' => ['required', 'string', 'max:128'],
            'redirect_uri' => ['required', 'string', 'max:2048'],
        ]);

        $claims = $this->exchange->handle(
            $data['client_id'],
            $data['client_secret'],
            $data['code'],
            $data['redirect_uri'],
        );

        return response()->json($claims->toArray());
    }
}
