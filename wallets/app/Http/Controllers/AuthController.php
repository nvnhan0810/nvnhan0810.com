<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Wallets\Identity\Application\Query\IsEmailAllowed;
use Wallets\Shared\Application\QueryBus;

class AuthController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function redirectGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl($this->googleRedirectUrl())
            ->redirect();
    }

    public function callbackGoogle(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->googleRedirectUrl())
                ->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->with('error', 'Đăng nhập Google thất bại.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect()->route('login')
                ->with('error', 'Tài khoản Google không có email.');
        }

        if (! $this->queries->ask(new IsEmailAllowed($email))) {
            return redirect()->route('login')
                ->with('error', 'Email này chưa được cấp quyền. Liên hệ admin để thêm vào allowlist.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
            ]
        );

        if ($googleUser->getId()) {
            $user->google_id = $googleUser->getId();
            $user->save();
        }

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

    private function googleRedirectUrl(): string
    {
        return (string) config('services.google.redirect');
    }
}
