<?php

namespace App\Services\Knowledge;

use App\Enums\Audit\AuditAction;
use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\EligibilityRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class EligibilityRuleService
{
    public function __construct(
        private readonly KnowledgeContentSanitizer $sanitizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Tenant $tenant, array $data, User $actor): EligibilityRule
    {
        return DB::transaction(function () use ($tenant, $data, $actor): EligibilityRule {
            $rule = EligibilityRule::query()->create([
                'title' => $this->sanitizer->title($data['title'] ?? null),
                'service_id' => $data['service_id'] ?? null,
                'course_id' => $data['course_id'] ?? null,
                'required_criteria' => $this->sanitizer->optionalText($data['required_criteria'] ?? null, 4000),
                'preferred_criteria' => $this->sanitizer->optionalText($data['preferred_criteria'] ?? null, 4000),
                'priority' => max(1, min(999, (int) ($data['priority'] ?? 100))),
                'status' => KnowledgePublishableStatus::Draft->value,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(AuditAction::EligibilityCreated, $rule, $tenant->id, ['title' => $rule->title], $actor);

            return $rule;
        });
    }

    public function update(EligibilityRule $rule, array $data, User $actor): EligibilityRule
    {
        return DB::transaction(function () use ($rule, $data, $actor): EligibilityRule {
            $rule->update([
                'title' => $this->sanitizer->title($data['title'] ?? $rule->title),
                'service_id' => $data['service_id'] ?? $rule->service_id,
                'course_id' => $data['course_id'] ?? $rule->course_id,
                'required_criteria' => $this->sanitizer->optionalText($data['required_criteria'] ?? $rule->required_criteria, 4000),
                'preferred_criteria' => $this->sanitizer->optionalText($data['preferred_criteria'] ?? $rule->preferred_criteria, 4000),
                'priority' => max(1, min(999, (int) ($data['priority'] ?? $rule->priority))),
            ]);

            $this->auditLogger->log(AuditAction::EligibilityUpdated, $rule, $rule->tenant_id, [], $actor);

            return $rule->fresh();
        });
    }

    public function publish(EligibilityRule $rule, User $actor): EligibilityRule
    {
        return DB::transaction(function () use ($rule, $actor): EligibilityRule {
            $rule->update([
                'status' => KnowledgePublishableStatus::Published->value,
                'published_at' => now(),
            ]);

            $this->auditLogger->log(AuditAction::EligibilityUpdated, $rule, $rule->tenant_id, ['action' => 'published'], $actor);

            return $rule->fresh();
        });
    }

    public function archive(EligibilityRule $rule, User $actor): EligibilityRule
    {
        return DB::transaction(function () use ($rule, $actor): EligibilityRule {
            $rule->update(['status' => KnowledgePublishableStatus::Archived->value]);

            $this->auditLogger->log(AuditAction::EligibilityArchived, $rule, $rule->tenant_id, [], $actor);

            return $rule->fresh();
        });
    }
}
