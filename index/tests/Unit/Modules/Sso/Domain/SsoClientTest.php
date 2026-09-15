<?php

namespace Tests\Unit\Modules\Sso\Domain;

use Modules\Sso\Domain\SsoClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SsoClientTest extends TestCase
{
    #[Test]
    public function test_it_should_accept_exact_redirect_uri(): void
    {
        $client = new SsoClient('wallets', 'secret', ['https://example.com/callback'], []);

        $this->assertTrue($client->acceptsRedirectUri('https://example.com/callback'));
        $this->assertFalse($client->acceptsRedirectUri('https://example.com/other'));
    }

    #[Test]
    public function test_it_should_accept_pattern_redirect_uri_for_mobile(): void
    {
        $client = new SsoClient('flc-mobile', 'secret', [], ['/^flc:\/\/oauth-callback(\/|\?|$)/']);

        $this->assertTrue($client->acceptsRedirectUri('flc://oauth-callback?code=abc'));
    }
}
