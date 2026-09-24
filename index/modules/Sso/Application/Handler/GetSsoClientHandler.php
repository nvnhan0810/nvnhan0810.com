<?php

namespace Modules\Sso\Application\Handler;

use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;
use Modules\Sso\Application\Query\GetSsoClient;
use Modules\Sso\Domain\Ports\SsoClientRepository;
use Modules\Sso\Domain\SsoClientDefinition;

final class GetSsoClientHandler implements QueryHandler
{
    public function __construct(
        private readonly SsoClientRepository $clients,
    ) {}

    public function handle(Query $query): ?SsoClientDefinition
    {
        assert($query instanceof GetSsoClient);

        return $this->clients->findById($query->id);
    }
}
