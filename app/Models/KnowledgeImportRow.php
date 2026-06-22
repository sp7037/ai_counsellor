<?php

namespace App\Models;

use App\Enums\Knowledge\KnowledgeImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'knowledge_import_id',
    'row_number',
    'status',
    'payload',
    'error_message',
])]
class KnowledgeImportRow extends Model
{
    protected function casts(): array
    {
        return [
            'status' => KnowledgeImportRowStatus::class,
            'payload' => 'array',
            'row_number' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(KnowledgeImport::class, 'knowledge_import_id');
    }
}
