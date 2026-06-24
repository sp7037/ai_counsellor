<?php

use App\Enums\Billing\PlanChangeRequestStatus;
use App\Models\TenantPlanChangeRequest;
use App\Services\Billing\PlanChangeRequestService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public function with(PlanChangeRequestService $service): array
    {
        return [
            'planChangeRequestsAvailable' => $service->isAvailable(),
            'requests' => $service->pendingRequests(),
        ];
    }

    public function approve(int $requestId, PlanChangeRequestService $service): void
    {
        $request = TenantPlanChangeRequest::query()->findOrFail($requestId);
        Gate::authorize('update', $request->tenant);
        $service->approve($request, auth()->user());
        session()->flash('status', 'Plan change request approved.');
    }

    public function reject(int $requestId, PlanChangeRequestService $service, string $admin_note = ''): void
    {
        $request = TenantPlanChangeRequest::query()->findOrFail($requestId);
        Gate::authorize('update', $request->tenant);
        $service->reject($request, auth()->user(), $admin_note ?: null);
        session()->flash('status', 'Plan change request rejected.');
    }
}; ?>

<x-slot:heading>Plan change requests</x-slot:heading>

<div class="grid gap-4">
    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if (! $planChangeRequestsAvailable)
        <flux:callout variant="warning">
            Plan change requests are not enabled on this server yet. Run the latest database migrations to activate this feature.
        </flux:callout>
    @endif

    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Current plan</th>
                    <th class="px-4 py-3">Requested plan</th>
                    <th class="px-4 py-3">Reason</th>
                    <th class="px-4 py-3">Requested by</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 text-zinc-200">
                @forelse ($requests as $request)
                    <tr wire:key="plan-request-{{ $request->id }}">
                        <td class="px-4 py-3">{{ $request->tenant->displayName() }}</td>
                        <td class="px-4 py-3">{{ $request->currentPlan?->name ?? 'None' }}</td>
                        <td class="px-4 py-3">{{ $request->requestedPlan->name }}</td>
                        <td class="px-4 py-3">{{ $request->reason ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $request->requester->name }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <flux:button wire:click="approve({{ $request->id }})" size="sm" variant="primary">Approve</flux:button>
                                <flux:button wire:click="reject({{ $request->id }})" size="sm" variant="ghost">Reject</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">No pending plan change requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
