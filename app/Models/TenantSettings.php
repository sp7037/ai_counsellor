<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'display_name',
    'assistant_name',
    'assistant_title',
    'primary_color',
    'accent_color',
    'logo_path',
    'consent_text',
    'consent_version',
    'ai_disclosure_enabled',
    'ai_disclosure_message',
    'default_locale',
    'supported_locales',
    'human_transfer_enabled',
    'human_transfer_label',
    'human_transfer_message',
])]
class TenantSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_settings';

    protected function casts(): array
    {
        return [
            'ai_disclosure_enabled' => 'boolean',
            'human_transfer_enabled' => 'boolean',
            'supported_locales' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
