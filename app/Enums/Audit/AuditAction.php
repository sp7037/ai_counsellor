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
            self::PlatformBypass => 'Platform bypass',
        };
    }
}
