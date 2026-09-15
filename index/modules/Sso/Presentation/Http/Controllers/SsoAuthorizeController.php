<?php

namespace Modules\Sso\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Sso\Application\DTOs\SsoIntent;
use Modules\Sso\Application\Handler\IssueAuthorizationCodeHandler;
use Modules\Sso\Domain\SsoClientRegistry;
use Modules\Sso\Domain\SsoSessionKey;

final class SsoAuthorizeController extends Controller
{
    public function __construct(
        private readonly SsoClientRegistry $clients,
        private readonly IssueAuthorizationCodeHandler $issueCode,
    ) {}

    public function authorize(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'redirect_uri' => ['required', 'string', 'max:2048'],
            'state' => ['nullable', 'string', 'max:128'],
            'response_type' => ['required', 'string', 'in:code'],
        ]);

        if (! $this->clients->has($data['client_id'])) {
            abort(400, 'Unknown client_id');
        }

        $client = $this->clients->get($data['client_id']);
        if (! $client->acceptsRedirectUri($data['redirect_uri'])) {
            abort(400, 'Invalid redirect_uri');
        }

        $intent = new SsoIntent(
            $data['client_id'],
            $data['redirect_uri'],
            (string) ($data['state'] ?? ''),
        );

        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            $request->session()->put(SsoSessionKey::INTENT, $intent->toSession());

            return redirect()->route('google.login');
        }

        if (! in_array($user->email, config('auth.valid_emails'), true)) {
            abort(403, 'Unauthorized');
        }

        $target = $this->issueCode->buildRedirectUrl($intent, $user);

        return redirect()->away($target);
    }
}
