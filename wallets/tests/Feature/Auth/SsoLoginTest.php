<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sso.idp_url' => 'https://idp.test',
            'sso.client_id' => 'wallets',
            'sso.secret' => 'secret',
            'sso.redirect_uri' => 'https://wallets.test/auth/sso/callback',
            'wallets.allowed_emails' => ['allowed@example.com'],
            'wallets.allow_all_emails' => false,
        ]);
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/wallets')->assertRedirect('/wallets/login');
    }

    #[Test]
    public function sso_entry_redirects_to_idp(): void
    {
        $response = $this->get(route('auth.sso'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://idp.test/auth/sso/authorize', $location);
        $this->assertStringContainsString('client_id=wallets', $location);
    }

    #[Test]
    public function allowlisted_sso_user_can_sign_in(): void
    {
        Http::fake([
            'https://idp.test/api/auth/sso/token' => Http::response([
                'sub' => 1,
                'email' => 'allowed@example.com',
                'name' => 'Allowed User',
                'avatar' => null,
            ]),
        ]);

        $this->withSession(['sso.oauth_state' => 'state-1'])
            ->get(route('auth.sso.callback', ['code' => 'code-abc', 'state' => 'state-1']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'allowed@example.com',
        ]);
    }

    #[Test]
    public function non_allowlisted_sso_user_is_denied(): void
    {
        Http::fake([
            'https://idp.test/api/auth/sso/token' => Http::response([
                'sub' => 2,
                'email' => 'other@example.com',
                'name' => 'Other',
                'avatar' => null,
            ]),
        ]);

        $this->withSession(['sso.oauth_state' => 'state-2'])
            ->get(route('auth.sso.callback', ['code' => 'code-xyz', 'state' => 'state-2']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
