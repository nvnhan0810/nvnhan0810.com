<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Persistence;

use App\Models\User;
use Modules\Reader\Domain\Entities\ReaderUser;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\ReaderUserRepository;

final class EloquentReaderUserRepository implements ReaderUserRepository
{
    public function findById(string $id): ?ReaderUser
    {
        $model = User::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function upsertFromIdpClaims(
        int $idpSub,
        string $email,
        string $name,
        ?string $avatarUrl,
    ): ReaderUser {
        // IdP `sub` === `users.id` (created when Google SSO completes on index).
        $model = User::query()->find($idpSub);

        if ($model === null) {
            throw ReaderDomainException::invalidGrant('IdP user does not exist locally');
        }

        $model->name = $name;
        $model->email = $email;
        if ($avatarUrl !== null) {
            $model->avatar = $avatarUrl;
        }
        $model->save();

        return $this->toDomain($model);
    }

    private function toDomain(User $model): ReaderUser
    {
        return new ReaderUser(
            id: (string) $model->id,
            email: (string) $model->email,
            name: (string) $model->name,
            avatarUrl: $model->avatar !== null ? (string) $model->avatar : null,
        );
    }
}
