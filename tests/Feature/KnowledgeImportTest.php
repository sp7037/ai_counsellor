<?php

namespace Tests\Feature;

use App\Enums\Billing\PlanFeature;
use App\Enums\Knowledge\KnowledgeImportRowStatus;
use App\Enums\Knowledge\KnowledgeImportStatus;
use App\Enums\Knowledge\KnowledgeImportType;
use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\KnowledgeImport;
use App\Models\KnowledgeItem;
use App\Services\Billing\EntitlementOverrideService;
use App\Services\Knowledge\KnowledgeImportService;
use App\Services\Knowledge\KnowledgeItemService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class KnowledgeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_import_valid_faq_csv_as_draft(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $import = $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'What is NEET?', 'answer' => 'National eligibility exam.', 'status' => 'draft'],
        ]));

        $this->assertSame(1, $import->valid_rows);

        $result = app(KnowledgeImportService::class)->execute($import, $tenant, $user);

        $this->assertSame(KnowledgeImportStatus::Completed, $result->status);
        $this->assertSame(1, $result->imported_rows);
        $this->assertDatabaseHas('knowledge_items', [
            'tenant_id' => $tenant->id,
            'title' => 'What is NEET?',
            'status' => KnowledgeItemStatus::Draft->value,
        ]);
    }

    public function test_tenant_can_import_valid_faq_csv_as_published(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $import = $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'Published import FAQ', 'answer' => 'This should be searchable.', 'status' => 'published'],
        ]));

        app(KnowledgeImportService::class)->execute($import, $tenant, $user);

        $this->assertDatabaseHas('knowledge_items', [
            'tenant_id' => $tenant->id,
            'title' => 'Published import FAQ',
            'status' => KnowledgeItemStatus::Published->value,
        ]);
        $this->assertDatabaseHas('knowledge_versions', [
            'tenant_id' => $tenant->id,
            'title' => 'Published import FAQ',
        ]);
    }

    public function test_draft_imported_faq_is_ignored_by_ai_retrieval(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        $import = $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'Draft import only', 'answer' => 'Hidden from AI.', 'status' => 'draft'],
        ]));

        app(KnowledgeImportService::class)->execute($import, $tenant, $user);

        $token = $this->widgetSessionToken($key);

        $this->getJson('/widget/v1/knowledge/search?q=Draft import only', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_published_imported_faq_is_used_by_ai_retrieval(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        $import = $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'Imported visa FAQ', 'answer' => 'Carry passport and admission letter.', 'status' => 'published'],
        ]));

        app(KnowledgeImportService::class)->execute($import, $tenant, $user);

        $token = $this->widgetSessionToken($key);

        $response = $this->getJson('/widget/v1/knowledge/search?q=Imported visa', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertNotEmpty($response->json('results'));
        $this->assertStringContainsString('Imported visa FAQ', (string) collect($response->json('results'))->pluck('title')->first());
    }

    public function test_duplicate_faq_is_skipped_during_validation(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        app(TenantContext::class)->clear();
        app(KnowledgeItemService::class)->createDraft($tenant, [
            'type' => 'faq',
            'title' => 'Existing FAQ',
            'body' => 'Already here.',
        ], $user);

        $import = $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'Existing FAQ', 'answer' => 'Duplicate attempt.', 'status' => 'draft'],
        ]));

        $this->assertSame(0, $import->valid_rows);
        $this->assertSame(1, $import->skipped_rows);
        $this->assertDatabaseHas('knowledge_import_rows', [
            'knowledge_import_id' => $import->id,
            'status' => KnowledgeImportRowStatus::Skipped->value,
        ]);
    }

    public function test_invalid_rows_are_reported_and_not_imported(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $import = $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'Valid question', 'answer' => '', 'status' => 'draft'],
            ['question' => 'Another valid', 'answer' => 'Good answer', 'status' => 'draft'],
        ]));

        $this->assertSame(1, $import->valid_rows);
        $this->assertSame(1, $import->failed_rows);

        app(KnowledgeImportService::class)->execute($import, $tenant, $user);

        $this->assertSame(1, KnowledgeItem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_tenant_b_cannot_see_tenant_a_imported_knowledge(): void
    {
        ['tenant' => $tenantA, 'user' => $userA, 'key' => $keyA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'key' => $keyB] = $this->createWidgetReadyTenant();

        $import = $this->validateImport($tenantA, $userA, $this->faqCsv([
            ['question' => 'Tenant A only FAQ', 'answer' => 'Secret tenant A guidance.', 'status' => 'published'],
        ]));

        app(KnowledgeImportService::class)->execute($import, $tenantA, $userA);

        $tokenA = $this->widgetSessionToken($keyA);
        $tokenB = $this->widgetSessionToken($keyB);

        $this->getJson('/widget/v1/knowledge/search?q=Tenant A only FAQ', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk()
            ->assertJsonCount(1, 'results');

        $this->getJson('/widget/v1/knowledge/search?q=Tenant A only FAQ', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenB,
        ])->assertOk()
            ->assertJsonPath('results', []);

        $this->assertSame(0, KnowledgeImport::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    public function test_knowledge_base_entitlement_blocks_import_when_disabled(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        app(EntitlementOverrideService::class)->apply(
            $tenant,
            PlanFeature::KnowledgeBase,
            false,
            null,
            $user,
            'Disable knowledge base for test',
        );

        $this->expectException(ValidationException::class);

        $this->validateImport($tenant, $user, $this->faqCsv([
            ['question' => 'Blocked import', 'answer' => 'Should not import.', 'status' => 'draft'],
        ]));
    }

    public function test_import_page_shows_locked_notice_when_entitlement_disabled(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);

        app(EntitlementOverrideService::class)->apply(
            $tenant,
            PlanFeature::KnowledgeBase,
            false,
            null,
            $user,
            'Disable knowledge base for test',
        );

        Volt::test('tenant.knowledge.import', ['tenant' => $tenant])
            ->assertSee('locked on your current subscription');
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function faqCsv(array $rows): UploadedFile
    {
        $lines = ['question,answer,category,tags,status'];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s"',
                $row['question'],
                $row['answer'],
                $row['category'] ?? '',
                $row['tags'] ?? '',
                $row['status'] ?? 'draft',
            );
        }

        return UploadedFile::fake()->createWithContent('faq-import.csv', implode("\n", $lines)."\n");
    }

    /**
     * @return \App\Models\KnowledgeImport
     */
    private function validateImport($tenant, $user, UploadedFile $file): KnowledgeImport
    {
        app(TenantContext::class)->clear();

        return app(KnowledgeImportService::class)->validateUpload(
            $tenant,
            $user,
            KnowledgeImportType::Faq,
            $file,
        );
    }
}
