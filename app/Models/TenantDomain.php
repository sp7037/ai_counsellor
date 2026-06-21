<?php

namespace App\Models;

use App\Enums\Widget\TenantDomainStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'domain', 'status', 'verified_at', 'created_by'])]
class TenantDomain extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'status' => TenantDomainStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function normalizedDomain(): string
    {
        return strtolower($this->domain);
    }
}
