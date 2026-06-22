<?php

namespace App\Services\Knowledge;

use App\Enums\Billing\PlanFeature;
use App\Enums\Knowledge\KnowledgeImportRowStatus;
use App\Enums\Knowledge\KnowledgeImportStatus;
use App\Enums\Knowledge\KnowledgeImportType;
use App\Enums\Knowledge\KnowledgeItemType;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Models\KnowledgeImport;
use App\Models\KnowledgeImportRow;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KnowledgeImportService
{
    public function __construct(
        private readonly KnowledgeImportCsvParser $parser,
        private readonly KnowledgeImportRowValidator $validator,
        private readonly KnowledgeItemService $items,
        private readonly KnowledgeFeeService $fees,
        private readonly EligibilityRuleService $eligibility,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function canImport(Tenant $tenant): bool
    {
        return $this->entitlements->check($tenant, PlanFeature::KnowledgeBase)->isAllowed();
    }

    public function validateUpload(
        Tenant $tenant,
        User $actor,
        KnowledgeImportType $type,
        UploadedFile $file,
    ): KnowledgeImport {
        $this->assertEntitled($tenant);

        if (! in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Only CSV files are supported. For Excel files, please export as CSV before upload.',
            ]);
        }

        $parsed = $this->parser->parse($file->getRealPath(), $type);

        return DB::transaction(function () use ($tenant, $actor, $type, $file, $parsed): KnowledgeImport {
            $import = KnowledgeImport::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $actor->id,
                'import_type' => $type,
                'original_filename' => $file->getClientOriginalName(),
                'status' => KnowledgeImportStatus::Validating,
                'total_rows' => count($parsed['rows']),
            ]);

            $seenKeys = [];
            $valid = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($parsed['rows'] as $index => $row) {
                $result = $this->validator->validate($tenant, $type, $row, $seenKeys);

                KnowledgeImportRow::query()->create([
                    'knowledge_import_id' => $import->id,
                    'row_number' => $index + 2,
                    'status' => $result['status'],
                    'payload' => $row,
                    'error_message' => $result['error'],
                ]);

                match ($result['status']) {
                    KnowledgeImportRowStatus::Valid => $valid++,
                    KnowledgeImportRowStatus::Failed => $failed++,
                    KnowledgeImportRowStatus::Skipped => $skipped++,
                    default => null,
                };
            }

            $import->update([
                'valid_rows' => $valid,
                'failed_rows' => $failed,
                'skipped_rows' => $skipped,
                'status' => $valid > 0 ? KnowledgeImportStatus::Pending : KnowledgeImportStatus::Failed,
                'error_summary' => $this->buildErrorSummary($valid, $failed, $skipped),
            ]);

            return $import->fresh(['rows']);
        });
    }

    public function execute(KnowledgeImport $import, Tenant $tenant, User $actor): KnowledgeImport
    {
        $this->assertEntitled($tenant);

        if ($import->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages(['import' => 'Import does not belong to this tenant.']);
        }

        if ($import->status !== KnowledgeImportStatus::Pending) {
            throw ValidationException::withMessages(['import' => 'This import is not ready to run.']);
        }

        return DB::transaction(function () use ($import, $tenant, $actor): KnowledgeImport {
            $imported = 0;
            $failed = 0;

            $import->rows()
                ->where('status', KnowledgeImportRowStatus::Valid->value)
                ->orderBy('row_number')
                ->each(function (KnowledgeImportRow $row) use ($tenant, $actor, $import, &$imported, &$failed): void {
                    try {
                        $this->importRow($tenant, $actor, $import->import_type, $row);
                        $row->update(['status' => KnowledgeImportRowStatus::Imported->value, 'error_message' => null]);
                        $imported++;
                    } catch (\Throwable $exception) {
                        $row->update([
                            'status' => KnowledgeImportRowStatus::Failed->value,
                            'error_message' => $exception->getMessage(),
                        ]);
                        $failed++;
                    }
                });

            $import->update([
                'imported_rows' => $imported,
                'failed_rows' => $import->rows()->where('status', KnowledgeImportRowStatus::Failed->value)->count(),
                'status' => KnowledgeImportStatus::Completed,
                'error_summary' => $failed > 0
                    ? "{$imported} row(s) imported, {$failed} row(s) failed during import."
                    : "{$imported} row(s) imported successfully.",
            ]);

            return $import->fresh(['rows']);
        });
    }

    private function importRow(Tenant $tenant, User $actor, KnowledgeImportType $type, KnowledgeImportRow $row): void
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $publish = strtolower(trim((string) ($payload['status'] ?? ''))) === 'published';

        match ($type) {
            KnowledgeImportType::Faq => $this->importFaq($tenant, $actor, $payload, $publish),
            KnowledgeImportType::CourseInfo => $this->importCourseInfo($tenant, $actor, $payload, $publish),
            KnowledgeImportType::Fee => $this->importFee($tenant, $actor, $payload, $publish),
            KnowledgeImportType::Eligibility => $this->importEligibility($tenant, $actor, $payload, $publish),
        };
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function importFaq(Tenant $tenant, User $actor, array $payload, bool $publish): void
    {
        $item = $this->items->createDraft($tenant, [
            'type' => KnowledgeItemType::Faq->value,
            'title' => $payload['question'],
            'body' => $this->composeBody($payload['answer'], $payload),
        ], $actor);

        if ($publish) {
            $this->items->publish($item->fresh(), $actor);
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function importCourseInfo(Tenant $tenant, User $actor, array $payload, bool $publish): void
    {
        $item = $this->items->createDraft($tenant, [
            'type' => KnowledgeItemType::CourseInfo->value,
            'title' => $payload['title'],
            'body' => $this->composeBody($payload['body'], $payload),
        ], $actor);

        if ($publish) {
            $this->items->publish($item->fresh(), $actor);
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function importFee(Tenant $tenant, User $actor, array $payload, bool $publish): void
    {
        $fee = $this->fees->create($tenant, [
            'label' => $payload['label'],
            'fee_type' => $payload['fee_type'],
            'amount_minor' => (int) $payload['amount_minor'],
            'currency' => strtoupper($payload['currency']),
            'notes' => $payload['notes'] ?? null,
        ], $actor);

        if ($publish) {
            $this->fees->publish($fee->fresh(), $actor);
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function importEligibility(Tenant $tenant, User $actor, array $payload, bool $publish): void
    {
        $rule = $this->eligibility->create($tenant, [
            'title' => $payload['title'],
            'required_criteria' => $payload['required_criteria'],
            'preferred_criteria' => $payload['preferred_criteria'] ?? null,
            'priority' => $payload['priority'] ?? 100,
        ], $actor);

        if ($publish) {
            $this->eligibility->publish($rule->fresh(), $actor);
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function composeBody(string $body, array $payload): string
    {
        $parts = [trim($body)];

        if (! empty($payload['category'])) {
            $parts[] = 'Category: '.trim($payload['category']);
        }

        if (! empty($payload['tags'])) {
            $parts[] = 'Tags: '.trim($payload['tags']);
        }

        return implode("\n\n", array_filter($parts));
    }

    private function buildErrorSummary(int $valid, int $failed, int $skipped): ?string
    {
        if ($valid === 0) {
            return 'No valid rows found. Fix the CSV and try again.';
        }

        $parts = ["{$valid} valid row(s) ready to import."];

        if ($failed > 0) {
            $parts[] = "{$failed} invalid row(s).";
        }

        if ($skipped > 0) {
            $parts[] = "{$skipped} duplicate row(s) skipped.";
        }

        return implode(' ', $parts);
    }

    private function assertEntitled(Tenant $tenant): void
    {
        try {
            $this->entitlements->assertAllowed($tenant, PlanFeature::KnowledgeBase);
        } catch (EntitlementDeniedException $exception) {
            throw ValidationException::withMessages(['import' => $exception->getMessage()]);
        }
    }
}
