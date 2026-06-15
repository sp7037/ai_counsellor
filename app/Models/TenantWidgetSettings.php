<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['welcome_message', 'offline_message', 'offline_form_enabled'])]
class TenantWidgetSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_widget_settings';

    protected function casts(): array
    {
        return [
            'offline_form_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
