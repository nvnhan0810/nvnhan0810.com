<?php

namespace Tests\Feature\Modules\Sso;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SsoAuthorizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sso.secret' => 'test-secret',
            'sso.clients' => [
                'wallets' => [
                    'redirect_uris' => ['https://wallets.test/auth/sso/callback'],
                    'redirect_uri_patterns' => [],
                ],
            ],
            'auth.valid_emails' => ['allowed@example.com'],
        ]);
    }

    #[Test]
    public function test_it_should_redirect_guest_to_google_login_and_store_intent(): void
    {
        $response = $this->get('/auth/sso/authorize?'.http_build_query([
            'client_id' => 'wallets',
            'redirect_uri' => 'https://wallets.test/auth/sso/callback',
            'response_type' => 'code',
            'state' => 'xyz',
        ]));

        $response->assertRedirect(route('google.login'));
        $this->assertSame([
            'client_id' => 'wallets',
            'redirect_uri' => 'https://wallets.test/auth/sso/callback',
            'state' => 'xyz',
        ], session('sso.intent'));
    }

    #[Test]
    public function test_it_should_issue_code_when_user_is_authenticated(): void
    {
        Cache::flush();

        $user = User::factory()->make(['email' => 'allowed@example.com']);
        $user->id = 9001;

        $response = $this->actingAs($user)->get('/auth/sso/authorize?'.http_build_query([
            'client_id' => 'wallets',
            'redirect_uri' => 'https://wallets.test/auth/sso/callback',
            'response_type' => 'code',
            'state' => 'abc',
        ]));

        $target = $response->headers->get('Location');
        $this->assertNotNull($target);
        $this->assertStringStartsWith('https://wallets.test/auth/sso/callback', $target);
        $this->assertStringContainsString('code=', $target);
        $this->assertStringContainsString('state=abc', $target);
    }

    #[Test]
    public function test_it_should_exchange_code_for_user_claims(): void
    {
        Cache::flush();

        $user = User::factory()->make([
            'email' => 'allowed@example.com',
            'name' => 'Allowed',
        ]);
        $user->id = 9002;

        $authResponse = $this->actingAs($user)->get('/auth/sso/authorize?'.http_build_query([
            'client_id' => 'wallets',
            'redirect_uri' => 'https://wallets.test/auth/sso/callback',
            'response_type' => 'code',
            'state' => 's1',
        ]));

        parse_str((string) parse_url((string) $authResponse->headers->get('Location'), PHP_URL_QUERY), $query);
        $code = $query['code'] ?? '';
        $this->assertNotSame('', $code);

        $tokenResponse = $this->postJson('/api/auth/sso/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'wallets',
            'client_secret' => 'test-secret',
            'code' => $code,
            'redirect_uri' => 'https://wallets.test/auth/sso/callback',
        ]);

        $tokenResponse->assertOk()
            ->assertJsonPath('email', 'allowed@example.com')
            ->assertJsonPath('name', 'Allowed');
    }
}
