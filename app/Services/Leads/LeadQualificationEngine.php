<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadPriority;
use App\Enums\Leads\LeadQualificationStatus;
use App\Models\Lead;
use App\Models\LeadQualificationRule;
use App\Models\Tenant;

class LeadQualificationEngine
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{score: int, components: array<string, int>, qualification_status: LeadQualificationStatus, priority: LeadPriority}
     */
    public function score(Tenant $tenant, array $input): array
    {
        $rules = $this->rulesFor($tenant);
        $components = [];

        if (! empty(trim((string) ($input['full_name'] ?? '')))) {
            $components['contact_name'] = (int) ($rules['contact_name'] ?? 10);
        }

        if (! empty(trim((string) ($input['mobile'] ?? '')))) {
            $components['mobile_provided'] = (int) ($rules['mobile_provided'] ?? 15);
        }

        if (! empty(trim((string) ($input['email'] ?? '')))) {
            $components['email_provided'] = (int) ($rules['email_provided'] ?? 10);
        }

        if (! empty(trim((string) ($input['service_interest'] ?? '')))) {
            $components['service_selected'] = (int) ($rules['service_selected'] ?? 15);
        }

        if (! empty(trim((string) ($input['location'] ?? '')) || trim((string) ($input['state'] ?? '')))) {
            $components['location_provided'] = (int) ($rules['location_provided'] ?? 10);
        }

        if (! empty(trim((string) ($input['enquiry_summary'] ?? '')))) {
            $length = strlen(trim((string) $input['enquiry_summary']));
            $components['enquiry_detail'] = $length >= 80
                ? (int) ($rules['enquiry_detail_high'] ?? 15)
                : (int) ($rules['enquiry_detail_low'] ?? 8);
        }

        if (! empty($input['requested_human_contact'])) {
            $components['human_contact_requested'] = (int) ($rules['human_contact_requested'] ?? 20);
        }

        if (! empty($input['conversation_id'])) {
            $components['conversation_linked'] = (int) ($rules['conversation_linked'] ?? 5);
        }

        $score = (int) min(100, array_sum($components));

        return [
            'score' => $score,
            'components' => $components,
            'qualification_status' => $this->qualificationFromScore($score),
            'priority' => $this->priorityFromScore($score, $input),
        ];
    }

    public function applyToLead(Lead $lead, array $input): Lead
    {
        $result = $this->score($lead->tenant, array_merge($input, [
            'conversation_id' => $lead->conversation_id,
        ]));

        $lead->lead_score = $result['score'];
        $lead->score_components = $result['components'];
        $lead->qualification_status = $result['qualification_status'];
        $lead->priority = $result['priority'];

        return $lead;
    }

    /**
     * @return array<string, int>
     */
    private function rulesFor(Tenant $tenant): array
    {
        $stored = LeadQualificationRule::query()
            ->where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->value('rules');

        return is_array($stored) ? $stored : $this->defaultRules();
    }

    /**
     * @return array<string, int>
     */
    private function defaultRules(): array
    {
        return [
            'contact_name' => 10,
            'mobile_provided' => 15,
            'email_provided' => 10,
            'service_selected' => 15,
            'location_provided' => 10,
            'enquiry_detail_low' => 8,
            'enquiry_detail_high' => 15,
            'human_contact_requested' => 20,
            'conversation_linked' => 5,
        ];
    }

    private function qualificationFromScore(int $score): LeadQualificationStatus
    {
        return match (true) {
            $score >= 70 => LeadQualificationStatus::Qualified,
            $score >= 45 => LeadQualificationStatus::Potential,
            $score >= 20 => LeadQualificationStatus::InsufficientInformation,
            default => LeadQualificationStatus::NotReviewed,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function priorityFromScore(int $score, array $input): LeadPriority
    {
        if (! empty($input['requested_human_contact'])) {
            return LeadPriority::High;
        }

        return match (true) {
            $score >= 80 => LeadPriority::Urgent,
            $score >= 55 => LeadPriority::High,
            $score >= 30 => LeadPriority::Normal,
            default => LeadPriority::Low,
        };
    }
}
