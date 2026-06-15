<?php

namespace App\Providers;

use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\CourseInstitution;
use App\Models\EligibilityRule;
use App\Models\Institution;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeFee;
use App\Models\KnowledgeItem;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantAiConfig;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\TenantNote;
use App\Models\WidgetKey;
use App\Policies\AiRunPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CourseInstitutionPolicy;
use App\Policies\CoursePolicy;
use App\Policies\EligibilityRulePolicy;
use App\Policies\InstitutionPolicy;
use App\Policies\KnowledgeDocumentPolicy;
use App\Policies\KnowledgeFeePolicy;
use App\Policies\KnowledgeItemPolicy;
use App\Policies\LocationPolicy;
use App\Policies\ServicePolicy;
use App\Policies\TenantAiConfigPolicy;
use App\Policies\TenantConfigurationPolicy;
use App\Policies\TenantDomainPolicy;
use App\Policies\TenantKnowledgePolicy;
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
        TenantAiConfig::class => TenantAiConfigPolicy::class,
        TenantMembership::class => TenantMembershipPolicy::class,
        TenantNote::class => TenantNotePolicy::class,
        WidgetKey::class => WidgetKeyPolicy::class,
        TenantDomain::class => TenantDomainPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Service::class => ServicePolicy::class,
        Course::class => CoursePolicy::class,
        Institution::class => InstitutionPolicy::class,
        Location::class => LocationPolicy::class,
        KnowledgeItem::class => KnowledgeItemPolicy::class,
        KnowledgeFee::class => KnowledgeFeePolicy::class,
        EligibilityRule::class => EligibilityRulePolicy::class,
        KnowledgeDocument::class => KnowledgeDocumentPolicy::class,
        CourseInstitution::class => CourseInstitutionPolicy::class,
        AiRun::class => AiRunPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
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
        Gate::define('viewTenantConfiguration', [TenantConfigurationPolicy::class, 'viewAny']);
        Gate::define('manageTenantConfiguration', [TenantConfigurationPolicy::class, 'manage']);
        Gate::define('viewTenantKnowledge', [TenantKnowledgePolicy::class, 'viewAny']);
        Gate::define('manageTenantKnowledge', [TenantKnowledgePolicy::class, 'manage']);
        Gate::define('viewTenantAiConfiguration', [TenantAiConfigPolicy::class, 'viewAny']);
    }
}
