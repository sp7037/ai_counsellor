<?php

namespace App\Services\Leads;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Tenant;

class LeadIdentityResolver
{
    public function normalizeMobile(?string $mobile): ?string
    {
        if ($mobile === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^[6-9]\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    public function findByMobile(Tenant $tenant, ?string $mobile): ?Lead
    {
        $normalized = $this->normalizeMobile($mobile);

        if ($normalized === null) {
            return null;
        }

        return Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('mobile')
            ->get()
            ->first(fn (Lead $lead) => $this->normalizeMobile($lead->mobile) === $normalized);
    }

    public function findByEmail(Tenant $tenant, ?string $email): ?Lead
    {
        $normalized = $this->normalizeEmail($email);

        if ($normalized === null) {
            return null;
        }

        return Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('email')
            ->get()
            ->first(fn (Lead $lead) => $this->normalizeEmail($lead->email) === $normalized);
    }

    public function resolve(
        Tenant $tenant,
        ?string $mobile = null,
        ?string $email = null,
        ?Conversation $conversation = null,
    ): ?Lead {
        $mobileLead = $this->findByMobile($tenant, $mobile);

        if ($mobileLead !== null) {
            return $mobileLead;
        }

        $emailLead = $this->findByEmail($tenant, $email);

        if ($emailLead !== null) {
            return $emailLead;
        }

        if ($conversation !== null) {
            $conversation->loadMissing('lead');

            return $conversation->lead;
        }

        return null;
    }

    public function linkConversation(Conversation $conversation, Lead $lead): void
    {
        if ($conversation->tenant_id !== $lead->tenant_id) {
            return;
        }

        Conversation::withoutGlobalScopes()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('id', $conversation->id)
            ->update(['lead_id' => $lead->id]);

        if (blank($lead->conversation_id)) {
            $lead->update(['conversation_id' => $conversation->id]);
        }
    }
}
