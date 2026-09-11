<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class OpenIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $data = $this->fetchOpenId($request);
        } catch(InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }

        $user = User::updateOrCreate([
            'email' => $data['email'],
        ], [
            'avatar' => $data['avatar'],
            'name' => $data['name'],
        ]);

        $request->merge([
            'user' => $user,
        ]);

        return $next($request);
    }

    private function fetchOpenId(Request $request): array {
        $token = $request->bearerToken();

        if ($token == null) {
            throw new InvalidArgumentException(code: 401, message: 'UnAuthorization.');
        }

        $baseUrl = config('services.open_id.base_uri');

        $res = Http::withToken($token)->get("{$baseUrl}/api/user");

        if ($res->failed()) {
            throw new InvalidArgumentException(code: 403, message: 'Forbiden.');
        }

        return $res->json();
    }
}
