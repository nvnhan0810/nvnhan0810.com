<?php

namespace Tests\Unit\Modules\Sso\Application\Handler;

use Modules\Sso\Application\DTOs\StoredAuthorizationCode;
use Modules\Sso\Application\Handler\ExchangeAuthorizationCodeHandler;
use Modules\Sso\Domain\Ports\AuthorizationCodeStore;
use Modules\Sso\Domain\SsoClientRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ExchangeAuthorizationCodeHandlerTest extends TestCase
{
    #[Test]
    public function test_it_should_return_claims_when_code_and_secret_are_valid(): void
    {
        config([
            'sso.secret' => 'test-secret',
            'sso.clients' => [
                'wallets' => [
                    'redirect_uris' => ['https://wallets.test/sso/callback'],
                    'redirect_uri_patterns' => [],
                ],
            ],
        ]);

        $store = new class implements AuthorizationCodeStore
        {
            public function put(string $code, StoredAuthorizationCode $payload, int $ttlSeconds): void {}

            public function pull(string $code): ?StoredAuthorizationCode
            {
                return new StoredAuthorizationCode(
                    'wallets',
                    'https://wallets.test/sso/callback',
                    42,
                    'user@example.com',
                    'User',
                    null,
                );
            }
        };

        $handler = new ExchangeAuthorizationCodeHandler(new SsoClientRegistry, $store);

        $claims = $handler->handle(
            'wallets',
            'test-secret',
            'any-code',
            'https://wallets.test/sso/callback',
        );

        $this->assertSame(42, $claims->sub);
        $this->assertSame('user@example.com', $claims->email);
    }
}
