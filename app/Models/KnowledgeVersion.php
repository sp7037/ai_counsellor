<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KnowledgeVersion extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'uuid', 'knowledge_item_id', 'version_number', 'title', 'body',
        'content_checksum', 'published_at', 'published_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (KnowledgeVersion $version): void {
            if (empty($version->uuid)) {
                $version->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'knowledge_item_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
