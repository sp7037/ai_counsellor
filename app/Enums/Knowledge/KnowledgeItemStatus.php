<?php

namespace App\Enums\Knowledge;

enum KnowledgeItemStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::UnderReview => 'Under review',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function isPubliclyRetrievable(): bool
    {
        return $this === self::Published;
    }
}
