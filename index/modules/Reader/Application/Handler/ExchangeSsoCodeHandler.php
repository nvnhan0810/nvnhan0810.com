<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Command\ExchangeSsoCode;
use Modules\Reader\Application\DTOs\AuthTokenResult;
use Modules\Reader\Domain\Ports\AccessTokenIssuer;
use Modules\Reader\Domain\Ports\IdpTokenClient;
use Modules\Reader\Domain\Ports\ReaderUserRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class ExchangeSsoCodeHandler implements CommandHandler
{
    public function __construct(
        private readonly IdpTokenClient $idpTokenClient,
        private readonly ReaderUserRepository $users,
        private readonly AccessTokenIssuer $tokens,
    ) {}

    public function handle(Command $command): AuthTokenResult
    {
        assert($command instanceof ExchangeSsoCode);

        $claims = $this->idpTokenClient->exchangeAuthorizationCode(
            $command->code,
            $command->redirectUri,
            $command->clientId,
        );

        $user = $this->users->upsertFromIdpClaims(
            $claims->sub,
            $claims->email,
            $claims->name,
            $claims->avatar,
        );

        $token = $this->tokens->issue($user, $command->deviceName);

        return new AuthTokenResult($token, $user);
    }
}
