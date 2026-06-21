<?php

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeDocumentService;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    use WithFileUploads;

    public Tenant $tenant;

    public $upload;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [KnowledgeDocument::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return ['documents' => KnowledgeDocument::query()->orderByDesc('created_at')->get()];
    }

    public function uploadDocument(KnowledgeDocumentService $service): void
    {
        $this->authorize('upload', [KnowledgeDocument::class, $this->tenant]);

        $allowedMimes = config('knowledge.allowed_document_mimes', []);

        $this->validate([
            'upload' => [
                'required',
                'file',
                'max:'.config('knowledge.max_document_size_kb', 10240),
                function (string $attribute, $value, \Closure $fail) use ($allowedMimes): void {
                    if (! in_array($value->getMimeType(), $allowedMimes, true)) {
                        $fail('Document type is not allowed.');
                    }

                    if (preg_match('/\.(php|html|js|exe|bat|sh|svg)$/i', $value->getClientOriginalName())) {
                        $fail('Dangerous file extension rejected.');
                    }
                },
            ],
        ]);

        $service->upload($this->tenant, $this->upload, auth()->user());
        $this->reset('upload');
    }

    public function remove(string $uuid, KnowledgeDocumentService $service): void
    {
        $document = KnowledgeDocument::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $document);
        $service->remove($document, auth()->user());
    }
}; ?>

<x-slot:heading>Source documents</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('upload', [App\Models\KnowledgeDocument::class, $tenant])
        <form wire:submit="uploadDocument" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:input wire:model="upload" type="file" label="Document (PDF, DOC, DOCX, TXT)" />
            <flux:button type="submit" variant="primary">Upload</flux:button>
        </form>
    @endcan

    <div class="grid gap-3">
        @forelse ($documents as $document)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $document->display_name }}</div>
                    <div class="text-zinc-500">{{ $document->mime_type }} · {{ number_format($document->size_bytes / 1024, 1) }} KB</div>
                </div>
                <div class="flex gap-2">
                    @can('download', $document)
                        <flux:button href="{{ route('tenant.knowledge.documents.download', [$tenant, $document]) }}" size="sm" variant="ghost">Download</flux:button>
                    @endcan
                    @can('delete', $document)
                        <flux:button wire:click="remove('{{ $document->uuid }}')" wire:confirm="Remove this document?" size="sm" variant="danger">Remove</flux:button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-zinc-500">No source documents uploaded yet.</p>
        @endforelse
    </div>
</div>
