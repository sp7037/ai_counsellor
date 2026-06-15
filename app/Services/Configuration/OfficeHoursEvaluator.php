<?php

namespace App\Services\Configuration;

use App\Enums\Configuration\DayOfWeek;
use App\Models\Tenant;
use App\Models\TenantOfficeHour;
use Carbon\Carbon;

class OfficeHoursEvaluator
{
    public function __construct(
        private readonly TenantConfigurationResolver $resolver,
    ) {}

    public function evaluate(Tenant $tenant, ?Carbon $moment = null): array
    {
        $this->resolver->ensureDefaultOfficeHours($tenant);

        $timezone = $tenant->timezone ?: 'Asia/Kolkata';
        $moment ??= Carbon::now($timezone);
        $local = $moment->copy()->timezone($timezone);
        $day = DayOfWeek::fromCarbonDay($local->dayOfWeek);

        /** @var TenantOfficeHour|null $hours */
        $hours = $tenant->officeHours()->where('day_of_week', $day->value)->first();

        if ($hours === null || $hours->is_closed || $hours->opens_at === null || $hours->closes_at === null) {
            return [
                'is_open' => false,
                'timezone' => $timezone,
                'message' => 'We are currently outside office hours.',
            ];
        }

        $opens = $local->copy()->setTimeFromTimeString((string) $hours->opens_at);
        $closes = $local->copy()->setTimeFromTimeString((string) $hours->closes_at);
        $isOpen = $local->betweenIncluded($opens, $closes);

        return [
            'is_open' => $isOpen,
            'timezone' => $timezone,
            'message' => $isOpen ? null : 'We are currently outside office hours.',
        ];
    }
}
