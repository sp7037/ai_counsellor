<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadSource;
use App\Models\Lead;
use App\Models\WidgetSession;

class LeadCaptureService
{
    public function __construct(
        private readonly LeadCreationService $creation,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function captureFromWidget(WidgetSession $session, array $input, ?string $captureEventUuid = null): Lead
    {
        return $this->creation->create(
            $session->tenant,
            LeadSource::WidgetForm,
            array_merge($input, [
                'conversation_id' => $session->conversation_id,
                'requested_human_contact' => true,
            ]),
            captureEventUuid: $captureEventUuid,
            sourceReference: $captureEventUuid ? 'capture:'.$captureEventUuid : null,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function captureFromOfflineIntake(WidgetSession $session, array $input, string $messageUuid): Lead
    {
        return $this->creation->create(
            $session->tenant,
            LeadSource::OfflineIntake,
            array_merge($input, [
                'conversation_id' => $session->conversation_id,
                'enquiry_summary' => $input['enquiry_summary'] ?? null,
                'requested_human_contact' => true,
            ]),
            captureEventUuid: $messageUuid,
            sourceReference: 'offline:'.$messageUuid,
        );
    }
}
