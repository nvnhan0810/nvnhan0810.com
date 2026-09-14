<?php

namespace App\Http\Controllers\Admin\ReadingDigest;

use App\Http\Controllers\Controller;
use App\Models\RdArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ReadingDigest\Domain\Services\ArticleLanguageService;

class ArticleInboxController extends Controller
{
    public function index(Request $request)
    {
        $articles = RdArticle::query()
            ->with(['source', 'taxonomyNodes'])
            ->whereIn('language', ArticleLanguageService::allowed())
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
        $article = RdArticle::query()->findOrFail($id);

        $data = $request->validate([
            'force_include' => 'boolean',
            'force_exclude' => 'boolean',
        ]);

        $article->update($data);

        return back();
    }
}
