<?php

namespace App\Services\Messaging;

use App\Enums\Conversations\ConversationChannel;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Messaging\MessagingEventType;
use App\Models\Conversation;
use App\Models\MessagingContact;
use App\Models\Tenant;
use App\Models\TenantMessagingIntegration;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

class MessagingConversationService
{
    public function __construct(
        private readonly MessagingEventRecorder $events,
    ) {}

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public static function visitorFingerprint(string $normalizedPhone): string
    {
        return 'whatsapp:'.$normalizedPhone;
    }

    public function findOrCreateContact(
        TenantMessagingIntegration $integration,
        string $phone,
        ?string $displayName = null,
        ?string $providerContactId = null,
    ): MessagingContact {
        $normalized = self::normalizePhone($phone);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Phone number is required.');
        }

        return MessagingContact::query()->firstOrCreate(
            [
                'messaging_integration_id' => $integration->id,
                'external_contact_id' => $normalized,
            ],
            [
                'tenant_id' => $integration->tenant_id,
                'channel' => ConversationChannel::WhatsApp->value,
                'display_phone' => $phone,
                'display_name' => $displayName,
                'provider_contact_id' => $providerContactId,
            ],
        );
    }

    /**
     * @return array{conversation: Conversation, created: bool}
     */
    public function findOrCreateConversation(
        TenantMessagingIntegration $integration,
        MessagingContact $contact,
    ): array {
        $existing = Conversation::query()
            ->where('tenant_id', $integration->tenant_id)
            ->where('messaging_contact_id', $contact->id)
            ->where('channel', ConversationChannel::WhatsApp->value)
            ->where('status', ConversationStatus::Open->value)
            ->first();

        if ($existing !== null) {
            return ['conversation' => $existing, 'created' => false];
        }

        $conversation = DB::transaction(function () use ($integration, $contact): Conversation {
            $visitor = $this->findOrCreateVisitor($integration->tenant, $contact);

            $conversation = Conversation::query()->create([
                'tenant_id' => $integration->tenant_id,
                'visitor_id' => $visitor->id,
                'messaging_integration_id' => $integration->id,
                'messaging_contact_id' => $contact->id,
                'channel' => ConversationChannel::WhatsApp->value,
                'status' => ConversationStatus::Open->value,
                'mode' => ConversationMode::Ai->value,
                'external_channel_reference' => $contact->external_contact_id,
                'started_at' => now(),
            ]);

            $this->events->record(
                MessagingEventType::ConversationCreated,
                $integration,
                $conversation,
                metadata: ['contact_uuid' => $contact->uuid],
            );

            return $conversation;
        });

        return ['conversation' => $conversation, 'created' => true];
    }

    private function findOrCreateVisitor(Tenant $tenant, MessagingContact $contact): Visitor
    {
        $fingerprint = self::visitorFingerprint($contact->external_contact_id);
        $hash = hash('sha256', $fingerprint);

        $visitor = Visitor::query()
            ->where('tenant_id', $tenant->id)
            ->where('fingerprint_hash', $hash)
            ->first();

        if ($visitor !== null) {
            $visitor->update(['last_seen_at' => now()]);

            return $visitor;
        }

        return Visitor::query()->create([
            'tenant_id' => $tenant->id,
            'fingerprint_hash' => $hash,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
