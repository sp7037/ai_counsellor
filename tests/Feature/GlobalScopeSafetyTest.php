<?php

namespace Tests\Feature;

use App\Models\TenantNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalScopeSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_ignores_request_supplied_tenant_id_when_isolation_enforced(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        $this->withTenantContext($userA, $tenantA);

        $note = TenantNote::query()->create([
            'tenant_id' => $tenantB->id,
            'title' => 'Attempted cross-tenant create',
            'created_by' => $userA->id,
        ]);

        $this->assertSame($tenantA->id, $note->tenant_id);
    }

    public function test_update_cannot_change_tenant_id(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        $note = TenantNote::query()->forceCreate([
            'tenant_id' => $tenantA->id,
            'title' => 'Original',
            'created_by' => $userA->id,
        ]);

        $this->withTenantContext($userA, $tenantA);

        $note->update(['tenant_id' => $tenantB->id, 'title' => 'Updated']);

        $this->assertSame($tenantA->id, $note->fresh()->tenant_id);
        $this->assertSame('Updated', $note->fresh()->title);
    }

    public function test_factory_without_context_does_not_apply_fail_closed_scope(): void
    {
        $note = TenantNote::factory()->create();

        $this->assertDatabaseHas('tenant_notes', ['id' => $note->id]);
    }

    public function test_cross_tenant_uuid_route_returns_forbidden_not_data_leak(): void
    {
        ['user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        $this->actingAs($userA)
            ->get(route('tenant.notes.index', $tenantB))
            ->assertForbidden();
    }

    public function test_bulk_delete_respects_tenant_scope(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        TenantNote::query()->forceCreate([
            'tenant_id' => $tenantA->id,
            'title' => 'A',
            'created_by' => $userA->id,
        ]);
        $noteB = TenantNote::query()->forceCreate([
            'tenant_id' => $tenantB->id,
            'title' => 'B',
            'created_by' => $userA->id,
        ]);

        $this->withTenantContext($userA, $tenantA);
        TenantNote::query()->delete();

        $this->assertDatabaseMissing('tenant_notes', ['title' => 'A']);
        $this->assertDatabaseHas('tenant_notes', ['id' => $noteB->id]);
    }
}
