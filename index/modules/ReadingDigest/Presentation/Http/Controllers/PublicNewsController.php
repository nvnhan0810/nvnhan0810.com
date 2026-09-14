<?php

namespace Modules\ReadingDigest\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RdArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ReadingDigest\Application\Query\GetArticleList;
use Modules\Shared\Application\QueryBus;

class PublicNewsController extends Controller
{
    /**
     * Public feed: newest articles across sources.
     */
    public function index(Request $request, QueryBus $queryBus): Response
    {
        $articles = $queryBus
            ->ask(new GetArticleList)
            ->through(fn (RdArticle $article) => $this->serializeArticle($article));

        return Inertia::render('public/NewsIndexPage', [
            'articles' => $articles,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(RdArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'url' => $article->url,
            'summary' => $article->summary,
            'image_url' => $article->image_url,
            'published_at' => $article->published_at,
            'source' => $article->source?->only(['id', 'name']),
        ];
    }
}
