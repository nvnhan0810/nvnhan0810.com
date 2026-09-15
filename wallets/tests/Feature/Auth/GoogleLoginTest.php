<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    #[Test]
    public function google_auth_entry_redirects_to_provider(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    #[Test]
    public function allowlisted_google_user_can_sign_in(): void
    {
        config(['wallets.allowed_emails' => ['allowed@example.com'], 'wallets.allow_all_emails' => false]);

        $this->mockGoogleUser('allowed@example.com', 'Allowed User', 'google-1');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'allowed@example.com',
            'google_id' => 'google-1',
        ]);
    }

    #[Test]
    public function non_allowlisted_google_user_is_denied(): void
    {
        config(['wallets.allowed_emails' => ['allowed@example.com'], 'wallets.allow_all_emails' => false]);

        $this->mockGoogleUser('other@example.com', 'Other', 'google-2');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function mockGoogleUser(string $email, string $name, string $id): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getId')->andReturn($id);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
