<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if (! $context->isIsolationEnforced()) {
                return;
            }

            $tenantId = $context->tenantId();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if (! $context->isIsolationEnforced()) {
                return;
            }

            $tenantId = $context->tenantId();

            if ($tenantId === null) {
                throw new \RuntimeException('Cannot create tenant-owned records without an active tenant context.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                $model->setAttribute('tenant_id', $model->getOriginal('tenant_id'));
            }
        });
    }
}
