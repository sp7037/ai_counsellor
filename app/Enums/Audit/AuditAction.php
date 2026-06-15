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
    case MembershipRemoved = 'membership.removed';
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
            self::MembershipRemoved => 'Membership removed',
            self::PlatformBypass => 'Platform bypass',
        };
    }
}
