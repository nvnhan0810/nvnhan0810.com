<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\Logout;
use Modules\Reader\Domain\Ports\AccessTokenIssuer;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class LogoutHandler implements CommandHandler
{
    public function __construct(private readonly AccessTokenIssuer $tokens) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof Logout);
        $this->tokens->revokeCurrentToken($command->userId, $command->tokenId);

        return null;
    }
}
