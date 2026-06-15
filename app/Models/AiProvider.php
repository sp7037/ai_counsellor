<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'supports_tools', 'enabled'])]
class AiProvider extends Model
{
    public function tenantConfigs(): HasMany
    {
        return $this->hasMany(TenantAiConfig::class, 'provider_id');
    }
}
