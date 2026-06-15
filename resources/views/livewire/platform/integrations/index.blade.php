<?php

use App\Models\TenantMessagingIntegration;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.platform')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'integrations' => TenantMessagingIntegration::query()
                ->with('tenant')
                ->orderByDesc('updated_at')
                ->paginate(25),
        ];
    }

    public function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return '***';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }
}; ?>

<x-slot:heading>Integrations</x-slot:heading>

<div class="grid gap-4">
    <div class="overflow-hidden rounded-lg border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Provider</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Enabled</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Last webhook</th>
                    <th class="px-4 py-3">Last error</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950">
                @forelse ($integrations as $integration)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('platform.tenants.show', $integration->tenant) }}" wire:navigate class="text-white hover:underline">
                                {{ $integration->tenant->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $integration->provider->value }}</td>
                        <td class="px-4 py-3">{{ $integration->status->value }}</td>
                        <td class="px-4 py-3">{{ $integration->is_enabled ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $this->maskPhone($integration->display_phone_number) }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $integration->last_webhook_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $integration->last_error_category ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No tenant integrations configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $integrations->links() }}
</div>
