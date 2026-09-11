<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('post-agent.api_token', '');
        $expected = trim($expected);

        if ($expected === '') {
            return response()->json([
                'message' => 'POST_AGENT_API_TOKEN chưa được cấu hình',
            ], 500);
        }

        $bearer = (string) $request->bearerToken();
        $xToken = (string) $request->header('X-API-TOKEN');

        $provided = $bearer !== '' ? $bearer : $xToken;
        $provided = trim($provided);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}

