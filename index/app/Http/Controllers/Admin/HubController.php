<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class HubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('private/admin/HubPage', [
            'modules' => [
                [
                    'key' => 'posts',
                    'label' => 'Posts',
                    'description' => 'Quản lý bài viết blog (tạo / sửa trên trang công khai).',
                    'path' => '/posts',
                    'route' => 'posts.index',
                ],
                [
                    'key' => 'tags',
                    'label' => 'Tags',
                    'description' => 'Thẻ gắn bài viết.',
                    'path' => '/admin/tags',
                    'route' => 'admin.tags.index',
                ],
                [
                    'key' => 'series',
                    'label' => 'Series',
                    'description' => 'Chuỗi bài / series.',
                    'path' => '/admin/series',
                    'route' => 'admin.series.index',
                ],
                [
                    'key' => 'reading-digest',
                    'label' => 'Reading Digest',
                    'description' => 'Digest hàng ngày, nguồn, chủ đề, taxonomy.',
                    'path' => '/admin/reading-digest/today',
                    'route' => 'admin.reading-digest.today',
                ],
                [
                    'key' => 'sso-clients',
                    'label' => 'SSO clients',
                    'description' => 'OAuth / SSO clients cho app (Reader, FLC, …).',
                    'path' => '/admin/sso-clients',
                    'route' => 'admin.sso-clients.index',
                ],
                [
                    'key' => 'reader',
                    'label' => 'Reader',
                    'description' => 'Documents, collections, trash (PDF Reader).',
                    'path' => '/admin/reader/documents',
                    'route' => 'admin.reader.documents.index',
                ],
            ],
        ]);
    }
}
