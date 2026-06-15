<?php

namespace App\Enums\Audit;

enum AuditAction: string
{
    case TenantCreated = 'tenant.created';
    case TenantActivated = 'tenant.activated';
    case TenantSuspended = 'tenant.suspended';
    case TenantReactivated = 'tenant.reactivated';
    case MembershipCreated = 'membership.created';
    case MembershipRoleChanged = 'membership.role_changed';
    case MembershipStatusChanged = 'membership.status_changed';
    case MembershipRemoved = 'membership.removed';
    case WidgetKeyCreated = 'widget_key.created';
    case WidgetKeyRotated = 'widget_key.rotated';
    case WidgetKeyRevoked = 'widget_key.revoked';
    case TenantDomainCreated = 'tenant_domain.created';
    case TenantDomainVerified = 'tenant_domain.verified';
    case TenantDomainRemoved = 'tenant_domain.removed';
    case ConfigurationUpdated = 'configuration.updated';
    case BrandingUpdated = 'branding.updated';
    case LogoUpdated = 'logo.updated';
    case LogoRemoved = 'logo.removed';
    case OfficeHoursUpdated = 'office_hours.updated';
    case ServiceCreated = 'service.created';
    case ServiceUpdated = 'service.updated';
    case ServiceActivated = 'service.activated';
    case ServiceDeactivated = 'service.deactivated';
    case ServiceRemoved = 'service.removed';
    case CourseCreated = 'course.created';
    case CourseUpdated = 'course.updated';
    case CourseActivated = 'course.activated';
    case CourseDeactivated = 'course.deactivated';
    case CourseRemoved = 'course.removed';
    case InstitutionCreated = 'institution.created';
    case InstitutionUpdated = 'institution.updated';
    case InstitutionActivated = 'institution.activated';
    case InstitutionDeactivated = 'institution.deactivated';
    case InstitutionRemoved = 'institution.removed';
    case LocationCreated = 'location.created';
    case LocationUpdated = 'location.updated';
    case LocationActivated = 'location.activated';
    case LocationDeactivated = 'location.deactivated';
    case LocationRemoved = 'location.removed';
    case KnowledgeCreated = 'knowledge.created';
    case KnowledgeUpdated = 'knowledge.updated';
    case KnowledgePublished = 'knowledge.published';
    case KnowledgeVersionCreated = 'knowledge.version_created';
    case KnowledgeArchived = 'knowledge.archived';
    case KnowledgeDeleted = 'knowledge.deleted';
    case KnowledgeSourceUploaded = 'knowledge.source_uploaded';
    case KnowledgeSourceRemoved = 'knowledge.source_removed';
    case FeeCreated = 'fee.created';
    case FeeUpdated = 'fee.updated';
    case FeeArchived = 'fee.archived';
    case EligibilityCreated = 'eligibility.created';
    case EligibilityUpdated = 'eligibility.updated';
    case EligibilityArchived = 'eligibility.archived';
    case CourseInstitutionCreated = 'course_institution.created';
    case CourseInstitutionUpdated = 'course_institution.updated';
    case AiConfigurationUpdated = 'ai.configuration_updated';
    case AiSecretReplaced = 'ai.secret_replaced';
    case PlatformBypass = 'platform.bypass';

    public function label(): string
    {
        return match ($this) {
            self::TenantCreated => 'Tenant created',
            self::TenantActivated => 'Tenant activated',
            self::TenantSuspended => 'Tenant suspended',
            self::TenantReactivated => 'Tenant reactivated',
            self::MembershipCreated => 'Membership created',
            self::MembershipRoleChanged => 'Membership role changed',
            self::MembershipStatusChanged => 'Membership status changed',
            self::MembershipRemoved => 'Membership removed',
            self::WidgetKeyCreated => 'Widget key created',
            self::WidgetKeyRotated => 'Widget key rotated',
            self::WidgetKeyRevoked => 'Widget key revoked',
            self::TenantDomainCreated => 'Tenant domain created',
            self::TenantDomainVerified => 'Tenant domain verified',
            self::TenantDomainRemoved => 'Tenant domain removed',
            self::ConfigurationUpdated => 'Configuration updated',
            self::BrandingUpdated => 'Branding updated',
            self::LogoUpdated => 'Logo updated',
            self::LogoRemoved => 'Logo removed',
            self::OfficeHoursUpdated => 'Office hours updated',
            self::ServiceCreated => 'Service created',
            self::ServiceUpdated => 'Service updated',
            self::ServiceActivated => 'Service activated',
            self::ServiceDeactivated => 'Service deactivated',
            self::ServiceRemoved => 'Service removed',
            self::CourseCreated => 'Course created',
            self::CourseUpdated => 'Course updated',
            self::CourseActivated => 'Course activated',
            self::CourseDeactivated => 'Course deactivated',
            self::CourseRemoved => 'Course removed',
            self::InstitutionCreated => 'Institution created',
            self::InstitutionUpdated => 'Institution updated',
            self::InstitutionActivated => 'Institution activated',
            self::InstitutionDeactivated => 'Institution deactivated',
            self::InstitutionRemoved => 'Institution removed',
            self::LocationCreated => 'Location created',
            self::LocationUpdated => 'Location updated',
            self::LocationActivated => 'Location activated',
            self::LocationDeactivated => 'Location deactivated',
            self::LocationRemoved => 'Location removed',
            self::KnowledgeCreated => 'Knowledge created',
            self::KnowledgeUpdated => 'Knowledge updated',
            self::KnowledgePublished => 'Knowledge published',
            self::KnowledgeVersionCreated => 'Knowledge version created',
            self::KnowledgeArchived => 'Knowledge archived',
            self::KnowledgeDeleted => 'Knowledge deleted',
            self::KnowledgeSourceUploaded => 'Knowledge source uploaded',
            self::KnowledgeSourceRemoved => 'Knowledge source removed',
            self::FeeCreated => 'Fee created',
            self::FeeUpdated => 'Fee updated',
            self::FeeArchived => 'Fee archived',
            self::EligibilityCreated => 'Eligibility created',
            self::EligibilityUpdated => 'Eligibility updated',
            self::EligibilityArchived => 'Eligibility archived',
            self::CourseInstitutionCreated => 'Course institution created',
            self::CourseInstitutionUpdated => 'Course institution updated',
            self::AiConfigurationUpdated => 'AI configuration updated',
            self::AiSecretReplaced => 'AI secret replaced',
            self::PlatformBypass => 'Platform bypass',
        };
    }
}
