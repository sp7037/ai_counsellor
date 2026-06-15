<?php

namespace App\Services\Knowledge;

use App\Enums\Audit\AuditAction;
use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Enums\Knowledge\KnowledgeItemType;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KnowledgeItemService
{
    public function __construct(
        private readonly KnowledgeContentSanitizer $sanitizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createDraft(Tenant $tenant, array $data, User $actor): KnowledgeItem
    {
        if (KnowledgeItem::query()->where('tenant_id', $tenant->id)->count() >= config('knowledge.max_items', 500)) {
            throw ValidationException::withMessages(['title' => 'Maximum knowledge items reached.']);
        }

        $title = $this->sanitizer->title($data['title'] ?? null);
        $body = $this->sanitizer->body($data['body'] ?? null);

        return DB::transaction(function () use ($tenant, $data, $actor, $title, $body): KnowledgeItem {
            $item = KnowledgeItem::query()->create([
                'type' => KnowledgeItemType::from((string) $data['type'])->value,
                'status' => KnowledgeItemStatus::Draft->value,
                'locale' => $data['locale'] ?? $tenant->locale ?? 'en',
                'title' => $title,
                'draft_title' => $title,
                'draft_body' => $body,
                'service_id' => $data['service_id'] ?? null,
                'course_id' => $data['course_id'] ?? null,
                'institution_id' => $data['institution_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->auditLogger->log(AuditAction::KnowledgeCreated, $item, $tenant->id, [
                'type' => $item->type->value,
                'title' => $item->title,
            ], $actor);

            return $item;
        });
    }

    public function updateDraft(KnowledgeItem $item, array $data, User $actor): KnowledgeItem
    {
        if ($item->status === KnowledgeItemStatus::Archived) {
            throw ValidationException::withMessages(['body' => 'Archived items cannot be edited.']);
        }

        return DB::transaction(function () use ($item, $data, $actor): KnowledgeItem {
            $before = ['draft_title' => $item->draft_title, 'draft_body' => mb_substr((string) $item->draft_body, 0, 200)];

            $item->update([
                'draft_title' => $this->sanitizer->title($data['draft_title'] ?? $data['title'] ?? $item->draft_title),
                'draft_body' => $this->sanitizer->body($data['draft_body'] ?? $data['body'] ?? $item->draft_body),
                'locale' => $data['locale'] ?? $item->locale,
                'service_id' => $data['service_id'] ?? $item->service_id,
                'course_id' => $data['course_id'] ?? $item->course_id,
                'institution_id' => $data['institution_id'] ?? $item->institution_id,
                'location_id' => $data['location_id'] ?? $item->location_id,
                'updated_by' => $actor->id,
                'status' => KnowledgeItemStatus::Draft->value,
            ]);

            $this->auditLogger->log(AuditAction::KnowledgeUpdated, $item, $item->tenant_id, [
                'before' => $before,
                'after' => ['draft_title' => $item->draft_title],
            ], $actor);

            return $item->fresh();
        });
    }

    public function publish(KnowledgeItem $item, User $actor): KnowledgeItem
    {
        return DB::transaction(function () use ($item, $actor): KnowledgeItem {
            $item = KnowledgeItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $title = $this->sanitizer->title($item->draft_title ?? $item->title);
            $body = $this->sanitizer->body($item->draft_body ?? '');

            $nextVersion = (int) $item->versions()->max('version_number') + 1;

            $version = KnowledgeVersion::query()->create([
                'knowledge_item_id' => $item->id,
                'version_number' => $nextVersion,
                'title' => $title,
                'body' => $body,
                'content_checksum' => hash('sha256', $title.$body),
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);

            $item->update([
                'title' => $title,
                'status' => KnowledgeItemStatus::Published->value,
                'current_version_id' => $version->id,
                'published_at' => now(),
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);

            $action = $nextVersion === 1 ? AuditAction::KnowledgePublished : AuditAction::KnowledgeVersionCreated;

            $this->auditLogger->log($action, $item, $item->tenant_id, [
                'version_number' => $nextVersion,
                'version_uuid' => $version->uuid,
            ], $actor);

            return $item->fresh(['currentVersion']);
        });
    }

    public function archive(KnowledgeItem $item, User $actor): KnowledgeItem
    {
        return DB::transaction(function () use ($item, $actor): KnowledgeItem {
            $before = ['status' => $item->status->value];

            $item->update([
                'status' => KnowledgeItemStatus::Archived->value,
                'archived_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $this->auditLogger->log(AuditAction::KnowledgeArchived, $item, $item->tenant_id, [
                'before' => $before,
                'after' => ['status' => $item->status->value],
            ], $actor);

            return $item->fresh();
        });
    }

    public function deleteItem(KnowledgeItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $snapshot = ['uuid' => $item->uuid, 'title' => $item->title, 'status' => $item->status->value];
            $tenantId = $item->tenant_id;
            $item->delete();

            $this->auditLogger->log(AuditAction::KnowledgeDeleted, null, $tenantId, ['before' => $snapshot], $actor);
        });
    }
}
