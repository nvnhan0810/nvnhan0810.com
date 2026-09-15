<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PathPrefixTest extends TestCase
{
    #[Test]
    public function test_it_should_register_web_routes_under_wallets_prefix(): void
    {
        $this->assertSame('wallets', config('app.path_prefix'));

        $this->get('/wallets/login')->assertOk();
        $this->get('/login')->assertNotFound();
    }
}
