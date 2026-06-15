<?php

namespace App\Models;

use App\Enums\Configuration\DayOfWeek;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['day_of_week', 'opens_at', 'closes_at', 'is_closed'])]
class TenantOfficeHour extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_office_hours';

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'is_closed' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
