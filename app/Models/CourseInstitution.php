<?php

namespace App\Models;

use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseInstitution extends Model
{
    use BelongsToTenant;

    protected $table = 'course_institution';

    protected $fillable = [
        'tenant_id', 'course_id', 'institution_id', 'intake_label', 'fee_amount_minor', 'currency',
        'notes', 'status', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => KnowledgePublishableStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
