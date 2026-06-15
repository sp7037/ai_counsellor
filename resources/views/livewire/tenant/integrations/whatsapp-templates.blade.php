<?php

use App\Models\MessagingTemplate;
use App\Models\Tenant;
use App\Services\Messaging\MessagingIntegrationService;
use App\Services\Messaging\TemplateMessageService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $provider_template_name = '';

    public string $language_code = 'en';

    public string $category = 'utility';

    public string $status = 'approved';

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
            'templates' => MessagingTemplate::query()
                ->where('messaging_integration_id', $integration->id)
                ->orderBy('provider_template_name')
                ->get(),
        ];
    }

    public function addTemplate(TemplateMessageService $templates, MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($this->tenant);
        $this->authorize('configure', $integration);

        $validated = $this->validate([
            'provider_template_name' => ['required', 'string', 'max:120'],
            'language_code' => ['required', 'string', 'max:16'],
            'category' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:approved,pending,rejected,disabled'],
        ]);

        $templates->syncTemplate(
            $integration,
            $validated['provider_template_name'],
            $validated['language_code'],
            $validated['status'],
            $validated['category'] ?: null,
        );

        $this->reset('provider_template_name');
        session()->flash('status', 'Template recorded.');
    }
}; ?>

<x-slot:heading>WhatsApp templates</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.integrations.whatsapp', $tenant) }}" wire:navigate variant="ghost" size="sm">Back to WhatsApp</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <form wire:submit="addTemplate" class="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-4 md:grid-cols-2">
        <flux:heading size="md" class="md:col-span-2">Add approved template metadata</flux:heading>
        <flux:input wire:model="provider_template_name" label="Provider template name" required />
        <flux:input wire:model="language_code" label="Language code" required />
        <flux:input wire:model="category" label="Category" />
        <flux:select wire:model="status" label="Provider status">
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
            <option value="disabled">Disabled</option>
        </flux:select>
        <div class="md:col-span-2">
            <flux:button type="submit" variant="primary" size="sm">Save template</flux:button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Language</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Last synced</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950">
                @forelse ($templates as $template)
                    <tr>
                        <td class="px-4 py-3">{{ $template->provider_template_name }}</td>
                        <td class="px-4 py-3">{{ $template->language_code }}</td>
                        <td class="px-4 py-3">{{ $template->category ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $template->status }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $template->last_synced_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No templates recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
