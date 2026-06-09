<?php

return [
    'site_name' => env('SEO_SITE_NAME', 'Nguyen Van Nhan'),
    'twitter_site' => env('SEO_TWITTER_SITE', '@nvnhan0810'),
    'default_og_image' => '/og/home.png',
    'og_image_width' => 1200,
    'og_image_height' => 630,
    'portfolio_title' => env('SEO_PORTFOLIO_TITLE', 'Software Developer'),
    'portfolio_tagline' => env('SEO_PORTFOLIO_TAGLINE', 'Web · Mobile · System Architecture'),
    'portfolio' => [
        'en' => [
            'name' => env('SEO_SITE_NAME', 'Nguyen Van Nhan'),
            'title' => env('SEO_PORTFOLIO_TITLE', 'Software Developer'),
            'tagline' => env('SEO_PORTFOLIO_TAGLINE', 'Web · Mobile · System Architecture'),
        ],
        'vi' => [
            'name' => 'Nguyễn Văn Nhàn',
            'title' => 'Lập trình viên',
            'tagline' => 'Web · Mobile · Kiến trúc hệ thống',
        ],
    ],
];
