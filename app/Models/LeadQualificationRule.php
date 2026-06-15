<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rules', 'enabled', 'updated_by'])]
class LeadQualificationRule extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
