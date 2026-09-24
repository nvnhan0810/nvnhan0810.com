<?php

namespace Modules\Sso\Application\Handler;

use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;
use Modules\Sso\Application\Query\ListSsoClients;
use Modules\Sso\Domain\Ports\SsoClientRepository;
use Modules\Sso\Domain\SsoClientDefinition;

final class ListSsoClientsHandler implements QueryHandler
{
    public function __construct(
        private readonly SsoClientRepository $clients,
    ) {}

    /**
     * @return list<SsoClientDefinition>
     */
    public function handle(Query $query): array
    {
        assert($query instanceof ListSsoClients);

        return $this->clients->all();
    }
}
