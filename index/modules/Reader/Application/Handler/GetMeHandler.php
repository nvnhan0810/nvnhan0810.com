<?php

declare(strict_types=1);

namespace Modules\Reader\Application\Handler;

use Modules\Reader\Application\Query\GetMe;
use Modules\Reader\Domain\Entities\ReaderUser;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\ReaderUserRepository;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;

final class GetMeHandler implements QueryHandler
{
    public function __construct(private readonly ReaderUserRepository $users) {}

    public function handle(Query $query): ReaderUser
    {
        assert($query instanceof GetMe);
        $user = $this->users->findById($query->userId);
        if ($user === null) {
            throw ReaderDomainException::notFound('User not found');
        }

        return $user;
    }
}
