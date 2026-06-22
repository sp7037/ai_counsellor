<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\KnowledgeItem;
use App\Services\Knowledge\KnowledgeItemService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_create_knowledge_item(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Staff);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->set('title', 'FAQ one')
            ->set('body', 'Answer text')
            ->call('create')
            ->assertForbidden();

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_create_faq_draft_persists_tenant_id_without_tenant_context(): void
    {
        // Reproduces the production Livewire flow: the /livewire/update request
        // clears the tenant context, so the BelongsToTenant auto-fill does not run
        // and the service must set tenant_id from the trusted tenant itself.
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        app(TenantContext::class)->clear();

        $item = app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'No-context FAQ',
            'body' => 'Created without an enforced tenant context.',
        ], $user);

        $this->assertSame($tenant->id, $item->tenant_id);
        $this->assertSame(KnowledgeItemStatus::Draft, $item->status);
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $item->id,
            'tenant_id' => $tenant->id,
            'status' => KnowledgeItemStatus::Draft->value,
        ]);
    }

    public function test_admin_creates_faq_draft_through_livewire_without_preresolved_context(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);

        // Intentionally do NOT enforce tenant context, mirroring a Livewire update.
        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->set('type', 'faq')
            ->set('title', 'Visa requirements')
            ->set('body', 'Bring your passport and photos.')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_items', [
            'tenant_id' => $tenant->id,
            'title' => 'Visa requirements',
            'status' => KnowledgeItemStatus::Draft->value,
        ]);
    }

    public function test_publish_persists_version_tenant_id_without_tenant_context(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);

        $service = app(KnowledgeItemService::class);

        app(TenantContext::class)->clear();
        $item = $service->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Publishable',
            'body' => 'Publish me.',
        ], $user);

        app(TenantContext::class)->clear();
        $service->publish($item->fresh(), $user);

        $this->assertDatabaseHas('knowledge_versions', [
            'knowledge_item_id' => $item->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
        ]);
    }

    public function test_admin_can_publish_draft_via_list_action(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $item = app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'MBBS Abroad admission guidance',
            'body' => 'NEET qualified, budget 25 lakh, prefers Russia, documents ready.',
        ], $user);

        $this->assertSame(KnowledgeItemStatus::Draft, $item->status);

        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->call('publishItem', $item->uuid)
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame(KnowledgeItemStatus::Published, $item->status);
        $this->assertNotNull($item->current_version_id);
        $this->assertNotNull($item->published_at);
        $this->assertDatabaseHas('knowledge_items', [
            'id' => $item->id,
            'tenant_id' => $tenant->id,
            'status' => KnowledgeItemStatus::Published->value,
        ]);
        $this->assertDatabaseHas('knowledge_versions', [
            'knowledge_item_id' => $item->id,
            'tenant_id' => $tenant->id,
            'version_number' => 1,
        ]);
    }

    public function test_tenant_a_cannot_publish_tenant_b_knowledge_item(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB, 'user' => $userB] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->withTenantContext($userB, $tenantB);
        $itemB = app(KnowledgeItemService::class)->createDraft($tenantB, [
            'type' => 'faq',
            'title' => 'Tenant B secret',
            'body' => 'Should not be publishable by tenant A.',
        ], $userB);

        $this->actingAs($userA);
        // Clear context so the item is resolvable and the authorization policy
        // (not just the tenant query scope) is what blocks the cross-tenant publish.
        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenantA])
            ->call('publishItem', $itemB->uuid)
            ->assertForbidden();

        $this->assertDatabaseHas('knowledge_items', [
            'id' => $itemB->id,
            'tenant_id' => $tenantB->id,
            'status' => KnowledgeItemStatus::Draft->value,
        ]);
    }

    public function test_admin_can_edit_draft_knowledge_item(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $item = app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Original title',
            'body' => 'Original body',
        ], $user);

        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->call('select', $item->uuid)
            ->assertSet('editTitle', 'Original title')
            ->assertSet('editBody', 'Original body')
            ->set('editTitle', 'Updated title')
            ->set('editBody', 'Updated body content')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('Updated title', $item->draft_title);
        $this->assertStringContainsString('Updated body content', (string) $item->draft_body);
    }

    public function test_edit_loads_published_content_into_left_form(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $service = app(KnowledgeItemService::class);
        $item = $service->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'MBBS Abroad admission guidance',
            'body' => 'NEET status, budget, country preference, documents, risk factors.',
        ], $user);
        $service->publish($item, $user);

        $item->refresh();
        $item->update(['draft_body' => null]);

        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->call('select', $item->uuid)
            ->assertSet('editTitle', 'MBBS Abroad admission guidance')
            ->assertSet('editBody', 'NEET status, budget, country preference, documents, risk factors.');
    }

    public function test_admin_can_delete_knowledge_item_from_list(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $item = app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Delete me',
            'body' => 'Temporary content',
        ], $user);

        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->call('deleteItem', $item->uuid)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('knowledge_items', ['id' => $item->id]);
    }

    public function test_knowledge_items_list_is_searchable_and_tenant_scoped(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB, 'user' => $userB] = $this->createTenantWithMember(role: TenantRole::Admin);

        $service = app(KnowledgeItemService::class);

        $this->withTenantContext($userA, $tenantA);
        $service->createDraft($tenantA, ['type' => 'faq', 'title' => 'MBBS abroad guidance', 'body' => 'NEET budget country'], $userA);
        $service->createDraft($tenantA, ['type' => 'faq', 'title' => 'Visa appointment help', 'body' => 'passport documents'], $userA);

        $this->withTenantContext($userB, $tenantB);
        $service->createDraft($tenantB, ['type' => 'faq', 'title' => 'Tenant B private MBBS note', 'body' => 'confidential'], $userB);

        $this->actingAs($userA);
        app(TenantContext::class)->clear();

        Volt::test('tenant.knowledge.items', ['tenant' => $tenantA])
            ->assertSee('MBBS abroad guidance')
            ->assertSee('Visa appointment help')
            ->assertDontSee('Tenant B private MBBS note')
            ->set('search', 'MBBS')
            ->assertSee('MBBS abroad guidance')
            ->assertDontSee('Visa appointment help')
            ->assertDontSee('Tenant B private MBBS note');
    }

    public function test_draft_is_created_only_for_the_passed_tenant(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember(role: TenantRole::Owner);
        ['tenant' => $tenantB] = $this->createTenantWithMember(role: TenantRole::Owner);

        app(TenantContext::class)->clear();
        $item = app(KnowledgeItemService::class)->createDraft($tenantA, [
            'type' => 'faq',
            'title' => 'Tenant A only',
            'body' => 'Belongs to A.',
        ], $userA);

        $this->assertSame($tenantA->id, $item->tenant_id);
        $this->assertNotSame($tenantB->id, $item->tenant_id);
        $this->assertDatabaseMissing('knowledge_items', [
            'id' => $item->id,
            'tenant_id' => $tenantB->id,
        ]);
    }

    public function test_admin_can_create_publish_and_audit_knowledge_item(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->set('title', 'Visa FAQ')
            ->set('body', 'Bring your passport.')
            ->call('create')
            ->assertHasNoErrors();

        $item = KnowledgeItem::query()->first();
        $this->assertNotNull($item);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::KnowledgeCreated->value]);

        Volt::test('tenant.knowledge.items', ['tenant' => $tenant])
            ->call('select', $item->uuid)
            ->call('publish')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame(KnowledgeItemStatus::Published, $item->status);
        $this->assertDatabaseHas('knowledge_versions', ['knowledge_item_id' => $item->id, 'version_number' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::KnowledgePublished->value]);
    }

    public function test_republishing_creates_new_version(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $service = app(KnowledgeItemService::class);
        $item = $service->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Original',
            'body' => 'Body one',
        ], $user);
        $service->publish($item, $user);
        $service->updateDraft($item->fresh(), [
            'draft_title' => 'Updated',
            'draft_body' => 'Body two',
        ], $user);
        $service->publish($item->fresh(), $user);

        $this->assertDatabaseCount('knowledge_versions', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::KnowledgeVersionCreated->value]);
    }

    public function test_unsafe_script_content_is_stripped_from_knowledge_body(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $item = app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Safe title',
            'body' => '<script>alert(1)</script>Plain answer',
        ], $user);

        $this->assertStringNotContainsString('<script', (string) $item->draft_body);
        $this->assertStringContainsString('Plain answer', (string) $item->draft_body);
    }

    public function test_tenant_a_cannot_select_tenant_b_knowledge_item(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember(role: TenantRole::Owner);
        ['tenant' => $tenantB, 'user' => $userB] = $this->createTenantWithMember(role: TenantRole::Owner);

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($userB, $tenantB);
        app(TenantContext::class)->enforceIsolation();

        $itemB = app(KnowledgeItemService::class)->createDraft($tenantB, [
            'type' => 'faq',
            'title' => 'Secret',
            'body' => 'Hidden',
        ], $userB);

        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        Volt::test('tenant.knowledge.items', ['tenant' => $tenantA])
            ->call('select', $itemB->uuid)
            ->assertStatus(404);
    }

    public function test_draft_knowledge_is_not_returned_by_widget_search(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($user, $tenant);
        app(TenantContext::class)->enforceIsolation();

        app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Draft only title',
            'body' => 'Draft only body',
        ], $user);

        app(TenantContext::class)->clear();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $this->getJson('/widget/v1/knowledge/search?q=Draft', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_published_knowledge_is_returned_by_widget_search(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($user, $tenant);
        app(TenantContext::class)->enforceIsolation();

        $service = app(KnowledgeItemService::class);
        $item = $service->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Published visa FAQ',
            'body' => 'Bring passport and photos.',
        ], $user);
        $service->publish($item, $user);
        app(TenantContext::class)->clear();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $response = $this->getJson('/widget/v1/knowledge/search?q=passport', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertCount(1, $response->json('results'));
        $this->assertSame('Published visa FAQ', $response->json('results.0.title'));
        $this->assertStringNotContainsString('storage_path', (string) json_encode($response->json()));
    }

    public function test_document_upload_rejects_invalid_mime(): void
    {
        Storage::fake('local');
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        $file = UploadedFile::fake()->create('evil.php', 10, 'application/x-php');

        Volt::test('tenant.knowledge.documents', ['tenant' => $tenant])
            ->set('upload', $file)
            ->call('uploadDocument')
            ->assertHasErrors(['upload']);
    }

    public function test_production_rejects_knowledge_search_from_localhost_when_local_origins_disabled(): void
    {
        $this->app['env'] = 'production';
        config(['widget.allow_local_origins' => false]);

        ['key' => $key, 'domain' => $domain] = $this->createWidgetReadyTenant();
        $domain->delete();

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_archived_knowledge_is_not_searchable(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($user, $tenant);
        app(TenantContext::class)->enforceIsolation();

        $service = app(KnowledgeItemService::class);
        $item = $service->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Archived topic',
            'body' => 'No longer active',
        ], $user);
        $service->publish($item, $user);
        $service->archive($item->fresh(), $user);
        app(TenantContext::class)->clear();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $this->getJson('/widget/v1/knowledge/search?q=Archived', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('results', []);
    }
}
