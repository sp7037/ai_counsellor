<?php

use App\Enums\Configuration\DayOfWeek;
use App\Models\Tenant;
use App\Models\TenantOfficeHour;
use App\Services\Configuration\TenantConfigurationResolver;
use App\Services\Configuration\TenantOfficeHoursService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    /** @var array<int, array<string, mixed>> */
    public array $schedule = [];

    public function mount(Tenant $tenant, TenantConfigurationResolver $resolver): void
    {
        $this->authorize('viewTenantConfiguration', $tenant);
        $this->tenant = $tenant;
        $resolver->ensureDefaultOfficeHours($tenant);

        $this->schedule = $tenant->officeHours()->orderBy('day_of_week')->get()->map(fn (TenantOfficeHour $hour) => [
            'day_of_week' => $hour->day_of_week->value,
            'label' => $hour->day_of_week->label(),
            'opens_at' => $hour->opens_at ? substr((string) $hour->opens_at, 0, 5) : '09:00',
            'closes_at' => $hour->closes_at ? substr((string) $hour->closes_at, 0, 5) : '18:00',
            'is_closed' => $hour->is_closed,
        ])->all();
    }

    public function save(TenantOfficeHoursService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);
        $service->replaceSchedule($this->tenant, $this->schedule, auth()->user());
    }
}; ?>

<x-slot:heading>Office hours</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

@can('manageTenantConfiguration', $tenant)
    <form wire:submit="save" class="grid max-w-3xl gap-4">
        @foreach ($schedule as $index => $day)
            <div class="grid gap-3 rounded border border-zinc-800 p-4 md:grid-cols-4 md:items-end">
                <div class="font-medium text-white">{{ $day['label'] }}</div>
                <flux:checkbox wire:model="schedule.{{ $index }}.is_closed" label="Closed" />
                <flux:input wire:model="schedule.{{ $index }}.opens_at" type="time" label="Opens" />
                <flux:input wire:model="schedule.{{ $index }}.closes_at" type="time" label="Closes" />
            </div>
        @endforeach
        <flux:button type="submit" variant="primary">Save office hours</flux:button>
    </form>
@else
    <p class="text-zinc-500">You do not have permission to edit office hours.</p>
@endcan
