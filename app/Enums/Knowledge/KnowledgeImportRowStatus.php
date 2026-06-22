<?php

namespace App\Enums\Knowledge;

enum KnowledgeImportRowStatus: string
{
    case Valid = 'valid';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Failed => 'Invalid',
            self::Skipped => 'Duplicate skipped',
            self::Imported => 'Imported',
        };
    }
}
