<?php

namespace Modules\Sso\Application\Handler;

use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Modules\Sso\Application\Command\DeleteSsoClient;
use Modules\Sso\Domain\Exceptions\SsoClientNotFoundException;
use Modules\Sso\Domain\Ports\SsoClientRepository;

final class DeleteSsoClientHandler implements CommandHandler
{
    public function __construct(
        private readonly SsoClientRepository $clients,
    ) {}

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteSsoClient);

        if ($this->clients->findById($command->id) === null) {
            throw new SsoClientNotFoundException($command->id);
        }

        $this->clients->delete($command->id);
    }
}
