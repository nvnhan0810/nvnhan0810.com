<?php

namespace App\Domains\ReadingDigest\Domain\Enums;

enum ArticleType: string
{
    case Tutorial = 'tutorial';
    case DeepDive = 'deep_dive';
    case Architecture = 'architecture';
    case Performance = 'performance';
    case News = 'news';
    case Opinion = 'opinion';
    case Marketing = 'marketing';
    case ConferenceRecap = 'conference_recap';
    case CaseStudy = 'case_study';
    case BestPractices = 'best_practices';
}
