<?php

namespace App\Models;

use App\Enums\Knowledge\KnowledgeImportStatus;
use App\Enums\Knowledge\KnowledgeImportType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'user_id',
    'import_type',
    'original_filename',
    'status',
    'total_rows',
    'valid_rows',
    'failed_rows',
    'skipped_rows',
    'imported_rows',
    'error_summary',
])]
class KnowledgeImport extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'import_type' => KnowledgeImportType::class,
            'status' => KnowledgeImportStatus::class,
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_rows' => 'integer',
            'imported_rows' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(KnowledgeImportRow::class);
    }
}
