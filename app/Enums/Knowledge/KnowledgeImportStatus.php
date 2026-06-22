<?php

namespace App\Enums\Knowledge;

enum KnowledgeImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Failed = 'failed';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ready to import',
            self::Validating => 'Validating',
            self::Failed => 'Failed',
            self::Completed => 'Completed',
        };
    }
}
