<?php

use App\Models\User;
use App\Services\Platform\PlatformUserLookupService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public string $email = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function lookup(PlatformUserLookupService $service): void
    {
        Gate::authorize('viewAny', User::class);
        $this->validate(['email' => ['required', 'email']]);
        $this->result = $service->lookup($this->email);
    }
}; ?>

<x-slot:heading>Account lookup</x-slot:heading>

<div class="grid max-w-3xl gap-4">
    <p class="text-sm text-zinc-400">Super-admin only email lookup for support. Public password reset remains generic and does not reveal account existence.</p>

    <form wire:submit="lookup" class="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
        <flux:input wire:model="email" label="Email address" type="email" required />
        <flux:button type="submit" variant="primary">Lookup account</flux:button>
    </form>

    @if ($result !== null)
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm text-zinc-200">
            <p class="font-medium text-white">{{ $result['message'] ?? 'Lookup complete.' }}</p>

            @if ($result['exists'] ?? false)
                <dl class="mt-4 grid gap-2">
                    <div><dt class="text-zinc-500">Name</dt><dd>{{ $result['user']['name'] ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Email</dt><dd>{{ $result['user']['email'] ?? '—' }}</dd></div>
                    @if (! empty($result['user']['original_email']))
                        <div><dt class="text-zinc-500">Original email</dt><dd>{{ $result['user']['original_email'] }}</dd></div>
                    @endif
                    <div><dt class="text-zinc-500">Status</dt><dd>{{ $result['user']['status'] ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Available for new tenant owner</dt><dd>{{ ($result['available_for_new_tenant_owner'] ?? false) ? 'Yes' : 'No' }}</dd></div>
                </dl>

                @if (! empty($result['memberships']))
                    <div class="mt-4">
                        <p class="text-zinc-500">Tenant memberships</p>
                        <ul class="mt-2 grid gap-2">
                            @foreach ($result['memberships'] as $membership)
                                <li class="rounded border border-zinc-800 px-3 py-2">
                                    {{ $membership['tenant_name'] }} · {{ $membership['tenant_status'] }} · {{ $membership['role'] }}
                                    @if ($membership['is_owner']) (Owner) @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
