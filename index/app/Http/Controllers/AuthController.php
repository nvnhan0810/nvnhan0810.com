<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Modules\Sso\Application\DTOs\SsoIntent;
use Modules\Sso\Application\Handler\IssueAuthorizationCodeHandler;
use Modules\Sso\Domain\SsoSessionKey;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(
        Request $request,
        IssueAuthorizationCodeHandler $issueCode,
    ) {
        $ggUser = Socialite::driver('google')->stateless()->user();

        if (! $ggUser || ! in_array($ggUser->email, config('auth.valid_emails'), true)) {
            abort(403, 'Unauthorized');
        }

        $user = User::updateOrCreate([
            'email' => $ggUser->email,
        ], [
            'name' => $ggUser->name,
            'avatar' => $ggUser->avatar,
        ]);

        Auth::login($user, true);

        /** @var array<string, mixed>|null $intentData */
        $intentData = $request->session()->pull(SsoSessionKey::INTENT);
        $intent = is_array($intentData) ? SsoIntent::fromSession($intentData) : null;

        if ($intent !== null) {
            $target = $issueCode->buildRedirectUrl($intent, $user);

            return redirect()->away($target);
        }

        return redirect()->intended(route('admin.index'));
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->route('home');
    }
}
