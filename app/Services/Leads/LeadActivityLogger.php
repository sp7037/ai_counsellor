<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;

class LeadActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        Lead $lead,
        LeadActivityType $type,
        ?User $actor = null,
        array $metadata = [],
        ?array $previous = null,
        ?array $new = null,
    ): LeadActivity {
        return LeadActivity::query()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'actor_user_id' => $actor?->id,
            'action_type' => $type->value,
            'metadata' => $metadata === [] ? null : $metadata,
            'previous_values' => $previous,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }
}
