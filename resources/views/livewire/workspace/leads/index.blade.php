<?php

use App\Models\Lead;
use App\Models\Tenant;
use App\Services\Leads\LeadDirectoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.workspace')] class extends Component {
    use WithPagination;

    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $directory): array
    {
        return [
            'leads' => $directory->paginateForTenant($this->tenant, Auth::user()),
        ];
    }
}; ?>

<x-slot:heading>My leads</x-slot:heading>
<div class="grid gap-4">
<div class="overflow-x-auto rounded-lg border border-zinc-800">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-900 text-left text-zinc-500"><tr><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-zinc-800 text-zinc-200">
            @forelse ($leads as $lead)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $lead->public_reference }}</td>
                    <td class="px-4 py-3">{{ $lead->full_name }}</td>
                    <td class="px-4 py-3">{{ $lead->stage->label() }}</td>
                    <td class="px-4 py-3 text-right"><flux:button href="{{ route('workspace.leads.show', [$tenant, $lead]) }}" wire:navigate size="sm" variant="ghost">Open</flux:button></td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-zinc-500">No assigned leads.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $leads->links() }}
</div>
