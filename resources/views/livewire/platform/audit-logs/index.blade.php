<?php

use App\Enums\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\Platform\PlatformAuditLogService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.platform')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tenant_id = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?int $selectedLogId = null;

    public function showLog(int $logId): void
    {
        Gate::authorize('view', AuditLog::query()->findOrFail($logId));
        $this->selectedLogId = $logId;
    }

    public function closeLog(): void
    {
        $this->selectedLogId = null;
    }

    public function with(PlatformAuditLogService $audit): array
    {
        $filters = array_filter([
            'tenant_id' => $this->tenant_id !== '' ? (int) $this->tenant_id : null,
            'action' => $this->action !== '' ? $this->action : null,
            'from' => $this->from !== '' ? $this->from : null,
            'to' => $this->to !== '' ? $this->to : null,
        ], fn ($value) => $value !== null);

        $logs = $audit->paginate($filters);
        $logDetail = null;

        if ($this->selectedLogId !== null) {
            $log = AuditLog::query()->find($this->selectedLogId);
            if ($log !== null) {
                Gate::authorize('view', $log);
                $logDetail = $audit->safeDetail($log);
            }
        }

        return [
            'logs' => $logs,
            'logDetail' => $logDetail,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditAction::cases(),
        ];
    }
}; ?>

<x-slot:heading>Audit logs</x-slot:heading>

<div class="grid gap-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="tenant_id" label="Tenant">
            <option value="">All tenants</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="action" label="Action">
            <option value="">All actions</option>
            @foreach ($actions as $auditAction)
                <option value="{{ $auditAction->value }}">{{ $auditAction->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="from" type="date" label="From" />
        <flux:input wire:model.live="to" type="date" label="To" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Actor</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 text-zinc-200">
                @forelse ($logs as $log)
                    <tr class="cursor-pointer hover:bg-zinc-900" wire:click="showLog({{ $log->id }})">
                        <td class="px-4 py-3">{{ $log->created_at?->toDayDateTimeString() }}</td>
                        <td class="px-4 py-3">{{ $log->actor?->name ?? 'System' }}</td>
                        <td class="px-4 py-3">{{ $log->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->action->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-zinc-500">No audit records match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}

    @if ($logDetail)
        <div class="fixed inset-0 z-50 flex justify-end bg-black/50" wire:click="closeLog">
            <div class="h-full w-full max-w-xl overflow-y-auto border-l border-zinc-800 bg-zinc-950 p-6" wire:click.stop>
                <div class="flex items-center justify-between">
                    <flux:heading size="md">Audit #{{ $logDetail['id'] }}</flux:heading>
                    <flux:button wire:click="closeLog" variant="ghost" size="sm">Close</flux:button>
                </div>
                <dl class="mt-4 grid gap-2 text-sm text-zinc-300">
                    <div class="flex justify-between gap-4 border-b border-zinc-900 py-2"><dt class="text-zinc-500">Action</dt><dd>{{ $logDetail['action'] }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-zinc-900 py-2"><dt class="text-zinc-500">Actor</dt><dd>{{ $logDetail['actor']['name'] ?? 'System' }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-zinc-900 py-2"><dt class="text-zinc-500">Tenant</dt><dd>{{ $logDetail['tenant']['name'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4 border-b border-zinc-900 py-2"><dt class="text-zinc-500">IP</dt><dd>{{ $logDetail['ip_address'] ?? '—' }}</dd></div>
                </dl>
                @if (! empty($logDetail['metadata']))
                    <pre class="mt-4 overflow-x-auto rounded border border-zinc-800 bg-zinc-900 p-3 text-xs text-zinc-400">{{ json_encode($logDetail['metadata'], JSON_PRETTY_PRINT) }}</pre>
                @endif
            </div>
        </div>
    @endif
</div>
