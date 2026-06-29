<?php

namespace App\Domains\ReadingDigest\Presentation\Http\Controllers;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\DigestArticleModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArticleInboxController extends Controller
{
    public function index(Request $request)
    {
        $articles = DigestArticleModel::query()
            ->with(['source', 'taxonomyNodes'])
            ->when($request->search, fn ($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->orderByDesc('published_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('domains/reading-digest/pages/admin/articles/ListPage', [
            'articles' => $articles,
            'filters' => $request->only('search'),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $article = DigestArticleModel::query()->findOrFail($id);

        $data = $request->validate([
            'force_include' => 'boolean',
            'force_exclude' => 'boolean',
        ]);

        $article->update($data);

        return back();
    }
}
