<?php

namespace App\Enums\AI;

enum AiCredentialMode: string
{
    case TenantKeyRequired = 'tenant_key_required';
    case PlatformManaged = 'platform_managed';
    case TenantKeyWithExplicitPlatformFallback = 'tenant_key_with_explicit_platform_fallback';

    public function label(): string
    {
        return match ($this) {
            self::TenantKeyRequired => 'Tenant key required',
            self::PlatformManaged => 'Platform managed',
            self::TenantKeyWithExplicitPlatformFallback => 'Tenant key with explicit platform fallback',
        };
    }
}
