<?php

use App\Models\Tenant;
use App\Services\Platform\PlatformAuditLogService;
use App\Services\Platform\PlatformTenantDirectoryService;
use App\Services\Platform\PlatformUsageReportingService;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Tenant $tenant;

    public string $tab = 'overview';

    public string $suspension_reason = '';

    public bool $confirm_suspend = false;

    public function mount(Tenant $tenant): void
    {
        Gate::authorize('view', $tenant);
        $this->tenant = $tenant;
    }

    public function with(
        PlatformTenantDirectoryService $directory,
        PlatformUsageReportingService $usage,
        PlatformAuditLogService $audit,
    ): array {
        $detail = $directory->tenantDetail($this->tenant);

        return [
            'detail' => $detail,
            'usage' => $usage->tenantSummary($this->tenant->id),
            'auditLogs' => $audit->paginate(['tenant_id' => $this->tenant->id], 10),
            'memberships' => $this->tenant->memberships()->with('user')->get(),
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
}; ?>

<x-slot:heading>{{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.index') }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
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
                <div><dt class="text-zinc-500">Slug</dt><dd class="font-mono text-xs">{{ $tenant->slug }}</dd></div>
                <div><dt class="text-zinc-500">UUID</dt><dd class="font-mono text-xs">{{ $tenant->uuid }}</dd></div>
                <div><dt class="text-zinc-500">Conversations</dt><dd>{{ $detail['tenant']->conversations_count }}</dd></div>
                @if ($tenant->suspension_reason)
                    <div class="sm:col-span-2"><dt class="text-zinc-500">Suspension reason</dt><dd>{{ $tenant->suspension_reason }}</dd></div>
                @endif
                @if ($tenant->suspendedByUser)
                    <div><dt class="text-zinc-500">Suspended by</dt><dd>{{ $tenant->suspendedByUser->name }}</dd></div>
                @endif
            </dl>

            <div class="mt-4 flex flex-wrap gap-3">
                @can('activate', $tenant)
                    <flux:button wire:click="activate" variant="primary">Activate</flux:button>
                @endcan
                @can('reactivate', $tenant)
                    <flux:button wire:click="reactivate" variant="primary">Reactivate</flux:button>
                @endcan
                @can('suspend', $tenant)
                    <flux:button wire:click="$set('confirm_suspend', true)" variant="danger">Suspend tenant</flux:button>
                @endcan
            </div>
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
