<?php

namespace App\Services\Configuration;

use App\Enums\Audit\AuditAction;
use App\Enums\Configuration\DayOfWeek;
use App\Models\Tenant;
use App\Models\TenantOfficeHour;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantOfficeHoursService
{
    public function __construct(
        private readonly TenantConfigurationResolver $resolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param  array<int, array<string, mixed>>  $schedule */
    public function replaceSchedule(Tenant $tenant, array $schedule, User $actor): void
    {
        DB::transaction(function () use ($tenant, $schedule, $actor): void {
            $before = $tenant->officeHours()->orderBy('day_of_week')->get()->map(fn (TenantOfficeHour $hour) => [
                'day_of_week' => $hour->day_of_week->value,
                'opens_at' => $hour->opens_at,
                'closes_at' => $hour->closes_at,
                'is_closed' => $hour->is_closed,
            ])->all();

            $tenant->officeHours()->delete();

            foreach (DayOfWeek::cases() as $day) {
                $entry = collect($schedule)->firstWhere('day_of_week', $day->value)
                    ?? collect($schedule)->firstWhere('day_of_week', (string) $day->value);

                if ($entry === null) {
                    $tenant->officeHours()->create([
                        'day_of_week' => $day->value,
                        'is_closed' => true,
                    ]);

                    continue;
                }

                $isClosed = (bool) ($entry['is_closed'] ?? false);
                $opensAt = $entry['opens_at'] ?? null;
                $closesAt = $entry['closes_at'] ?? null;

                if (! $isClosed) {
                    if ($opensAt === null || $closesAt === null) {
                        throw ValidationException::withMessages([
                            'schedule' => 'Open and close times are required for open days.',
                        ]);
                    }

                    if ($opensAt >= $closesAt) {
                        throw ValidationException::withMessages([
                            'schedule' => 'Close time must be after open time.',
                        ]);
                    }
                }

                $tenant->officeHours()->create([
                    'day_of_week' => $day->value,
                    'opens_at' => $isClosed ? null : $opensAt,
                    'closes_at' => $isClosed ? null : $closesAt,
                    'is_closed' => $isClosed,
                ]);
            }

            $after = $tenant->officeHours()->orderBy('day_of_week')->get()->map(fn (TenantOfficeHour $hour) => [
                'day_of_week' => $hour->day_of_week->value,
                'opens_at' => $hour->opens_at,
                'closes_at' => $hour->closes_at,
                'is_closed' => $hour->is_closed,
            ])->all();

            $this->auditLogger->log(
                AuditAction::OfficeHoursUpdated,
                null,
                $tenant->id,
                ['before' => $before, 'after' => $after],
                $actor,
            );
        });
    }
}
