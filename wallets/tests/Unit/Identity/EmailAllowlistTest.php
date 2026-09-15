<?php

namespace Tests\Unit\Identity;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Identity\Application\Query\IsEmailAllowed;
use Wallets\Identity\Domain\EmailAllowlist;
use Wallets\Shared\Application\QueryBus;

class EmailAllowlistTest extends TestCase
{
    #[Test]
    public function it_matches_exact_and_domain_patterns(): void
    {
        $this->assertTrue(EmailAllowlist::isAllowed('a@x.com', false, ['a@x.com']));
        $this->assertTrue(EmailAllowlist::isAllowed('b@company.com', false, ['*@company.com']));
        $this->assertFalse(EmailAllowlist::isAllowed('c@other.com', false, ['*@company.com']));
        $this->assertTrue(EmailAllowlist::isAllowed('anyone@x.com', true, []));
    }

    #[Test]
    public function query_bus_respects_config_allowlist(): void
    {
        config(['wallets.allow_all_emails' => false, 'wallets.allowed_emails' => ['allowed@example.com']]);

        $this->assertTrue(app(QueryBus::class)->ask(new IsEmailAllowed('allowed@example.com')));
        $this->assertFalse(app(QueryBus::class)->ask(new IsEmailAllowed('denied@example.com')));
    }
}
