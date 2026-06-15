<?php

use App\Models\MessagingEvent;
use App\Models\Tenant;
use App\Services\Messaging\MessagingIntegrationService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.tenant')] class extends Component {
    use WithPagination;

    public Tenant $tenant;

    public function mount(Tenant $tenant, MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($tenant);
        $this->authorize('view', $integration);
        $this->tenant = $tenant;
    }

    public function with(MessagingIntegrationService $integrations): array
    {
        $integration = $integrations->forTenant($this->tenant);

        return [
            'events' => MessagingEvent::query()
                ->where('messaging_integration_id', $integration->id)
                ->latest('id')
                ->paginate(25),
        ];
    }
}; ?>

<x-slot:heading>WhatsApp integration events</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.integrations.whatsapp', $tenant) }}" wire:navigate variant="ghost" size="sm">Back to WhatsApp</flux:button>
</x-slot:actions>

<div class="grid gap-4">
    <div class="overflow-hidden rounded-lg border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-4 py-3">{{ $event->event_type }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $event->external_reference ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $event->processing_status }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $event->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No integration events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $events->links() }}
</div>
