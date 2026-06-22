<?php

namespace App\Services\Knowledge;

use App\Enums\Audit\AuditAction;
use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\Course;
use App\Models\CourseInstitution;
use App\Models\Institution;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseInstitutionService
{
    public function __construct(
        private readonly KnowledgeContentSanitizer $sanitizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Tenant $tenant, array $data, User $actor): CourseInstitution
    {
        $course = Course::query()->whereKey((int) $data['course_id'])->firstOrFail();
        $institution = Institution::query()->whereKey((int) $data['institution_id'])->firstOrFail();

        if (CourseInstitution::query()->where('course_id', $course->id)->where('institution_id', $institution->id)->exists()) {
            throw ValidationException::withMessages(['course_id' => 'This course-institution link already exists.']);
        }

        return DB::transaction(function () use ($tenant, $data, $actor, $course, $institution): CourseInstitution {
            $record = CourseInstitution::query()->create([
                'tenant_id' => $tenant->id,
                'course_id' => $course->id,
                'institution_id' => $institution->id,
                'intake_label' => $this->sanitizer->optionalText($data['intake_label'] ?? null, 120),
                'fee_amount_minor' => isset($data['fee_amount_minor']) ? (int) $data['fee_amount_minor'] : null,
                'currency' => strtoupper((string) ($data['currency'] ?? 'INR')),
                'notes' => $this->sanitizer->optionalText($data['notes'] ?? null, 2000),
                'status' => KnowledgePublishableStatus::Draft->value,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(AuditAction::CourseInstitutionCreated, $record, $tenant->id, [
                'course_id' => $course->id,
                'institution_id' => $institution->id,
            ], $actor);

            return $record;
        });
    }

    public function publish(CourseInstitution $record, User $actor): CourseInstitution
    {
        return DB::transaction(function () use ($record, $actor): CourseInstitution {
            $record->update([
                'status' => KnowledgePublishableStatus::Published->value,
                'published_at' => now(),
            ]);

            $this->auditLogger->log(AuditAction::CourseInstitutionUpdated, $record, $record->tenant_id, ['action' => 'published'], $actor);

            return $record->fresh();
        });
    }

    public function archive(CourseInstitution $record, User $actor): CourseInstitution
    {
        return DB::transaction(function () use ($record, $actor): CourseInstitution {
            $record->update(['status' => KnowledgePublishableStatus::Archived->value]);

            $this->auditLogger->log(AuditAction::CourseInstitutionUpdated, $record, $record->tenant_id, ['action' => 'archived'], $actor);

            return $record->fresh();
        });
    }
}
