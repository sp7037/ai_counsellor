<?php

use App\Services\Platform\PlatformUsageReportingService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function with(PlatformUsageReportingService $usage): array
    {
        $from = $this->from !== '' ? Carbon::parse($this->from)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $this->to !== '' ? Carbon::parse($this->to)->endOfDay() : Carbon::now();

        return [
            'summary' => $usage->periodSummary($from, $to),
            'byTenant' => $usage->usageByTenant($from, $to),
            'byProvider' => $usage->usageByProvider($from, $to),
        ];
    }
}; ?>

<x-slot:heading>Usage</x-slot:heading>

<div class="grid gap-6">
    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live="from" type="date" label="From" />
        <flux:input wire:model.live="to" type="date" label="To" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'Total runs' => $summary['total_runs'],
            'Successful runs' => $summary['successful_runs'],
            'Failed runs' => $summary['failed_runs'],
            'Input tokens' => $summary['input_tokens'],
            'Output tokens' => $summary['output_tokens'],
            'Total tokens' => $summary['total_tokens'],
        ] as $label => $value)
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
                <p class="text-xs uppercase text-zinc-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) $value) }}</p>
            </div>
        @endforeach
    </div>

    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <flux:heading size="md">Usage by tenant</flux:heading>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-zinc-500"><tr><th class="py-2">Tenant</th><th>Runs</th><th>Success</th><th>Failed</th><th>Tokens</th></tr></thead>
                <tbody class="text-zinc-200">
                    @forelse ($byTenant as $row)
                        <tr class="border-t border-zinc-800">
                            <td class="py-2">{{ $row['tenant_name'] }}</td>
                            <td>{{ $row['total_runs'] }}</td>
                            <td>{{ $row['successful_runs'] }}</td>
                            <td>{{ $row['failed_runs'] }}</td>
                            <td>{{ number_format((int) $row['total_tokens']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-zinc-500">No usage in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <flux:heading size="md">Usage by provider</flux:heading>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-zinc-500"><tr><th class="py-2">Provider</th><th>Runs</th><th>Tokens</th></tr></thead>
                <tbody class="text-zinc-200">
                    @forelse ($byProvider as $row)
                        <tr class="border-t border-zinc-800">
                            <td class="py-2">{{ $row['provider'] }}</td>
                            <td>{{ $row['total_runs'] }}</td>
                            <td>{{ number_format((int) $row['total_tokens']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-zinc-500">No provider usage in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
