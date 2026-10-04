<?php

declare(strict_types=1);

namespace Modules\Reader\Domain\Ports;

use Modules\Reader\Domain\Entities\ReaderUser;

interface ReaderUserRepository
{
    public function findById(string $id): ?ReaderUser;

    /**
     * Resolve IdP claims (`sub` = users.id) to the shared site user.
     */
    public function upsertFromIdpClaims(
        int $idpSub,
        string $email,
        string $name,
        ?string $avatarUrl,
    ): ReaderUser;
}
