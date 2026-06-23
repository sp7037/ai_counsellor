<?php

use App\Models\Tenant;
use App\Services\Billing\EntitlementResolver;
use App\Services\Platform\PlatformAuditLogService;
use App\Services\Platform\PlatformTenantDirectoryService;
use App\Services\Platform\PlatformUsageReportingService;
use App\Services\Tenancy\TenantLifecycleService;
use App\Services\Tenancy\TenantProfileService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Tenant $tenant;

    public string $tab = 'overview';

    public string $suspension_reason = '';

    public bool $confirm_suspend = false;

    public string $archive_reason = '';

    public bool $confirm_archive = false;

    public bool $confirm_delete = false;

    public string $delete_confirmation = '';

    public string $delete_reason = '';

    public bool $edit_open = false;

    public string $edit_name = '';

    public string $edit_legal_name = '';

    public string $edit_slug = '';

    public string $edit_email = '';

    public string $edit_phone = '';

    public bool $confirm_slug_change = false;

    public function mount(Tenant $tenant): void
    {
        Gate::authorize('view', $tenant);
        $this->tenant = $tenant;
        $this->fillEditForm();
    }

    protected function fillEditForm(): void
    {
        $this->edit_name = $this->tenant->name;
        $this->edit_legal_name = (string) ($this->tenant->legal_name ?? '');
        $this->edit_slug = $this->tenant->displaySlug();
        $this->edit_email = (string) ($this->tenant->displayEmail() ?? '');
        $this->edit_phone = (string) ($this->tenant->phone ?? '');
    }

    public function with(
        PlatformTenantDirectoryService $directory,
        PlatformUsageReportingService $usage,
        PlatformAuditLogService $audit,
        EntitlementResolver $entitlements,
    ): array {
        $detail = $directory->tenantDetail($this->tenant);
        $subscription = $entitlements->subscriptionFor($this->tenant)?->load('plan');

        return [
            'detail' => $detail,
            'usage' => $usage->tenantSummary($this->tenant->id),
            'auditLogs' => $audit->paginate(['tenant_id' => $this->tenant->id], 10),
            'memberships' => $this->tenant->memberships()->with('user')->get(),
            'subscription' => $subscription,
            'effectiveStatus' => $subscription?->effectiveStatus(),
            'hasOperationalAccess' => $subscription !== null
                && $subscription->effectiveStatus()->allowsOperationalAccess(),
        ];
    }

    public function activate(TenantLifecycleService $service): void
    {
        Gate::authorize('activate', $this->tenant);
        $this->tenant = $service->activate($this->tenant, auth()->user());
    }

    public function suspend(TenantLifecycleService $service): void
    {
        Gate::authorize('suspend', $this->tenant);

        $this->validate([
            'suspension_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->tenant = $service->suspend($this->tenant, $this->suspension_reason, auth()->user());
        $this->reset('suspension_reason', 'confirm_suspend');
    }

    public function reactivate(TenantLifecycleService $service): void
    {
        Gate::authorize('reactivate', $this->tenant);
        $this->tenant = $service->reactivate($this->tenant, auth()->user());
    }

    public function archive(TenantLifecycleService $service): void
    {
        Gate::authorize('archive', $this->tenant);

        $this->validate([
            'archive_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->tenant = $service->archive($this->tenant, $this->archive_reason, auth()->user())
            ->load(['archivedByUser', 'suspendedByUser']);
        $this->reset('archive_reason', 'confirm_archive');
    }

    public function restore(TenantLifecycleService $service): void
    {
        Gate::authorize('restore', $this->tenant);

        if ($this->tenant->status === \App\Enums\Tenancy\TenantStatus::Deleted) {
            $this->tenant = $service->restoreFromDelete($this->tenant, auth()->user())
                ->load(['archivedByUser', 'suspendedByUser', 'deletedByUser']);
        } else {
            $this->tenant = $service->restoreFromArchive($this->tenant, auth()->user())
                ->load(['archivedByUser', 'suspendedByUser', 'deletedByUser']);
        }
    }

    public function deleteTenant(TenantLifecycleService $service): void
    {
        Gate::authorize('delete', $this->tenant);

        $this->validate([
            'delete_confirmation' => ['required', 'string'],
            'delete_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->tenant = $service->deleteTenant(
            $this->tenant,
            $this->delete_confirmation,
            $this->delete_reason,
            auth()->user(),
        )->load(['archivedByUser', 'suspendedByUser', 'deletedByUser']);

        $this->reset('delete_confirmation', 'delete_reason', 'confirm_delete');
    }

    public function openEdit(): void
    {
        Gate::authorize('update', $this->tenant);
        $this->fillEditForm();
        $this->edit_open = true;
    }

    public function saveProfile(TenantProfileService $profiles): void
    {
        Gate::authorize('update', $this->tenant);

        $this->validate([
            'edit_name' => ['required', 'string', 'max:255'],
            'edit_legal_name' => ['nullable', 'string', 'max:255'],
            'edit_slug' => ['required', 'string', 'max:255', 'alpha_dash'],
            'edit_email' => ['nullable', 'email', 'max:255'],
            'edit_phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($this->edit_slug !== $this->tenant->displaySlug() && ! $this->confirm_slug_change) {
            $this->addError('edit_slug', 'Confirm the slug change before saving.');

            return;
        }

        $this->tenant = $profiles->update($this->tenant, [
            'name' => $this->edit_name,
            'legal_name' => $this->edit_legal_name ?: null,
            'slug' => $this->edit_slug,
            'email' => $this->edit_email ?: null,
            'phone' => $this->edit_phone ?: null,
        ], auth()->user());

        $this->edit_open = false;
        $this->confirm_slug_change = false;
        session()->flash('status', 'Tenant profile updated.');
    }
}; ?>

<x-slot:heading>{{ $tenant->displayName() }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.index') }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif
    <div class="flex flex-wrap gap-2 border-b border-zinc-800 pb-2 text-sm">
        @foreach (['overview' => 'Overview', 'ai' => 'AI configuration', 'usage' => 'Usage', 'activity' => 'Activity', 'audit' => 'Audit history'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'rounded-md px-3 py-1.5',
                'bg-zinc-800 text-white' => $tab === $key,
                'text-zinc-400 hover:text-white' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm text-zinc-300">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="text-zinc-500">Status</dt><dd>{{ $tenant->status->label() }}</dd></div>
                <div><dt class="text-zinc-500">Slug</dt><dd class="font-mono text-xs">{{ $tenant->displaySlug() }}</dd></div>
                @if ($tenant->status === \App\Enums\Tenancy\TenantStatus::Deleted)
                    <div><dt class="text-zinc-500">Internal slug</dt><dd class="font-mono text-xs text-zinc-500">{{ $tenant->slug }}</dd></div>
                @endif
                @if ($tenant->identifier_restore_conflict)
                    <div class="sm:col-span-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-100">
                        Identifier conflict on restore. Edit slug/email before reactivation.
                    </div>
                @endif
                <div><dt class="text-zinc-500">UUID</dt><dd class="font-mono text-xs">{{ $tenant->uuid }}</dd></div>
                <div><dt class="text-zinc-500">Conversations</dt><dd>{{ $detail['tenant']->conversations_count }}</dd></div>
                @if ($tenant->suspension_reason)
                    <div class="sm:col-span-2"><dt class="text-zinc-500">Suspension reason</dt><dd>{{ $tenant->suspension_reason }}</dd></div>
                @endif
                @if ($tenant->suspendedByUser)
                    <div><dt class="text-zinc-500">Suspended by</dt><dd>{{ $tenant->suspendedByUser->name }}</dd></div>
                @endif
                @if ($tenant->archive_reason)
                    <div class="sm:col-span-2"><dt class="text-zinc-500">Archive reason</dt><dd>{{ $tenant->archive_reason }}</dd></div>
                @endif
                @if ($tenant->archived_at)
                    <div><dt class="text-zinc-500">Archived at</dt><dd>{{ $tenant->archived_at->toDayDateTimeString() }}</dd></div>
                @endif
                @if ($tenant->archivedByUser)
                    <div><dt class="text-zinc-500">Archived by</dt><dd>{{ $tenant->archivedByUser->name }}</dd></div>
                @endif
                @if ($tenant->delete_reason)
                    <div class="sm:col-span-2"><dt class="text-zinc-500">Delete reason</dt><dd>{{ $tenant->delete_reason }}</dd></div>
                @endif
                @if ($tenant->deleted_at)
                    <div><dt class="text-zinc-500">Deleted at</dt><dd>{{ $tenant->deleted_at->toDayDateTimeString() }}</dd></div>
                @endif
                @if ($tenant->deletedByUser)
                    <div><dt class="text-zinc-500">Deleted by</dt><dd>{{ $tenant->deletedByUser->name }}</dd></div>
                @endif
            </dl>

            <div class="mt-4 flex flex-wrap gap-3">
                @can('update', $tenant)
                    <flux:button type="button" wire:click="openEdit" variant="ghost">Edit tenant</flux:button>
                @endcan
                @can('activate', $tenant)
                    <flux:button wire:click="activate" variant="primary">Activate</flux:button>
                @endcan
                @can('reactivate', $tenant)
                    <flux:button wire:click="reactivate" variant="primary">Reactivate</flux:button>
                @endcan
                @can('suspend', $tenant)
                    <flux:button wire:click="$set('confirm_suspend', true)" variant="danger">Suspend tenant</flux:button>
                @endcan
                @can('archive', $tenant)
                    <flux:button wire:click="$set('confirm_archive', true)" variant="danger">Archive tenant</flux:button>
                @endcan
                @can('restore', $tenant)
                    <flux:button wire:click="restore" variant="primary">
                        {{ $tenant->status === \App\Enums\Tenancy\TenantStatus::Deleted ? 'Restore deleted tenant' : 'Restore archived tenant' }}
                    </flux:button>
                @endcan
                @can('delete', $tenant)
                    <flux:button wire:click="$set('confirm_delete', true)" variant="danger">Delete tenant</flux:button>
                @endcan
            </div>
        </div>

        @if ($edit_open)
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6">
                <flux:heading size="md">Edit tenant</flux:heading>
                <form wire:submit="saveProfile" class="mt-4 grid max-w-xl gap-3">
                    <flux:input wire:model="edit_name" label="Organisation name" required />
                    <flux:input wire:model="edit_legal_name" label="Legal name" />
                    <flux:input wire:model="edit_slug" label="Slug" required />
                    @if ($edit_slug !== $tenant->displaySlug())
                        <flux:checkbox wire:model="confirm_slug_change" label="I understand this slug change affects tenant URLs and routing." />
                    @endif
                    <flux:input wire:model="edit_email" label="Contact email" type="email" />
                    <flux:input wire:model="edit_phone" label="Phone" />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">Save changes</flux:button>
                        <flux:button type="button" wire:click="$set('edit_open', false)" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </div>
        @endif

        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="md">Subscription</flux:heading>
                    <p class="mt-1 text-sm text-zinc-400">Assign or change the tenant billing plan without Razorpay.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($subscription)
                        <flux:button href="{{ route('platform.tenants.subscription', $tenant) }}" wire:navigate size="sm" variant="primary">
                            Change subscription
                        </flux:button>
                    @else
                        <flux:button href="{{ route('platform.tenants.subscription', $tenant) }}" wire:navigate size="sm" variant="primary">
                            Assign subscription
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($subscription)
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-zinc-500">Plan</dt>
                        <dd class="font-medium text-white">{{ $subscription->plan->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Status</dt>
                        <dd>{{ $effectiveStatus?->label() ?? $subscription->status->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Billing interval</dt>
                        <dd>{{ ucfirst($subscription->plan->billing_interval) }}</dd>
                    </div>
                    @if ($subscription->current_period_started_at)
                        <div>
                            <dt class="text-zinc-500">Started</dt>
                            <dd>{{ $subscription->current_period_started_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->trial_ends_at)
                        <div>
                            <dt class="text-zinc-500">Trial ends</dt>
                            <dd>{{ $subscription->trial_ends_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->current_period_ends_at)
                        <div>
                            <dt class="text-zinc-500">Period ends</dt>
                            <dd>{{ $subscription->current_period_ends_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->cancelled_at)
                        <div>
                            <dt class="text-zinc-500">Cancelled</dt>
                            <dd>{{ $subscription->cancelled_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-zinc-500">Entitlements</dt>
                        <dd>
                            @if ($hasOperationalAccess)
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">Restricted</flux:badge>
                            @endif
                        </dd>
                    </div>
                </dl>
            @else
                <x-subscription-notice class="mt-4">
                    No subscription assigned. The tenant cannot use paid features until you assign a trial or plan.
                </x-subscription-notice>
            @endif
        </div>

        @if ($confirm_suspend)
            <div class="rounded-lg border border-red-900/50 bg-zinc-900 p-6">
                <flux:heading size="md">Confirm suspension</flux:heading>
                <p class="mt-2 text-sm text-zinc-400">Suspension preserves tenant data but blocks tenant operations.</p>
                <form wire:submit="suspend" class="mt-4 grid gap-3">
                    <flux:textarea wire:model="suspension_reason" label="Suspension reason" rows="3" required />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="danger">Confirm suspend</flux:button>
                        <flux:button type="button" wire:click="$set('confirm_suspend', false)" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </div>
        @endif

        @if ($confirm_archive)
            <div class="rounded-lg border border-red-900/50 bg-zinc-900 p-6">
                <flux:heading size="md">Confirm archive</flux:heading>
                <p class="mt-2 text-sm text-zinc-400">Archiving keeps tenant data in the database but blocks login, widget access, and hides the tenant from the default list.</p>
                <form wire:submit="archive" class="mt-4 grid gap-3">
                    <flux:textarea wire:model="archive_reason" label="Archive reason" rows="3" required />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="danger">Confirm archive</flux:button>
                        <flux:button type="button" wire:click="$set('confirm_archive', false)" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </div>
        @endif

        @if ($confirm_delete)
            <div class="rounded-lg border border-red-900/50 bg-zinc-900 p-6">
                <flux:heading size="md">Delete tenant</flux:heading>
                <p class="mt-2 text-sm text-zinc-400">This will disable the tenant and hide it from normal platform operations. Historical records are preserved for audit and recovery. Hard database removal is not performed. Type <strong class="text-white">DELETE TENANT</strong> to confirm.</p>
                <form wire:submit="deleteTenant" class="mt-4 grid gap-3">
                    <flux:textarea wire:model="delete_reason" label="Delete reason" rows="3" required />
                    <flux:input wire:model="delete_confirmation" label="Confirmation" placeholder="DELETE TENANT" required />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="danger">Delete tenant</flux:button>
                        <flux:button type="button" wire:click="$set('confirm_delete', false)" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </div>
        @endif

        <div>
            <flux:heading size="md">Memberships</flux:heading>
            <div class="mt-3 overflow-x-auto rounded-lg border border-zinc-800">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-900 text-left text-zinc-500"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-zinc-800 text-zinc-200">
                        @foreach ($memberships as $membership)
                            <tr>
                                <td class="px-4 py-3">{{ $membership->user->name }} ({{ $membership->user->email }})</td>
                                <td class="px-4 py-3">{{ $membership->role->label() }}</td>
                                <td class="px-4 py-3">{{ $membership->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($tab === 'ai')
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm">
            <dl class="grid gap-3">
                <div><dt class="text-zinc-500">Credential mode</dt><dd>{{ $detail['credential_mode'] }}</dd></div>
                <div><dt class="text-zinc-500">Configuration status</dt><dd>{{ $detail['ai_status']['label'] }}</dd></div>
                <div><dt class="text-zinc-500">Detail</dt><dd>{{ $detail['ai_status']['detail'] }}</dd></div>
            </dl>
        </div>
    @endif

    @if ($tab === 'usage')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['Total runs' => $usage['total_runs'], 'Successful' => $usage['successful_runs'], 'Failed' => $usage['failed_runs'], 'Tokens (month)' => $usage['total_tokens']] as $label => $value)
                <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
                    <p class="text-xs uppercase text-zinc-500">{{ $label }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ number_format((int) $value) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($tab === 'activity')
        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-900 text-left text-zinc-500"><tr><th class="px-4 py-3">Conversation</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last message</th></tr></thead>
                <tbody class="divide-y divide-zinc-800 text-zinc-200">
                    @forelse ($detail['recent_conversations'] as $conversation)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $conversation->uuid }}</td>
                            <td class="px-4 py-3">{{ $conversation->status }}</td>
                            <td class="px-4 py-3">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-zinc-500">No conversations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'audit')
        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-900 text-left text-zinc-500"><tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Actor</th></tr></thead>
                <tbody class="divide-y divide-zinc-800 text-zinc-200">
                    @forelse ($auditLogs as $log)
                        <tr>
                            <td class="px-4 py-3">{{ $log->created_at?->toDayDateTimeString() }}</td>
                            <td class="px-4 py-3">{{ $log->action->label() }}</td>
                            <td class="px-4 py-3">{{ $log->actor?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-zinc-500">No audit records for this tenant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $auditLogs->links() }}
    @endif
</div>
