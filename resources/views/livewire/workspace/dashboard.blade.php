<?php

use App\Models\Tenant;
use App\Services\Leads\LeadDirectoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $directory): array
    {
        return [
            'metrics' => $directory->counsellorMetrics($this->tenant, Auth::user()),
        ];
    }
}; ?>

<x-slot:heading>My dashboard</x-slot:heading>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach (['Assigned open' => $metrics['assigned_open'], 'Due today' => $metrics['due_today'], 'Overdue' => $metrics['overdue'], 'In progress' => $metrics['in_progress']] as $label => $value)
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs text-zinc-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>
    @endforeach
</div>
