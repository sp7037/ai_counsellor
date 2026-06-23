<?php

namespace Tests\Feature;

use App\Enums\Leads\LeadActivityType;
use App\Enums\Leads\LeadSource;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Visitor;
use App\Services\Leads\ChatLeadExtractionService;
use App\Services\Leads\LeadCreationService;
use App\Services\Leads\LeadIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadIdentityMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_indian_mobile_variants(): void
    {
        $resolver = app(LeadIdentityResolver::class);

        $this->assertSame('9876543210', $resolver->normalizeMobile('9876543210'));
        $this->assertSame('9876543210', $resolver->normalizeMobile('+919876543210'));
        $this->assertSame('9876543210', $resolver->normalizeMobile('919876543210'));
        $this->assertSame('9876543210', $resolver->normalizeMobile('09876543210'));
        $this->assertNull($resolver->normalizeMobile('12345'));
        $this->assertNull($resolver->normalizeMobile(null));
    }

    public function test_normalizes_email_for_matching(): void
    {
        $resolver = app(LeadIdentityResolver::class);

        $this->assertSame('rahul@example.com', $resolver->normalizeEmail('  Rahul@Example.COM '));
        $this->assertNull($resolver->normalizeEmail('not-an-email'));
    }

    public function test_same_tenant_mobile_reuses_existing_lead(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();

        $existing = app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Existing Lead',
            'mobile' => '9876543210',
        ]);

        $matched = app(LeadIdentityResolver::class)->findByMobile($tenant, '9876543210');

        $this->assertNotNull($matched);
        $this->assertSame($existing->id, $matched->id);
    }

    public function test_mobile_variants_match_same_lead(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();
        $resolver = app(LeadIdentityResolver::class);

        app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Existing Lead',
            'mobile' => '9876543210',
        ]);

        foreach (['+919876543210', '919876543210', '09876543210'] as $variant) {
            $matched = $resolver->findByMobile($tenant, $variant);
            $this->assertNotNull($matched, "Failed for variant: {$variant}");
            $this->assertSame('9876543210', $matched->mobile);
        }
    }

    public function test_same_tenant_email_matches_case_insensitively(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();

        app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Email Lead',
            'email' => 'student@example.com',
        ]);

        $matched = app(LeadIdentityResolver::class)->findByEmail($tenant, 'STUDENT@EXAMPLE.COM');

        $this->assertNotNull($matched);
        $this->assertSame('student@example.com', $matched->email);
    }

    public function test_tenant_a_mobile_does_not_match_tenant_b_lead(): void
    {
        ['tenant' => $tenantA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB] = $this->createWidgetReadyTenant();

        app(LeadCreationService::class)->create($tenantA, LeadSource::Manual, [
            'full_name' => 'Tenant A Lead',
            'mobile' => '9876543210',
        ]);

        $this->assertNull(app(LeadIdentityResolver::class)->findByMobile($tenantB, '9876543210'));
    }

    public function test_widget_chat_updates_existing_lead_instead_of_creating_duplicate(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Visitor',
            'mobile' => '9876543210',
            'metadata' => ['neet_score' => '450'],
        ]);

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $this->postJson('/widget/v1/messages', [
            'body' => 'I am planning MBBS abroad. My name is Rahul Sharma and my mobile number is 9876543210',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame(1, Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Rahul Sharma', $lead->full_name);
        $this->assertSame('9876543210', $lead->mobile);
        $this->assertSame('450', $lead->metadata['neet_score'] ?? null);

        $conversation = Conversation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($lead->id, $conversation->lead_id);
    }

    public function test_existing_good_name_is_not_overwritten_by_weak_text(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();

        $lead = app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Rahul Sharma',
            'mobile' => '9876543210',
        ]);

        $conversation = $this->createConversationForTenant($tenant, $lead->id);

        app(ChatLeadExtractionService::class)->processMessage(
            $tenant,
            $conversation,
            'I am planning MBBS abroad',
        );

        $this->assertSame('Rahul Sharma', $lead->fresh()->full_name);
    }

    public function test_better_real_name_replaces_visitor_name(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();

        $lead = app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Visitor',
            'mobile' => '9876543210',
        ]);

        $conversation = $this->createConversationForTenant($tenant, $lead->id);

        app(ChatLeadExtractionService::class)->processMessage(
            $tenant,
            $conversation,
            'My name is Rahul Sharma and my mobile number is 9876543210',
        );

        $this->assertSame('Rahul Sharma', $lead->fresh()->full_name);
    }

    public function test_metadata_merges_without_deleting_previous_values(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();

        $lead = app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Rahul Sharma',
            'mobile' => '9876543210',
            'metadata' => ['neet_score' => '450', 'budget' => '25 lakh'],
        ]);

        $conversation = $this->createConversationForTenant($tenant, $lead->id);

        app(ChatLeadExtractionService::class)->processMessage(
            $tenant,
            $conversation,
            'I am open to suggestions and targeting 2026 intake',
        );

        $metadata = $lead->fresh()->metadata;
        $this->assertSame('450', $metadata['neet_score'] ?? null);
        $this->assertSame('25 lakh', $metadata['budget'] ?? null);
        $this->assertSame('open_to_suggestions', $metadata['country_preference'] ?? null);
    }

    public function test_identity_match_activity_is_logged(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $existing = app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Visitor',
            'mobile' => '9876543210',
        ]);

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $this->postJson('/widget/v1/messages', [
            'body' => 'My name is Rahul Sharma and my mobile number is 9876543210',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertTrue(
            LeadActivity::query()
                ->where('lead_id', $existing->id)
                ->where('action_type', LeadActivityType::IdentityMatched->value)
                ->exists()
        );
    }

    public function test_no_match_when_mobile_and_email_are_invalid_or_absent(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();
        $resolver = app(LeadIdentityResolver::class);

        $this->assertNull($resolver->findByMobile($tenant, '123'));
        $this->assertNull($resolver->findByEmail($tenant, 'invalid'));
        $this->assertNull($resolver->resolve($tenant));
    }

    public function test_widget_form_capture_reuses_existing_mobile_lead(): void
    {
        $result = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($result['key']);

        app(LeadCreationService::class)->create($result['tenant'], LeadSource::Manual, [
            'full_name' => 'Form Lead',
            'mobile' => '9123456789',
            'metadata' => ['source_channel' => 'website'],
        ]);

        $this->postJson('/widget/v1/leads', [
            'full_name' => 'Rahul Sharma',
            'mobile' => '+919123456789',
            'enquiry_summary' => 'Need MBBS counselling',
            'capture_event_uuid' => (string) str()->uuid(),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $this->assertSame(1, Lead::withoutGlobalScopes()->where('tenant_id', $result['tenant']->id)->count());

        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $result['tenant']->id)->firstOrFail();
        $this->assertSame('9123456789', $lead->mobile);
        $this->assertSame('Form Lead', $lead->full_name);
        $this->assertSame('website', $lead->metadata['source_channel'] ?? null);
        $this->assertStringContainsString('Need MBBS counselling', (string) $lead->enquiry_summary);
    }

    private function createConversationForTenant($tenant, ?int $leadId = null): Conversation
    {
        $visitor = Visitor::query()->create([
            'tenant_id' => $tenant->id,
            'fingerprint_hash' => hash('sha256', (string) str()->uuid()),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'uuid' => (string) str()->uuid(),
            'lead_id' => $leadId,
            'status' => 'open',
            'mode' => 'ai',
            'started_at' => now(),
        ]);
    }
}
