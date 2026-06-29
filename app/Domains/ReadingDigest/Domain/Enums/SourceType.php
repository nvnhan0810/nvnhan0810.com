<?php

namespace App\Domains\ReadingDigest\Domain\Enums;

enum SourceType: string
{
    case HnAlgolia = 'hn_algolia';
    case Rss = 'rss';
    case Reddit = 'reddit';
    case GithubBlog = 'github_blog';
    case CustomHtml = 'custom_html';

    public function label(): string
    {
        return match ($this) {
            self::HnAlgolia => 'Hacker News (Algolia)',
            self::Rss => 'RSS Feed',
            self::Reddit => 'Reddit RSS',
            self::GithubBlog => 'GitHub Blog',
            self::CustomHtml => 'Custom HTML',
        };
    }
}
