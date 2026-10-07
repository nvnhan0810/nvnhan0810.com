<?php

declare(strict_types=1);

namespace Modules\Reader\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Reader\Application\Command\ExchangeSsoCode;
use Modules\Reader\Application\Command\Logout;
use Modules\Reader\Application\DTOs\AuthTokenResult;
use Modules\Reader\Application\Query\GetMe;
use Modules\Reader\Domain\Entities\ReaderUser;
use Modules\Reader\Domain\Exceptions\ReaderDomainException;
use App\Models\User;
use Modules\Reader\Presentation\Http\Responses\ApiErrorResponse;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;

final class AuthController extends Controller
{
    public function exchange(Request $request, CommandBus $commandBus): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:128'],
            'redirect_uri' => ['required', 'string', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:128'],
            'client_id' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            /** @var AuthTokenResult $result */
            $result = $commandBus->dispatch(new ExchangeSsoCode(
                code: $data['code'],
                redirectUri: $data['redirect_uri'],
                deviceName: $data['device_name'] ?? 'apple-reader',
                clientId: $data['client_id'] ?? (string) config('reader.sso_client_id'),
            ));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json([
            'token' => $result->token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($result->user),
        ]);
    }

    public function me(Request $request, QueryBus $queryBus): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        try {
            /** @var ReaderUser $user */
            $user = $queryBus->ask(new GetMe($userId));
        } catch (ReaderDomainException $exception) {
            return ApiErrorResponse::fromDomain($exception);
        }

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request, CommandBus $commandBus): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            return ApiErrorResponse::unauthenticated();
        }

        $token = $request->user()?->currentAccessToken();
        $tokenId = $token instanceof PersonalAccessToken ? (string) $token->getKey() : '';

        if ($tokenId !== '') {
            $commandBus->dispatch(new Logout($userId, $tokenId));
        }

        return response()->json(['ok' => true]);
    }

    /** @return array{id: string, email: string, name: string, avatar: string|null} */
    private function userPayload(ReaderUser $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatarUrl,
        ];
    }

    private function authenticatedUserId(Request $request): ?string
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return null;
        }

        return (string) $user->id;
    }
}
