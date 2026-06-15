<?php

use App\Services\Platform\PlatformDashboardService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public function with(PlatformDashboardService $dashboard): array
    {
        return [
            'cards' => $dashboard->summaryCards(),
            'recentTenants' => $dashboard->recentTenants(),
            'aiOverview' => $dashboard->aiOperationsOverview(),
            'alerts' => $dashboard->platformAlerts(),
        ];
    }
}; ?>

<x-slot:heading>Platform overview</x-slot:heading>

<div class="grid gap-6">
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      'Total tenants' => $cards['total_tenants'],
      'Active tenants' => $cards['active_tenants'],
      'Suspended tenants' => $cards['suspended_tenants'],
      'Conversations' => $cards['total_conversations'],
      'AI runs today' => $cards['ai_runs_today'],
      'Successful today' => $cards['ai_success_today'],
      'Failed today' => $cards['ai_failed_today'],
      'Tokens this month' => $cards['tokens_period'],
    ] as $label => $value)
      <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ $label }}</p>
        <p class="mt-2 text-2xl font-semibold text-white">{{ number_format((int) $value) }}</p>
      </div>
    @endforeach
  </div>

  @if ($alerts !== [])
    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
      <flux:heading size="md">Platform alerts</flux:heading>
      <ul class="mt-3 grid gap-2 text-sm text-zinc-300">
        @foreach ($alerts as $alert)
          <li class="rounded border border-zinc-800 px-3 py-2">{{ $alert['message'] }}</li>
        @endforeach
      </ul>
    </section>
  @endif

  <div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
      <flux:heading size="md">Tenant status overview</flux:heading>
      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left text-zinc-500"><tr><th class="py-2">Tenant</th><th>Status</th><th>AI</th><th>Activity</th></tr></thead>
          <tbody class="text-zinc-200">
            @forelse ($recentTenants as $tenant)
              <tr class="border-t border-zinc-800">
                <td class="py-2"><a href="{{ route('platform.tenants.show', $tenant['uuid']) }}" class="underline" wire:navigate>{{ $tenant['name'] }}</a></td>
                <td>{{ $tenant['status'] }}</td>
                <td>{{ $tenant['ai_status'] }}</td>
                <td>{{ $tenant['updated_at'] ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-6 text-center text-zinc-500">No tenants yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
      <flux:heading size="md">AI operations overview (today)</flux:heading>
      <dl class="mt-3 grid gap-2 text-sm text-zinc-300">
        <div class="flex justify-between"><dt>Processing</dt><dd>{{ $aiOverview['processing'] }}</dd></div>
        <div class="flex justify-between"><dt>Successful</dt><dd>{{ $aiOverview['success'] }}</dd></div>
        <div class="flex justify-between"><dt>Failed</dt><dd>{{ $aiOverview['failed'] }}</dd></div>
      </dl>
      @if ($aiOverview['recent_failures'] !== [])
        <div class="mt-4">
          <p class="text-xs uppercase tracking-wide text-zinc-500">Recent failures</p>
          <ul class="mt-2 grid gap-2 text-sm">
            @foreach ($aiOverview['recent_failures'] as $failure)
              <li class="rounded border border-zinc-800 px-3 py-2">{{ $failure['tenant'] }} — {{ $failure['error_category'] }} ({{ $failure['created_at'] }})</li>
            @endforeach
          </ul>
        </div>
      @endif
    </section>
  </div>
</div>
