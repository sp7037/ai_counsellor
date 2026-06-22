<?php

namespace App\Enums\Knowledge;

enum KnowledgeImportType: string
{
    case Faq = 'faq';
    case CourseInfo = 'course_info';
    case Fee = 'fee';
    case Eligibility = 'eligibility';

    public function label(): string
    {
        return match ($this) {
            self::Faq => 'FAQ import',
            self::CourseInfo => 'Course/program information',
            self::Fee => 'Fee information',
            self::Eligibility => 'Eligibility rules',
        };
    }

    /**
     * @return array<int, string>
     */
    public function requiredHeaders(): array
    {
        return match ($this) {
            self::Faq => ['question', 'answer'],
            self::CourseInfo => ['title', 'body'],
            self::Fee => ['label', 'fee_type', 'amount_minor', 'currency'],
            self::Eligibility => ['title', 'required_criteria'],
        };
    }

    /**
     * @return array<int, string>
     */
    public function optionalHeaders(): array
    {
        return match ($this) {
            self::Faq => ['category', 'tags', 'status'],
            self::CourseInfo => ['category', 'tags', 'status'],
            self::Fee => ['notes', 'status'],
            self::Eligibility => ['preferred_criteria', 'priority', 'status'],
        };
    }
}
