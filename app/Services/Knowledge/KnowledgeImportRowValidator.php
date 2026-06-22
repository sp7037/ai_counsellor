<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\KnowledgeImportType;
use App\Enums\Knowledge\KnowledgeFeeType;
use App\Enums\Knowledge\KnowledgeImportRowStatus;
use App\Enums\Knowledge\KnowledgeItemType;
use App\Models\KnowledgeItem;
use App\Models\Tenant;

class KnowledgeImportRowValidator
{
    public function __construct(
        private readonly KnowledgeContentSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, string>  $row
     * @param  array<string, true>  $seenKeys
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    public function validate(Tenant $tenant, KnowledgeImportType $type, array $row, array &$seenKeys): array
    {
        return match ($type) {
            KnowledgeImportType::Faq => $this->validateFaq($tenant, $row, $seenKeys),
            KnowledgeImportType::CourseInfo => $this->validateCourseInfo($tenant, $row, $seenKeys),
            KnowledgeImportType::Fee => $this->validateFee($row),
            KnowledgeImportType::Eligibility => $this->validateEligibility($row),
        };
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, true>  $seenKeys
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function validateFaq(Tenant $tenant, array $row, array &$seenKeys): array
    {
        $question = trim($row['question'] ?? '');
        $answer = trim($row['answer'] ?? '');

        if ($question === '' || $answer === '') {
            return $this->failed('Question and answer are required.');
        }

        try {
            $this->sanitizer->title($question);
            $this->sanitizer->body($answer);
        } catch (\Throwable $exception) {
            return $this->failed($exception->getMessage());
        }

        $statusError = $this->statusError($row['status'] ?? '');

        if ($statusError !== null) {
            return $this->failed($statusError);
        }

        $key = $this->normalizeKey($question);

        if (isset($seenKeys[$key]) || $this->duplicateFaqExists($tenant, $question)) {
            return $this->skipped('Duplicate skipped.');
        }

        $seenKeys[$key] = true;

        return $this->valid($key);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, true>  $seenKeys
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function validateCourseInfo(Tenant $tenant, array $row, array &$seenKeys): array
    {
        $title = trim($row['title'] ?? '');
        $body = trim($row['body'] ?? '');

        if ($title === '' || $body === '') {
            return $this->failed('Title and body are required.');
        }

        try {
            $this->sanitizer->title($title);
            $this->sanitizer->body($body);
        } catch (\Throwable $exception) {
            return $this->failed($exception->getMessage());
        }

        $statusError = $this->statusError($row['status'] ?? '');

        if ($statusError !== null) {
            return $this->failed($statusError);
        }

        $key = $this->normalizeKey(KnowledgeItemType::CourseInfo->value.'|'.$title);

        if (isset($seenKeys[$key]) || $this->duplicateCourseExists($tenant, $title)) {
            return $this->skipped('Duplicate skipped.');
        }

        $seenKeys[$key] = true;

        return $this->valid($key);
    }

    /**
     * @param  array<string, string>  $row
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function validateFee(array $row): array
    {
        $label = trim($row['label'] ?? '');

        if ($label === '') {
            return $this->failed('Label is required.');
        }

        $feeType = strtolower(trim($row['fee_type'] ?? ''));

        if (! in_array($feeType, array_map(fn (KnowledgeFeeType $type) => $type->value, KnowledgeFeeType::cases()), true)) {
            return $this->failed('Fee type must be exact, starting_from, or range.');
        }

        if (! is_numeric($row['amount_minor'] ?? null) || (int) $row['amount_minor'] < 0) {
            return $this->failed('Amount minor must be a zero or positive number.');
        }

        $currency = strtoupper(trim($row['currency'] ?? ''));

        if (! in_array($currency, config('knowledge.supported_currencies', ['INR']), true)) {
            return $this->failed('Unsupported currency.');
        }

        $statusError = $this->statusError($row['status'] ?? '');

        if ($statusError !== null) {
            return $this->failed($statusError);
        }

        return $this->valid(null);
    }

    /**
     * @param  array<string, string>  $row
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function validateEligibility(array $row): array
    {
        $title = trim($row['title'] ?? '');
        $required = trim($row['required_criteria'] ?? '');

        if ($title === '' || $required === '') {
            return $this->failed('Title and required_criteria are required.');
        }

        try {
            $this->sanitizer->title($title);
            $this->sanitizer->optionalText($required, 4000);
        } catch (\Throwable $exception) {
            return $this->failed($exception->getMessage());
        }

        if (isset($row['priority']) && $row['priority'] !== '' && ! is_numeric($row['priority'])) {
            return $this->failed('Priority must be numeric.');
        }

        $statusError = $this->statusError($row['status'] ?? '');

        if ($statusError !== null) {
            return $this->failed($statusError);
        }

        return $this->valid(null);
    }

    private function statusError(string $status): ?string
    {
        $status = strtolower(trim($status));

        if ($status === '') {
            return null;
        }

        if (! in_array($status, ['draft', 'published'], true)) {
            return 'Status must be draft or published.';
        }

        return null;
    }

    private function duplicateFaqExists(Tenant $tenant, string $question): bool
    {
        $normalized = $this->normalizeKey($question);

        return KnowledgeItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', KnowledgeItemType::Faq->value)
            ->get(['title', 'draft_title'])
            ->contains(fn (KnowledgeItem $item) => $this->normalizeKey((string) ($item->draft_title ?? $item->title)) === $normalized);
    }

    private function duplicateCourseExists(Tenant $tenant, string $title): bool
    {
        $normalized = $this->normalizeKey($title);

        return KnowledgeItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', KnowledgeItemType::CourseInfo->value)
            ->get(['title', 'draft_title'])
            ->contains(fn (KnowledgeItem $item) => $this->normalizeKey((string) ($item->draft_title ?? $item->title)) === $normalized);
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    /**
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function valid(?string $seenKey): array
    {
        return [
            'status' => KnowledgeImportRowStatus::Valid,
            'error' => null,
            'seen_key' => $seenKey,
        ];
    }

    /**
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function failed(string $message): array
    {
        return [
            'status' => KnowledgeImportRowStatus::Failed,
            'error' => $message,
            'seen_key' => null,
        ];
    }

    /**
     * @return array{status: KnowledgeImportRowStatus, error: ?string, seen_key: ?string}
     */
    private function skipped(string $message): array
    {
        return [
            'status' => KnowledgeImportRowStatus::Skipped,
            'error' => $message,
            'seen_key' => null,
        ];
    }
}
