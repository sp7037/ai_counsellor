<?php

namespace App\Models;

use App\Enums\Configuration\LauncherAnimation;
use App\Enums\Configuration\LauncherMode;
use App\Enums\Configuration\WidgetPosition;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'welcome_message',
    'offline_message',
    'offline_form_enabled',
    'widget_position',
    'welcome_delay_seconds',
    'launcher_mode',
    'launcher_card_image_path',
    'launcher_card_title',
    'launcher_card_subtitle',
    'launcher_card_cta_text',
    'launcher_card_trust_text',
    'launcher_card_delay_seconds',
    'launcher_card_dismiss_hours',
    'launcher_card_animation',
])]
class TenantWidgetSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_widget_settings';

    protected function casts(): array
    {
        return [
            'offline_form_enabled' => 'boolean',
            'widget_position' => WidgetPosition::class,
            'welcome_delay_seconds' => 'integer',
            'launcher_mode' => LauncherMode::class,
            'launcher_card_delay_seconds' => 'integer',
            'launcher_card_dismiss_hours' => 'integer',
            'launcher_card_animation' => LauncherAnimation::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
