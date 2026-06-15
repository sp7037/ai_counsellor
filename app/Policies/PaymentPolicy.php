<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->tenantRoleFor($payment->tenant)?->canManageBilling() ?? false;
    }
}
