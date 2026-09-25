<?php

use App\Providers\AppServiceProvider;
use Modules\Blog\BlogServiceProvider;
use Modules\ReadingDigest\ReadingDigestServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Sso\SsoServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    BlogServiceProvider::class,
    ReadingDigestServiceProvider::class,
    SsoServiceProvider::class,
];
