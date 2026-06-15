<?php

namespace App\Models;

use App\Enums\AI\AiCredentialMode;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'provider_id',
    'model',
    'temperature',
    'max_output_tokens',
    'timeout_seconds',
    'enabled',
    'credential_mode',
    'encrypted_api_key',
    'secret_updated_at',
    'created_by',
    'updated_by',
])]
class TenantAiConfig extends Model
{
    use BelongsToTenant;

    protected $hidden = [
        'encrypted_api_key',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantAiConfig $config): void {
            if (empty($config->uuid)) {
                $config->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'temperature' => 'decimal:2',
            'secret_updated_at' => 'datetime',
            'encrypted_api_key' => 'encrypted',
            'credential_mode' => AiCredentialMode::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
