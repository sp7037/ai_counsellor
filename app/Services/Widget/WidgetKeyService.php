<?php

namespace App\Services\Widget;

use App\Enums\Audit\AuditAction;
use App\Enums\Widget\WidgetKeyStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WidgetKey;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WidgetKeyService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Tenant $tenant, string $name, User $actor): WidgetKey
    {
        return DB::transaction(function () use ($tenant, $name, $actor): WidgetKey {
            $key = WidgetKey::query()->create([
                'tenant_id' => $tenant->id,
                'public_key' => $this->generatePublicKey(),
                'name' => $name,
                'status' => WidgetKeyStatus::Active->value,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(
                AuditAction::WidgetKeyCreated,
                $key,
                $tenant->id,
                ['public_key' => $key->public_key, 'name' => $key->name],
                $actor,
            );

            return $key;
        });
    }

    public function rotate(WidgetKey $key, User $actor): WidgetKey
    {
        return DB::transaction(function () use ($key, $actor): WidgetKey {
            $before = ['public_key' => $key->public_key, 'status' => $key->status->value];

            $key->update([
                'status' => WidgetKeyStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

            $replacement = WidgetKey::query()->create([
                'tenant_id' => $key->tenant_id,
                'public_key' => $this->generatePublicKey(),
                'name' => $key->name,
                'status' => WidgetKeyStatus::Active->value,
                'last_rotated_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(
                AuditAction::WidgetKeyRotated,
                $replacement,
                $key->tenant_id,
                [
                    'before' => $before,
                    'after' => ['public_key' => $replacement->public_key, 'status' => $replacement->status->value],
                    'revoked_key_uuid' => $key->uuid,
                ],
                $actor,
            );

            return $replacement;
        });
    }

    public function revoke(WidgetKey $key, User $actor): WidgetKey
    {
        return DB::transaction(function () use ($key, $actor): WidgetKey {
            $before = ['status' => $key->status->value];

            $key->update([
                'status' => WidgetKeyStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

            $this->auditLogger->log(
                AuditAction::WidgetKeyRevoked,
                $key,
                $key->tenant_id,
                ['before' => $before, 'after' => ['status' => $key->status->value]],
                $actor,
            );

            return $key->fresh();
        });
    }

    private function generatePublicKey(): string
    {
        do {
            $key = 'wk_'.Str::lower(Str::random(32));
        } while (WidgetKey::query()->where('public_key', $key)->exists());

        return $key;
    }
}
