<?php

namespace Modules\Sso\Application\Handler;

use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Modules\Sso\Application\Command\CreateSsoClient;
use Modules\Sso\Domain\Exceptions\DuplicateSsoClientIdException;
use Modules\Sso\Domain\Ports\SsoClientRepository;
use Modules\Sso\Domain\SsoClientDefinition;

final class CreateSsoClientHandler implements CommandHandler
{
    public function __construct(
        private readonly SsoClientRepository $clients,
    ) {}

    public function handle(Command $command): SsoClientDefinition
    {
        assert($command instanceof CreateSsoClient);

        if ($this->clients->clientIdExists($command->clientId)) {
            throw new DuplicateSsoClientIdException($command->clientId);
        }

        return $this->clients->create(
            $command->clientId,
            $command->name,
            $command->domain,
            $command->redirectUris,
            $command->redirectUriPatterns,
            $command->enabled,
        );
    }
}
