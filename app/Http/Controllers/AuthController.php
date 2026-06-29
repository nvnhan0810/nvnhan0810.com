<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(Request $request)
    {
        $ggUser = Socialite::driver('google')->stateless()->user();

        if ($ggUser && in_array($ggUser->email, config('auth.valid_emails'))) {
            $user = User::updateOrCreate([
                'email' => $ggUser->email,
            ], [
                'name' => $ggUser->name,
                'avatar' => $ggUser->avatar,
            ]);

            Auth::login($user, true);

            return redirect()->intended(route('admin.index'));
        }

        abort(403, 'Unauthorized');
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->route('home');
    }
}
