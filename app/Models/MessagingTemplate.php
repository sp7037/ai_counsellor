<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'messaging_integration_id',
    'provider_template_name',
    'language_code',
    'category',
    'status',
    'variable_definitions',
    'last_synced_at',
])]
class MessagingTemplate extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (MessagingTemplate $template): void {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'variable_definitions' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TenantMessagingIntegration::class, 'messaging_integration_id');
    }
}
