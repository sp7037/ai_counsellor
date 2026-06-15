<?php

namespace App\Policies;

use App\Models\PaymentOrder;
use App\Models\User;

class PaymentOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function view(User $user, PaymentOrder $order): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->tenantRoleFor($order->tenant)?->canManageBilling() ?? false;
    }

    public function checkout(User $user, PaymentOrder $order): bool
    {
        return $user->tenantRoleFor($order->tenant)?->canManageBilling() ?? false;
    }
}
