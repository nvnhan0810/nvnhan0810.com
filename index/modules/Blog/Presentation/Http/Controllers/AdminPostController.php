<?php

namespace Modules\Blog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Modules\Blog\Application\Command\CreatePost;
use Modules\Blog\Application\Command\DeletePost;
use Modules\Blog\Application\Command\UpdatePost;
use Modules\Blog\Domain\Exceptions\PostNotFoundException;
use Modules\Blog\Presentation\Http\Requests\CreatePostRequest;
use Modules\Blog\Presentation\Http\Requests\UpdatePostRequest;
use Modules\Shared\Application\CommandBus;
use Throwable;

final class AdminPostController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('posts.index');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('posts.index', ['create' => 1]);
    }

    public function store(CreatePostRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->commands->dispatch(new CreatePost(
                title: $validated['title'],
                content: $validated['content'],
                status: $validated['status'],
                description: $validated['description'] ?? null,
                sourceUrl: $validated['source_url'] ?? null,
                publishedAt: $validated['published_at'] ?? null,
                tags: $validated['tags'] ?? null,
                seriesIds: $validated['series_ids'] ?? null,
            ));

            return redirect()->route('posts.index');
        } catch (Throwable $e) {
            Log::info(__METHOD__, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['form' => 'Create post failed']);
        }
    }

    public function edit(int $post): RedirectResponse
    {
        return redirect()->route('posts.index', ['edit' => $post]);
    }

    public function update(UpdatePostRequest $request, int $post): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->commands->dispatch(new UpdatePost(
                id: $post,
                title: $validated['title'],
                content: $validated['content'],
                status: $validated['status'],
                description: $validated['description'] ?? null,
                sourceUrl: $validated['source_url'] ?? null,
                publishedAt: $validated['published_at'] ?? null,
                tags: $validated['tags'] ?? null,
                seriesIds: $validated['series_ids'] ?? null,
            ));

            return redirect()->route('posts.index');
        } catch (PostNotFoundException) {
            return redirect()->route('posts.index')->withErrors(['form' => 'Post Not Found']);
        } catch (Throwable $e) {
            Log::info(__METHOD__, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['form' => 'Update post failed']);
        }
    }

    public function destroy(int $post): RedirectResponse
    {
        try {
            $this->commands->dispatch(new DeletePost($post));

            return redirect()->route('posts.index');
        } catch (PostNotFoundException) {
            return redirect()->route('posts.index')->withErrors(['form' => 'Post Not Found']);
        } catch (Throwable $e) {
            Log::info(__METHOD__, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['form' => 'Delete Post Failed']);
        }
    }
}
