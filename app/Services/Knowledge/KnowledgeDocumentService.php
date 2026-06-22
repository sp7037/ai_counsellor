<?php

namespace App\Services\Knowledge;

use App\Enums\Audit\AuditAction;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KnowledgeDocumentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function upload(Tenant $tenant, UploadedFile $file, User $actor, ?KnowledgeItem $item = null): KnowledgeDocument
    {
        $this->assertSafeFile($file);

        return DB::transaction(function () use ($tenant, $file, $actor, $item): KnowledgeDocument {
            $extension = match ($file->getMimeType()) {
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'text/plain' => 'txt',
                default => throw ValidationException::withMessages(['file' => 'Unsupported document type.']),
            };

            $path = sprintf(
                'knowledge-documents/%s/%s.%s',
                $tenant->uuid,
                Str::lower(Str::random(40)),
                $extension,
            );

            Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

            $document = KnowledgeDocument::query()->create([
                'tenant_id' => $tenant->id,
                'knowledge_item_id' => $item?->id,
                'display_name' => $this->safeDisplayName($file->getClientOriginalName()),
                'storage_path' => $path,
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'status' => 'stored',
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(AuditAction::KnowledgeSourceUploaded, $document, $tenant->id, [
                'display_name' => $document->display_name,
                'knowledge_item_uuid' => $item?->uuid,
            ], $actor);

            return $document;
        });
    }

    public function remove(KnowledgeDocument $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $snapshot = ['uuid' => $document->uuid, 'display_name' => $document->display_name];
            $tenantId = $document->tenant_id;

            Storage::disk('local')->delete($document->storage_path);
            $document->delete();

            $this->auditLogger->log(AuditAction::KnowledgeSourceRemoved, null, $tenantId, ['before' => $snapshot], $actor);
        });
    }

    private function assertSafeFile(UploadedFile $file): void
    {
        $maxKb = config('knowledge.max_document_size_kb', 10240);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['file' => 'Document exceeds size limit.']);
        }

        if (! in_array($file->getMimeType(), config('knowledge.allowed_document_mimes', []), true)) {
            throw ValidationException::withMessages(['file' => 'Document type is not allowed.']);
        }

        $name = strtolower($file->getClientOriginalName());
        if (preg_match('/\.(php|html|js|exe|bat|sh|svg)$/i', $name)) {
            throw ValidationException::withMessages(['file' => 'Dangerous file extension rejected.']);
        }
    }

    private function safeDisplayName(string $original): string
    {
        $name = basename(str_replace(['\\', '/'], '', $original));
        $name = preg_replace('/[^\w\s\.\-]/', '', $name) ?? 'document';

        return mb_substr($name, 0, 200) ?: 'document';
    }
}
