<?php

use App\Enums\Knowledge\KnowledgeImportRowStatus;
use App\Enums\Knowledge\KnowledgeImportStatus;
use App\Enums\Knowledge\KnowledgeImportType;
use App\Models\KnowledgeImport;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public bool $isValidating = false;

    public function mount(Tenant $tenant, KnowledgeImportService $imports): void
    {
        Gate::authorize('viewTenantKnowledge', $tenant);
        $this->tenant = $tenant;

        if (! $imports->canImport($tenant)) {
            return;
        }

        Gate::authorize('manageTenantKnowledge', $tenant);
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
        Gate::authorize('manageTenantKnowledge', $this->tenant);
        $this->reset('notice', 'errorNotice', 'previewImportId');
        $this->isValidating = true;

        if (! $imports->canImport($this->tenant)) {
            $this->errorNotice = 'Knowledge base import is not available on your current plan.';
            $this->isValidating = false;

            return;
        }

        try {
            $validated = $this->validate([
                'importType' => ['required', 'in:faq,course_info,fee,eligibility'],
                'csvFile' => ['required', 'file', 'max:2048', 'extensions:csv,txt'],
            ]);

            $import = $imports->validateUpload(
                $this->tenant,
                auth()->user(),
                KnowledgeImportType::from($validated['importType']),
                $this->csvFile,
            );

            $this->previewImportId = $import->id;

            if ($import->valid_rows > 0) {
                $this->notice = $import->error_summary ?? 'Validation completed.';
            } else {
                $this->errorNotice = $import->error_summary ?? 'No valid rows found. Fix the CSV and try again.';
            }

            $this->reset('csvFile');
        } catch (ValidationException $exception) {
            $this->applyValidationErrors($exception);
        } finally {
            $this->isValidating = false;
        }
    }

    public function confirmImport(KnowledgeImportService $imports): void
    {
        Gate::authorize('manageTenantKnowledge', $this->tenant);
        $this->reset('notice', 'errorNotice');

        if ($this->previewImportId === null) {
            $this->errorNotice = 'Validate a CSV file before importing.';

            return;
        }

        try {
            $import = KnowledgeImport::query()->findOrFail($this->previewImportId);
            $result = $imports->execute($import, $this->tenant, auth()->user());

            $this->notice = $result->error_summary ?? 'Import completed.';
            $this->previewImportId = $result->id;
        } catch (ValidationException $exception) {
            $this->applyValidationErrors($exception);
        }
    }

    private function applyValidationErrors(ValidationException $exception): void
    {
        $messages = collect($exception->errors())->flatten();

        $this->errorNotice = $messages->first() ?? 'CSV validation failed.';

        foreach ($exception->errors() as $field => $fieldMessages) {
            $target = $field === 'file' ? 'csvFile' : $field;

            foreach ($fieldMessages as $message) {
                $this->addError($target, $message);
            }
        }
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

            <form wire:submit.prevent="validateUpload" class="mt-4 grid gap-4">
                <flux:select wire:model="importType" label="Import type">
                    @foreach ($importTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
                @error('importType')
                    <p class="text-sm text-red-300">{{ $message }}</p>
                @enderror

                <flux:input wire:model="csvFile" type="file" label="CSV file" accept=".csv,text/csv" />
                @error('csvFile')
                    <p class="text-sm text-red-300">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="validateUpload,csvFile">
                        <span wire:loading.remove wire:target="validateUpload">Validate & preview</span>
                        <span wire:loading wire:target="validateUpload">Validating CSV…</span>
                    </flux:button>
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
                        {{ $preview->original_filename }} · {{ $preview->import_type->label() }}
                    </p>
                </div>
                @if ($canImport && $preview->valid_rows > 0 && $preview->status === \App\Enums\Knowledge\KnowledgeImportStatus::Pending)
                    <flux:button wire:click="confirmImport" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="confirmImport">
                        <span wire:loading.remove wire:target="confirmImport">Confirm import</span>
                        <span wire:loading wire:target="confirmImport">Importing…</span>
                    </flux:button>
                @endif
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-zinc-700 bg-zinc-950/60 p-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Total rows</p>
                    <p class="mt-1 text-2xl font-semibold text-zinc-100">{{ $preview->total_rows }}</p>
                </div>
                <div class="rounded-lg border border-emerald-800/50 bg-emerald-950/30 p-3">
                    <p class="text-xs uppercase tracking-wide text-emerald-300/80">Valid rows</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-100">{{ $preview->valid_rows }}</p>
                </div>
                <div class="rounded-lg border border-red-800/50 bg-red-950/30 p-3">
                    <p class="text-xs uppercase tracking-wide text-red-300/80">Invalid rows</p>
                    <p class="mt-1 text-2xl font-semibold text-red-100">{{ $preview->failed_rows }}</p>
                </div>
                <div class="rounded-lg border border-amber-800/50 bg-amber-950/30 p-3">
                    <p class="text-xs uppercase tracking-wide text-amber-300/80">Duplicates skipped</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-100">{{ $preview->skipped_rows }}</p>
                </div>
            </div>

            @if ($preview->valid_rows === 0)
                <div class="mt-4 rounded-lg border border-red-800 bg-red-950/40 p-4 text-sm text-red-100">
                    No valid rows to import. Review the row errors below, fix your CSV, and validate again.
                </div>
            @endif

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
