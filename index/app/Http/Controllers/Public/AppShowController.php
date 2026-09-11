<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AppShowController extends Controller
{
    /** @var list<string> */
    private const PROJECT_SLUGS = [
        'foreign-language-course',
        'db-management-tool',
    ];

    public function __invoke(string $slug)
    {
        if (! in_array($slug, self::PROJECT_SLUGS, true)) {
            abort(404);
        }

        return Inertia::render('public/apps/ShowPage', [
            'slug' => $slug,
        ]);
    }
}
