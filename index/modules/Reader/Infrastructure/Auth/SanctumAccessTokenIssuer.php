<?php

declare(strict_types=1);

namespace Modules\Reader\Infrastructure\Auth;

use App\Models\User;
use Modules\Reader\Domain\Entities\ReaderUser;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use Modules\Reader\Domain\Ports\AccessTokenIssuer;

final class SanctumAccessTokenIssuer implements AccessTokenIssuer
{
    public function issue(ReaderUser $user, string $deviceName): string
    {
        $model = User::query()->find($user->id);
        if ($model === null) {
            throw ReaderDomainException::notFound('User not found');
        }

        $name = $deviceName !== '' ? $deviceName : 'apple-reader';

        return $model->createToken($name)->plainTextToken;
    }

    public function revokeCurrentToken(string $userId, string $tokenId): void
    {
        $model = User::query()->find($userId);
        if ($model === null) {
            return;
        }

        $model->tokens()->whereKey($tokenId)->delete();
    }
}
