<?php

namespace Modules\Sso\Application\Handler;

use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Modules\Sso\Application\Command\UpdateSsoClient;
use Modules\Sso\Domain\Exceptions\DuplicateSsoClientIdException;
use Modules\Sso\Domain\Exceptions\SsoClientNotFoundException;
use Modules\Sso\Domain\Ports\SsoClientRepository;
use Modules\Sso\Domain\SsoClientDefinition;

final class UpdateSsoClientHandler implements CommandHandler
{
    public function __construct(
        private readonly SsoClientRepository $clients,
    ) {}

    public function handle(Command $command): SsoClientDefinition
    {
        assert($command instanceof UpdateSsoClient);

        if ($this->clients->findById($command->id) === null) {
            throw new SsoClientNotFoundException($command->id);
        }

        if ($this->clients->clientIdExists($command->clientId, $command->id)) {
            throw new DuplicateSsoClientIdException($command->clientId);
        }

        return $this->clients->update(
            $command->id,
            $command->clientId,
            $command->name,
            $command->domain,
            $command->redirectUris,
            $command->redirectUriPatterns,
            $command->enabled,
        );
    }
}
