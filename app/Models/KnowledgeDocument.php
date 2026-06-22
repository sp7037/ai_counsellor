<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id', 'uuid', 'knowledge_item_id', 'display_name', 'storage_path', 'mime_type',
    'size_bytes', 'checksum', 'status', 'created_by',
])]
class KnowledgeDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'documents';

    protected static function booted(): void
    {
        static::creating(function (KnowledgeDocument $document): void {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function knowledgeItem(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class);
    }
}
