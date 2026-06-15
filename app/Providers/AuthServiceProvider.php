<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\TenantNote;
use App\Models\WidgetKey;
use App\Policies\ConversationPolicy;
use App\Policies\TenantDomainPolicy;
use App\Policies\TenantMembershipPolicy;
use App\Policies\TenantNotePolicy;
use App\Policies\TenantPolicy;
use App\Policies\TenantWidgetSettingsPolicy;
use App\Policies\WidgetKeyPolicy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Tenant::class => TenantPolicy::class,
        TenantMembership::class => TenantMembershipPolicy::class,
        TenantNote::class => TenantNotePolicy::class,
        WidgetKey::class => WidgetKeyPolicy::class,
        TenantDomain::class => TenantDomainPolicy::class,
        Conversation::class => ConversationPolicy::class,
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

        Gate::define('manageWidgetSettings', [TenantWidgetSettingsPolicy::class, 'update']);
        Gate::define('viewWidgetSettings', [TenantWidgetSettingsPolicy::class, 'view']);
    }
}
