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
            self::PlatformBypass => 'Platform bypass',
        };
    }
}
