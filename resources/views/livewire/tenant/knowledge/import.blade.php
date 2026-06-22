<?php

use App\Enums\Knowledge\KnowledgeImportRowStatus;
use App\Enums\Knowledge\KnowledgeImportStatus;
use App\Enums\Knowledge\KnowledgeImportType;
use App\Models\KnowledgeImport;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeImportService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    use WithFileUploads;

    public Tenant $tenant;

    public string $importType = 'faq';

    public $csvFile;

    public ?int $previewImportId = null;

    public ?string $notice = null;

    public ?string $errorNotice = null;

    public function mount(Tenant $tenant, KnowledgeImportService $imports): void
    {
        $this->authorize('viewTenantKnowledge', $tenant);
        $this->tenant = $tenant;

        if (! $imports->canImport($tenant)) {
            return;
        }

        $this->authorize('manageTenantKnowledge', $tenant);
    }

    public function with(KnowledgeImportService $imports): array
    {
        $preview = $this->previewImportId !== null
            ? KnowledgeImport::query()->with('rows')->find($this->previewImportId)
            : null;

        return [
            'canImport' => $imports->canImport($this->tenant),
            'importTypes' => KnowledgeImportType::cases(),
            'preview' => $preview,
            'history' => KnowledgeImport::query()->with('user')->latest('id')->limit(10)->get(),
        ];
    }

    public function validateUpload(KnowledgeImportService $imports): void
    {
        $this->authorize('manageTenantKnowledge', $this->tenant);
        $this->reset('notice', 'errorNotice', 'previewImportId');

        if (! $imports->canImport($this->tenant)) {
            $this->errorNotice = 'Knowledge base import is not available on your current plan.';

            return;
        }

        $validated = $this->validate([
            'importType' => ['required', 'in:faq,course_info,fee,eligibility'],
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $import = $imports->validateUpload(
            $this->tenant,
            auth()->user(),
            KnowledgeImportType::from($validated['importType']),
            $this->csvFile,
        );

        $this->previewImportId = $import->id;
        $this->notice = $import->error_summary ?? 'Validation completed.';
        $this->reset('csvFile');
    }

    public function confirmImport(KnowledgeImportService $imports): void
    {
        $this->authorize('manageTenantKnowledge', $this->tenant);
        $this->reset('notice', 'errorNotice');

        if ($this->previewImportId === null) {
            $this->errorNotice = 'Validate a CSV file before importing.';

            return;
        }

        $import = KnowledgeImport::query()->findOrFail($this->previewImportId);
        $result = $imports->execute($import, $this->tenant, auth()->user());

        $this->notice = $result->error_summary ?? 'Import completed.';
        $this->previewImportId = $result->id;
    }

    public function clearPreview(): void
    {
        $this->previewImportId = null;
        $this->reset('notice', 'errorNotice', 'csvFile');
    }
}; ?>

<x-slot:heading>Import knowledge</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @unless ($canImport)
        <div class="rounded-lg border border-amber-700/40 bg-amber-950/40 p-4 text-sm text-amber-100">
            Knowledge base import is locked on your current subscription. Upgrade or enable the knowledge base feature to import FAQs and structured counselling content.
        </div>
    @else
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-5">
            <flux:heading size="sm">Upload CSV</flux:heading>
            <p class="mt-2 text-sm text-zinc-400">For Excel files, please export as CSV before upload.</p>

            <form wire:submit="validateUpload" class="mt-4 grid gap-4">
                <flux:select wire:model="importType" label="Import type">
                    @foreach ($importTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="csvFile" type="file" label="CSV file" accept=".csv,text/csv" />

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary">Validate & preview</flux:button>
                    @if ($previewImportId)
                        <flux:button type="button" wire:click="clearPreview" variant="ghost">Clear preview</flux:button>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-5">
            <flux:heading size="sm">Sample templates</flux:heading>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button href="{{ route('tenant.knowledge.import.template', [$tenant, 'faq']) }}" size="sm" variant="ghost">FAQ template</flux:button>
                <flux:button href="{{ route('tenant.knowledge.import.template', [$tenant, 'course_info']) }}" size="sm" variant="ghost">Course template</flux:button>
                <flux:button href="{{ route('tenant.knowledge.import.template', [$tenant, 'fee']) }}" size="sm" variant="ghost">Fee template</flux:button>
                <flux:button href="{{ route('tenant.knowledge.import.template', [$tenant, 'eligibility']) }}" size="sm" variant="ghost">Eligibility template</flux:button>
            </div>
        </div>
    @endunless

    @if ($notice)
        <div class="rounded-lg border border-emerald-800 bg-emerald-950/40 p-4 text-sm text-emerald-100">{{ $notice }}</div>
    @endif

    @if ($errorNotice)
        <div class="rounded-lg border border-red-800 bg-red-950/40 p-4 text-sm text-red-100">{{ $errorNotice }}</div>
    @endif

    @if ($preview)
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="sm">Validation preview</flux:heading>
                    <p class="mt-1 text-sm text-zinc-400">
                        {{ $preview->original_filename }} · {{ $preview->import_type->label() }} ·
                        {{ $preview->total_rows }} total · {{ $preview->valid_rows }} valid ·
                        {{ $preview->failed_rows }} invalid · {{ $preview->skipped_rows }} duplicates skipped
                    </p>
                </div>
                @if ($canImport && $preview->status === \App\Enums\Knowledge\KnowledgeImportStatus::Pending)
                    <flux:button wire:click="confirmImport" variant="primary" size="sm">Import valid rows</flux:button>
                @endif
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-zinc-400">
                        <tr>
                            <th class="px-3 py-2">Row</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Preview</th>
                            <th class="px-3 py-2">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview->rows as $row)
                            <tr class="border-t border-zinc-800">
                                <td class="px-3 py-2">{{ $row->row_number }}</td>
                                <td class="px-3 py-2">{{ $row->status->label() }}</td>
                                <td class="px-3 py-2 text-zinc-300">{{ Str::limit(collect($row->payload)->take(2)->map(fn ($value, $key) => $key.': '.$value)->implode(' · '), 120) }}</td>
                                <td class="px-3 py-2 text-zinc-400">{{ $row->error_message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-5">
        <flux:heading size="sm">Import history</flux:heading>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-zinc-400">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">File</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Rows</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $entry)
                        <tr class="border-t border-zinc-800">
                            <td class="px-3 py-2">{{ $entry->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $entry->original_filename }}</td>
                            <td class="px-3 py-2">{{ $entry->import_type->label() }}</td>
                            <td class="px-3 py-2">{{ $entry->status->label() }}</td>
                            <td class="px-3 py-2">{{ $entry->imported_rows }}/{{ $entry->valid_rows }} imported · {{ $entry->failed_rows }} failed · {{ $entry->skipped_rows }} skipped</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-zinc-500">No imports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
