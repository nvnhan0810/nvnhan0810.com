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
            self::HnAlgolia => 'Hacker News',
            self::Rss => 'Website RSS (news sites, blogs)',
            self::Reddit => 'Reddit (RSS)',
            self::GithubBlog => 'GitHub Blog (RSS)',
            self::CustomHtml => 'Custom HTML (future)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Rss => 'Most publishers — paste the RSS/feed URL from tuoitre.vn, thanhnien.com, dev.to, etc.',
            self::HnAlgolia => 'Hacker News stories via Algolia search API.',
            self::Reddit => 'Subreddit RSS feed URL.',
            self::GithubBlog => 'Engineering blog RSS feed.',
            self::CustomHtml => 'Site without RSS — scraping adapter (not implemented yet).',
        };
    }
}
