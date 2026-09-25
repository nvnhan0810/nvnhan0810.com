<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Blog\Application\Query\GetLatestPosts;
use Modules\Shared\Application\QueryBus;

class HomeController extends Controller
{
    public const LATEST_POSTS_LIMIT = 10;

    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    public function __invoke()
    {
        $posts = $this->queries->ask(new GetLatestPosts(self::LATEST_POSTS_LIMIT));

        return Inertia::render('public/HomePage', [
            'posts' => $posts,
        ]);
    }
}
