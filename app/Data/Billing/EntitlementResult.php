<?php

namespace App\Data\Billing;

use App\Enums\Billing\EntitlementOutcome;
use App\Enums\Billing\PlanFeature;

readonly class EntitlementResult
{
    public function __construct(
        public EntitlementOutcome $outcome,
        public PlanFeature $feature,
        public ?int $limit = null,
        public ?int $used = null,
        public ?int $warningThresholdPercent = null,
        public ?string $message = null,
    ) {}

    public function isAllowed(): bool
    {
        return $this->outcome->isAllowed();
    }

    public function denyReason(): string
    {
        return $this->message ?? $this->outcome->safeMessageForTenantAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function toWidgetArray(): array
    {
        return [
            'code' => $this->outcome->safeWidgetCode(),
            'allowed' => $this->isAllowed(),
        ];
    }
}
