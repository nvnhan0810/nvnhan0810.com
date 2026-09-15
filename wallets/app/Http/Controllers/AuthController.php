<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Wallets\Identity\Application\Query\IsEmailAllowed;
use Wallets\Identity\Infrastructure\Sso\IndexSsoClient;
use Wallets\Shared\Application\QueryBus;

class AuthController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly IndexSsoClient $sso,
    ) {}

    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function redirectSso(): RedirectResponse
    {
        return redirect()->away($this->sso->authorizeUrl());
    }

    public function callbackSso(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($code === '' || ! $this->sso->validateState($state)) {
            return redirect()->route('login')
                ->with('error', 'Phiên đăng nhập SSO không hợp lệ.');
        }

        try {
            $claims = $this->sso->exchangeAuthorizationCode($code);
        } catch (\Throwable) {
            return redirect()->route('login')
                ->with('error', 'Đăng nhập SSO thất bại.');
        }

        $email = $claims['email'];

        if (! $this->queries->ask(new IsEmailAllowed($email))) {
            return redirect()->route('login')
                ->with('error', 'Email này chưa được cấp quyền. Liên hệ admin để thêm vào allowlist.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $claims['name'] ?: Str::before($email, '@'),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Đã đăng xuất.');
    }
}
