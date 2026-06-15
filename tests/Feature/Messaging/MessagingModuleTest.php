<?php

namespace Tests\Feature\Messaging;

use App\Enums\Billing\PlanFeature;
use App\Enums\Conversations\ConversationChannel;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Enums\Leads\LeadSource;
use App\Enums\Messaging\MessageDeliveryState;
use App\Enums\Messaging\MessageDirection;
use App\Enums\Messaging\MessagingFailureCategory;
use App\Enums\Messaging\MessagingProvider;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Exceptions\Messaging\MessagingException;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\MessagingContact;
use App\Models\MessagingWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantMessagingIntegration;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Billing\EntitlementResolver;
use App\Services\Messaging\MessagingConversationService;
use App\Services\Messaging\MessagingCredentialService;
use App\Services\Messaging\MessagingIntegrationService;
use App\Services\Messaging\MessagingWebhookService;
use App\Services\Messaging\OutboundMessageService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MessagingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
        Http::fake();
    }

    public function test_tenant_admin_can_configure_integration_counsellor_denied_integration_page(): void
    {
        $admin = $this->createEnabledIntegration();
        $counsellor = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->actingAs($admin['user'])
            ->get(route('tenant.integrations.whatsapp', $admin['tenant']))
            ->assertOk()
            ->assertSee('WhatsApp integration');

        $this->actingAs($counsellor['user'])
            ->get(route('tenant.integrations.whatsapp', $counsellor['tenant']))
            ->assertForbidden();
    }

    public function test_access_token_encrypted_and_not_in_configure_response_html(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $token = 'wa-access-token-'.Str::random(16);
        $secret = (string) config('messaging.providers.fake.app_secret');

        $integration = app(MessagingIntegrationService::class)->configure($setup['tenant'], [
            'provider' => MessagingProvider::Fake->value,
            'phone_number_id' => 'phone_encrypt_test',
            'access_token' => $token,
            'app_secret' => $secret,
        ], $setup['user']);

        $stored = $integration->fresh()->access_token;
        $this->assertIsArray($stored);
        $this->assertArrayHasKey('encrypted', $stored);
        $this->assertNotSame($token, $stored['encrypted']);
        $this->assertSame($token, app(MessagingCredentialService::class)->accessToken($integration->fresh()));

        $this->actingAs($setup['user'])
            ->get(route('tenant.integrations.whatsapp', $setup['tenant']))
            ->assertOk()
            ->assertDontSee($token)
            ->assertSee('Configured', false);
    }

    public function test_blank_secret_preserves_existing_on_reconfigure(): void
    {
        $setup = $this->createEnabledIntegration([
            'app_secret' => 'original-app-secret-value',
        ]);
        $credentials = app(MessagingCredentialService::class);
        $service = app(MessagingIntegrationService::class);

        $this->assertSame('original-app-secret-value', $credentials->appSecret($setup['integration']));

        $service->configure($setup['tenant'], [
            'phone_number_id' => $setup['phone_number_id'],
            'app_secret' => '',
        ], $setup['user']);

        $this->assertSame('original-app-secret-value', $credentials->appSecret($setup['integration']->fresh()));
    }

    public function test_valid_webhook_get_verify_returns_challenge(): void
    {
        $setup = $this->createEnabledIntegration();
        $challenge = 'challenge_'.Str::random(12);

        $this->get(route('webhooks.messaging', ['provider' => 'fake']).'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $setup['integration']->verify_token,
            'hub_challenge' => $challenge,
        ]))
            ->assertOk()
            ->assertSee($challenge, false);
    }

    public function test_invalid_verify_token_returns_403(): void
    {
        $this->createEnabledIntegration();

        $this->get(route('webhooks.messaging', ['provider' => 'fake']).'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'invalid-token',
            'hub_challenge' => 'challenge',
        ]))
            ->assertForbidden();
    }

    public function test_valid_signed_webhook_creates_conversation_and_message(): void
    {
        $setup = $this->createEnabledIntegration();
        $messageId = 'wamid.inbound.'.Str::lower(Str::random(12));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Hello from WhatsApp');

        $this->postSignedWebhook($payload)
            ->assertOk();

        $this->assertSame(1, Conversation::query()->where('tenant_id', $setup['tenant']->id)->count());
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $setup['tenant']->id,
            'provider_message_id' => $messageId,
            'body' => 'Hello from WhatsApp',
            'direction' => MessageDirection::Inbound->value,
        ]);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $setup = $this->createEnabledIntegration();
        $payload = $this->inboundPayload($setup['phone_number_id'], 'wamid.bad', '919876543210', 'Hi');

        $this->postWebhook($payload, 'sha256=invalid')
            ->assertForbidden();
    }

    public function test_missing_signature_returns_403(): void
    {
        $setup = $this->createEnabledIntegration();
        $payload = $this->inboundPayload($setup['phone_number_id'], 'wamid.nosig', '919876543210', 'Hi');

        $this->postWebhook($payload)
            ->assertForbidden();
    }

    public function test_unknown_phone_number_id_returns_200_ignored(): void
    {
        $payload = $this->inboundPayload('unknown_phone_id', 'wamid.unknown', '919876543210', 'Hi');

        $this->postWebhook($payload, 'sha256=anything')
            ->assertOk()
            ->assertJson(['status' => 'ignored', 'reason' => 'unknown_integration']);
    }

    public function test_duplicate_webhook_event_ignored(): void
    {
        $setup = $this->createEnabledIntegration();
        $messageId = 'wamid.dupevent.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Once');

        $this->postSignedWebhook($payload)->assertOk();
        $response = $this->postSignedWebhook($payload)->assertOk();

        $response->assertJson(['status' => 'duplicate']);
        $this->assertSame(1, MessagingWebhookEvent::query()->count());
        $this->assertSame(1, Message::query()->where('provider_message_id', $messageId)->count());
    }

    public function test_duplicate_provider_message_ignored(): void
    {
        $setup = $this->createEnabledIntegration();
        $messageId = 'wamid.dupmsg.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Duplicate body');

        $this->postSignedWebhook($payload)->assertOk();
        MessagingWebhookEvent::query()->delete();

        $response = $this->postSignedWebhook($payload)->assertOk();

        $response->assertJsonPath('results.0.status', 'duplicate');
        $this->assertSame(1, Message::query()->where('provider_message_id', $messageId)->count());
    }

    public function test_cross_tenant_cannot_see_other_tenant_integration_config(): void
    {
        $tenantA = $this->createEnabledIntegration();
        $tenantB = $this->createTenantWithSubscription('professional');

        $this->actingAs($tenantB['user'])
            ->get(route('tenant.integrations.whatsapp', $tenantA['tenant']))
            ->assertForbidden();
    }

    public function test_inbound_creates_lead_with_whatsapp_source_when_lead_management_entitled(): void
    {
        $setup = $this->createEnabledIntegration();
        $this->configureTenantAi($setup['tenant'], $setup['user']);
        $messageId = 'wamid.lead.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919111122233', 'I need counselling help');

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $setup['tenant']->id,
            'source' => LeadSource::WhatsApp->value,
            'mobile' => '919111122233',
        ]);
        $this->assertSame(1, Lead::query()->where('tenant_id', $setup['tenant']->id)->count());
    }

    public function test_suspended_tenant_webhook_acknowledged_but_no_message_stored(): void
    {
        $setup = $this->createEnabledIntegration();
        $setup['tenant']->update(['status' => TenantStatus::Suspended->value, 'suspended_at' => now()]);

        $messageId = 'wamid.suspended.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Should not store');

        $response = $this->postSignedWebhook($payload)->assertOk();

        $response->assertJson([
            'status' => 'tenant_inactive',
            'handled' => false,
        ]);
        $this->assertSame(0, Message::query()->where('provider_message_id', $messageId)->count());
    }

    public function test_disabled_integration_does_not_process_inbound(): void
    {
        $setup = $this->createEnabledIntegration();
        app(MessagingIntegrationService::class)->disable($setup['tenant'], $setup['user']);

        $messageId = 'wamid.disabled.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Ignored');

        $response = $this->postSignedWebhook($payload)->assertOk();

        $decoded = json_decode($response->getContent(), true);
        $this->assertFalse($decoded['handled'] ?? true);
        $this->assertSame('integration_disabled', $decoded['status'] ?? null);
        $this->assertSame(0, Message::query()->where('provider_message_id', $messageId)->count());
    }

    public function test_ai_invoked_in_ai_mode_only_with_fake_provider(): void
    {
        $setup = $this->createEnabledIntegration();

        $this->assertTrue(
            app(EntitlementResolver::class)
                ->check($setup['tenant'], PlanFeature::AiResponses)
                ->isAllowed()
        );

        $messageId = 'wamid.ai.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Tell me about courses');

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertDatabaseHas('messaging_events', [
            'tenant_id' => $setup['tenant']->id,
            'event_type' => 'ai_generation_requested',
        ]);
        $this->assertTrue(
            AiRun::query()->where('tenant_id', $setup['tenant']->id)->exists(),
            'Expected an AI run to be created for WhatsApp inbound processing.',
        );
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $setup['tenant']->id,
            'role' => MessageRole::Assistant->value,
        ]);
    }

    public function test_ai_not_invoked_in_human_mode(): void
    {
        $setup = $this->createEnabledIntegration();
        $this->configureTenantAi($setup['tenant'], $setup['user']);

        $contact = app(MessagingConversationService::class)->findOrCreateContact(
            $setup['integration'],
            '919876543210',
            'Human Mode User',
        );
        $visitor = $this->createVisitor($setup['tenant']);
        Conversation::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'visitor_id' => $visitor->id,
            'messaging_integration_id' => $setup['integration']->id,
            'messaging_contact_id' => $contact->id,
            'channel' => ConversationChannel::WhatsApp->value,
            'status' => ConversationStatus::Open->value,
            'mode' => ConversationMode::Human->value,
            'external_channel_reference' => $contact->external_contact_id,
            'started_at' => now(),
        ]);

        $messageId = 'wamid.human.'.Str::lower(Str::random(8));
        $payload = $this->inboundPayload($setup['phone_number_id'], $messageId, '919876543210', 'Counsellor please');

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(0, AiRun::query()->where('tenant_id', $setup['tenant']->id)->count());
        $this->assertSame(0, Message::query()
            ->where('tenant_id', $setup['tenant']->id)
            ->where('role', MessageRole::Assistant->value)
            ->count());
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $setup['tenant']->id,
            'provider_message_id' => $messageId,
            'role' => MessageRole::Visitor->value,
        ]);
    }

    public function test_session_window_blocks_outbound_outside_24h_window(): void
    {
        $setup = $this->createEnabledIntegration();
        $contact = MessagingContact::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'messaging_integration_id' => $setup['integration']->id,
            'channel' => ConversationChannel::WhatsApp->value,
            'external_contact_id' => '919876543210',
            'display_phone' => '+919876543210',
            'last_inbound_at' => now()->subHours(25),
        ]);
        $visitor = $this->createVisitor($setup['tenant']);
        $conversation = Conversation::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'visitor_id' => $visitor->id,
            'messaging_integration_id' => $setup['integration']->id,
            'messaging_contact_id' => $contact->id,
            'channel' => ConversationChannel::WhatsApp->value,
            'status' => ConversationStatus::Open->value,
            'mode' => ConversationMode::Human->value,
            'external_channel_reference' => $contact->external_contact_id,
            'started_at' => now()->subDay(),
        ]);
        $message = Message::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Counsellor->value,
            'body' => 'Follow up reply',
            'direction' => MessageDirection::Outbound->value,
        ]);

        try {
            app(OutboundMessageService::class)->sendCounsellorReply($conversation->fresh(), $message);
            $this->fail('Expected session window MessagingException was not thrown.');
        } catch (MessagingException $exception) {
            $this->assertSame(MessagingFailureCategory::SessionWindowClosed, $exception->category);
        }
    }

    public function test_delivery_status_progression_sent_delivered_read(): void
    {
        $setup = $this->createEnabledIntegration();
        $conversation = $this->createWhatsAppConversation($setup);
        $providerMessageId = 'wamid.out.'.Str::lower(Str::random(10));

        $outbound = Message::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant->value,
            'body' => 'Outbound test',
            'direction' => MessageDirection::Outbound->value,
            'provider_message_id' => $providerMessageId,
            'delivery_state' => MessageDeliveryState::Submitted->value,
        ]);

        foreach (['sent', 'delivered', 'read'] as $status) {
            $payload = $this->statusPayload($setup['phone_number_id'], $providerMessageId, $status, '919876543210');
            $this->postSignedWebhook($payload)->assertOk();
            MessagingWebhookEvent::query()->delete();
        }

        $outbound->refresh();
        $this->assertSame(MessageDeliveryState::Read, $outbound->delivery_state);
    }

    public function test_stale_failure_cannot_downgrade_read(): void
    {
        $setup = $this->createEnabledIntegration();
        $conversation = $this->createWhatsAppConversation($setup);
        $providerMessageId = 'wamid.readfail.'.Str::lower(Str::random(10));

        $outbound = Message::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant->value,
            'body' => 'Already read',
            'direction' => MessageDirection::Outbound->value,
            'provider_message_id' => $providerMessageId,
            'delivery_state' => MessageDeliveryState::Read->value,
        ]);

        $payload = $this->statusPayload($setup['phone_number_id'], $providerMessageId, 'failed', '919876543210');
        $this->postSignedWebhook($payload)->assertOk();

        $outbound->refresh();
        $this->assertSame(MessageDeliveryState::Read, $outbound->delivery_state);
    }

    public function test_missing_whatsapp_entitlement_blocks_configure(): void
    {
        $setup = $this->createTenantWithSubscription('starter');

        $this->expectException(EntitlementDeniedException::class);

        app(MessagingIntegrationService::class)->configure($setup['tenant'], [
            'provider' => MessagingProvider::Fake->value,
            'phone_number_id' => 'phone_starter_blocked',
            'access_token' => 'token',
            'app_secret' => (string) config('messaging.providers.fake.app_secret'),
        ], $setup['user']);
    }

    public function test_integration_ui_pages_return_200_for_tenant_admin(): void
    {
        $setup = $this->createEnabledIntegration();

        $this->actingAs($setup['user'])
            ->get(route('tenant.integrations.index', $setup['tenant']))
            ->assertOk()
            ->assertSee('WhatsApp Business');

        $this->actingAs($setup['user'])
            ->get(route('tenant.integrations.whatsapp', $setup['tenant']))
            ->assertOk()
            ->assertSee('Connection settings');

        $this->actingAs($setup['user'])
            ->get(route('tenant.integrations.whatsapp.templates', $setup['tenant']))
            ->assertOk();

        $this->actingAs($setup['user'])
            ->get(route('tenant.integrations.whatsapp.events', $setup['tenant']))
            ->assertOk();
    }

    public function test_platform_integrations_index_returns_200_for_super_admin(): void
    {
        $setup = $this->createEnabledIntegration();
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.integrations.index'))
            ->assertOk()
            ->assertSee($setup['tenant']->name);
    }

    public function test_tenant_admin_can_save_integration_via_livewire(): void
    {
        $setup = $this->createTenantWithSubscription('professional');
        $this->actingAs($setup['user']);

        Volt::test('tenant.integrations.whatsapp', ['tenant' => $setup['tenant']])
            ->set('phone_number_id', 'phone_livewire_1')
            ->set('access_token', 'livewire-access-token')
            ->set('app_secret', (string) config('messaging.providers.fake.app_secret'))
            ->call('save')
            ->assertHasNoErrors();

        $integration = TenantMessagingIntegration::query()->where('tenant_id', $setup['tenant']->id)->first();
        $this->assertNotNull($integration);
        $this->assertSame('phone_livewire_1', $integration->phone_number_id);
        $this->assertTrue(app(MessagingCredentialService::class)->accessTokenConfigured($integration));
    }

    public function test_webhook_service_verify_challenge_directly(): void
    {
        $setup = $this->createEnabledIntegration();
        $challenge = 'direct-challenge';

        $response = app(MessagingWebhookService::class)->verifyChallenge(
            'subscribe',
            $setup['integration']->verify_token,
            $challenge,
        );

        $this->assertSame($challenge, $response);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     tenant: Tenant,
     *     user: User,
     *     membership: TenantMembership,
     *     subscription: Subscription,
     *     plan: Plan,
     *     integration: TenantMessagingIntegration,
     *     phone_number_id: string
     * }
     */
    private function createEnabledIntegration(array $overrides = []): array
    {
        $setup = $this->createTenantWithSubscription('professional');
        $phoneNumberId = (string) ($overrides['phone_number_id'] ?? 'phone_'.Str::lower(Str::random(10)));
        $accessToken = (string) ($overrides['access_token'] ?? 'fake_access_'.Str::random(12));
        $appSecret = (string) ($overrides['app_secret'] ?? config('messaging.providers.fake.app_secret'));

        $service = app(MessagingIntegrationService::class);

        $integration = $service->configure($setup['tenant'], array_merge([
            'provider' => MessagingProvider::Fake->value,
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
            'app_secret' => $appSecret,
        ], $overrides), $setup['user']);

        $integration = $service->enable($setup['tenant'], $setup['user']);

        return array_merge($setup, [
            'integration' => $integration->fresh(),
            'phone_number_id' => $phoneNumberId,
        ]);
    }

    private function signPayload(string $rawBody, ?string $secret = null): string
    {
        $secret ??= (string) config('messaging.providers.fake.app_secret');

        return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
    }

    private function inboundPayload(string $phoneNumberId, string $messageId, string $from, string $body): string
    {
        return json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15550001111',
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Test User'],
                            'wa_id' => MessagingConversationService::normalizePhone($from),
                        ]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    private function statusPayload(string $phoneNumberId, string $messageId, string $status, string $recipientId): string
    {
        return json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'statuses' => [[
                            'id' => $messageId,
                            'status' => $status,
                            'timestamp' => (string) time(),
                            'recipient_id' => $recipientId,
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{tenant: Tenant, user: User, integration: TenantMessagingIntegration, phone_number_id: string}
     */
    private function createWhatsAppConversation(array $setup): Conversation
    {
        $contact = MessagingContact::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'messaging_integration_id' => $setup['integration']->id,
            'channel' => ConversationChannel::WhatsApp->value,
            'external_contact_id' => '919876543210',
            'display_phone' => '+919876543210',
            'last_inbound_at' => now(),
        ]);
        $visitor = $this->createVisitor($setup['tenant']);

        return Conversation::query()->create([
            'tenant_id' => $setup['tenant']->id,
            'visitor_id' => $visitor->id,
            'messaging_integration_id' => $setup['integration']->id,
            'messaging_contact_id' => $contact->id,
            'channel' => ConversationChannel::WhatsApp->value,
            'status' => ConversationStatus::Open->value,
            'mode' => ConversationMode::Ai->value,
            'external_channel_reference' => $contact->external_contact_id,
            'started_at' => now(),
        ]);
    }

    private function createVisitor(Tenant $tenant): Visitor
    {
        return Visitor::query()->create([
            'tenant_id' => $tenant->id,
            'fingerprint_hash' => hash('sha256', 'test:'.Str::random(12)),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function postSignedWebhook(string $payload, ?string $secret = null): TestResponse
    {
        return $this->postWebhook($payload, $this->signPayload($payload, $secret));
    }

    private function postWebhook(string $payload, ?string $signature = null): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_X-Hub-Signature-256'] = $signature;
        }

        return $this->call(
            'POST',
            route('webhooks.messaging', ['provider' => 'fake']),
            server: $server,
            content: $payload,
        );
    }
}
