<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantNote;
use App\Policies\TenantMembershipPolicy;
use App\Policies\TenantNotePolicy;
use App\Policies\TenantPolicy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Tenant::class => TenantPolicy::class,
        TenantMembership::class => TenantMembershipPolicy::class,
        TenantNote::class => TenantNotePolicy::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
