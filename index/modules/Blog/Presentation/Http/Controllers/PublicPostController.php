<?php

namespace Modules\Blog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Blog\Application\DTOs\PostListResult;
use Modules\Blog\Application\DTOs\PostShowResult;
use Modules\Blog\Application\Query\GetPostBySlug;
use Modules\Blog\Application\Query\ListPosts;
use Modules\Shared\Application\QueryBus;

final class PublicPostController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    public function index(Request $request): Response
    {
        /** @var PostListResult $result */
        $result = $this->queries->ask(new ListPosts(
            authenticated: Auth::check(),
            search: $request->string('search')->toString(),
            tag: $request->string('tag')->toString(),
            statusFilter: $request->string('status')->toString(),
            editId: $request->integer('edit') ?: null,
        ));

        return Inertia::render('public/posts/ListPage', [
            'posts' => $result->posts,
            'tags' => $result->tags,
            'filters' => $result->filters,
            'series' => $result->series,
            'editingPost' => $result->editingPost,
            'selectedSeriesIds' => $result->selectedSeriesIds,
            'canManage' => $result->canManage,
        ]);
    }

    public function show(string $slug): Response
    {
        /** @var PostShowResult|null $result */
        $result = $this->queries->ask(new GetPostBySlug(
            slug: $slug,
            authenticated: Auth::check(),
        ));

        if ($result === null) {
            abort(404);
        }

        return Inertia::render('public/posts/ShowPage', [
            'post' => $result->post,
            'series' => $result->series,
            'editorSeries' => $result->editorSeries,
            'selectedSeriesIds' => $result->selectedSeriesIds,
            'canManage' => $result->canManage,
        ]);
    }
}
