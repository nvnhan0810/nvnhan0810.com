<?php

namespace App\Http\Controllers\Admin;

use App\Domains\PostAgent\Infrastructure\Llm\PostAgentCancelledException;
use App\Domains\PostAgent\Services\PostAgentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostAgentChatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostAgentController extends Controller
{
    public function __construct(
        private readonly PostAgentService $postAgentService,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'configured' => $this->postAgentService->isConfigured(),
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        $postId = $request->integer('post_id') ?: null;

        $data = $this->postAgentService->getSessionForPost(
            (int) $request->user()->id,
            $postId,
        );

        return response()->json([
            'configured' => $this->postAgentService->isConfigured(),
            ...$data,
        ]);
    }

    public function chat(PostAgentChatRequest $request): JsonResponse
    {
        try {
            $result = $this->postAgentService->chat(
                (int) $request->user()->id,
                $request->validated(),
            );

            return response()->json($result);
        } catch (PostAgentCancelledException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'cancelled' => true,
            ], 499);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Không thể kết nối agent. Thử lại sau.',
            ], 502);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $this->postAgentService->cancel((int) $request->user()->id);

        return response()->json([
            'cancelled' => true,
        ]);
    }
}
