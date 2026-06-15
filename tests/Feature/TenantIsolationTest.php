<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\TenantNote;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_user_cannot_view_tenant_b_notes(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        app(TenantContext::class)->clear();

        $noteB = TenantNote::query()->forceCreate([
            'tenant_id' => $tenantB->id,
            'title' => 'Tenant B secret',
            'body' => 'Hidden',
            'created_by' => $userA->id,
        ]);

        app(TenantContext::class)->resolveForUser($userA, $tenantA);
        app(TenantContext::class)->enforceIsolation();

        $this->assertFalse(TenantNote::query()->whereKey($noteB->id)->exists());
    }

    public function test_tenant_a_user_cannot_update_tenant_b_note_via_livewire(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        app(TenantContext::class)->clear();

        $noteB = TenantNote::query()->forceCreate([
            'tenant_id' => $tenantB->id,
            'title' => 'Protected',
            'created_by' => $userA->id,
        ]);

        $this->actingAs($userA);
        app(TenantContext::class)->resolveForUser($userA, $tenantA);
        app(TenantContext::class)->enforceIsolation();

        Volt::test('tenant.notes.index', ['tenant' => $tenantA])
            ->call('deleteNote', $noteB->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('tenant_notes', ['id' => $noteB->id]);
    }

    public function test_tenant_a_user_cannot_access_tenant_b_dashboard_route(): void
    {
        ['user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        $this->actingAs($userA)
            ->get(route('tenant.dashboard', $tenantB))
            ->assertForbidden();
    }

    public function test_tenant_a_user_cannot_assign_note_to_tenant_b_via_mass_assignment(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        app(TenantContext::class)->resolveForUser($userA, $tenantA);
        app(TenantContext::class)->enforceIsolation();

        $note = TenantNote::query()->create([
            'tenant_id' => $tenantB->id,
            'title' => 'Cross-tenant attempt',
            'created_by' => $userA->id,
        ]);

        $this->assertSame($tenantA->id, $note->tenant_id);
    }

    public function test_route_model_binding_uses_uuid_not_numeric_id(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();

        $this->actingAs($user)
            ->get('/app/'.$tenant->id.'/dashboard')
            ->assertNotFound();
    }

    public function test_tenant_slug_uniqueness_is_enforced(): void
    {
        Tenant::factory()->create(['slug' => 'acme-corp']);

        $this->expectException(QueryException::class);

        Tenant::factory()->create(['slug' => 'acme-corp']);
    }

    public function test_membership_duplicates_are_rejected(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'membership' => $membership] = $this->createTenantWithMember(role: TenantRole::Owner);

        $this->actingAs($user);

        $service = app(TenantLifecycleService::class);

        $this->expectException(ValidationException::class);
        $service->addMember($tenant, $user, TenantRole::Staff, actor: $user);
    }
}
