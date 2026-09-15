<?php

namespace Wallets\Identity\Application\Handler;

use Wallets\Identity\Application\Query\IsEmailAllowed;
use Wallets\Identity\Domain\EmailAllowlist;
use Wallets\Shared\Application\Config;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class IsEmailAllowedHandler implements QueryHandler
{
    public function __construct(private readonly Config $config) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof IsEmailAllowed);

        $allowAll = (bool) $this->config->get('wallets.allow_all_emails', false);
        $fromEnv = $this->config->get('wallets.allowed_emails', []);
        $patterns = EmailAllowlist::mergePatterns([], is_array($fromEnv) ? $fromEnv : []);

        return EmailAllowlist::isAllowed($query->email, $allowAll, $patterns);
    }
}
