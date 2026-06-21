<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformSystemHealthService
{
    /**
     * @return array<int, array<string, string>>
     */
    public function checks(): array
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks[] = ['name' => 'Database', 'status' => 'ok', 'detail' => 'Connection successful.'];
        } catch (\Throwable $exception) {
            $checks[] = ['name' => 'Database', 'status' => 'error', 'detail' => 'Database connection failed.'];
        }

        $checks[] = [
            'name' => 'Migrations',
            'status' => Schema::hasTable('ai_runs') ? 'ok' : 'error',
            'detail' => Schema::hasTable('ai_runs') ? 'Core tables present.' : 'Required tables missing.',
        ];

        $checks[] = [
            'name' => 'AI test provider',
            'status' => config('ai.providers.fake.enabled') ? 'ok' : 'warning',
            'detail' => config('ai.providers.fake.enabled') ? 'Fake provider enabled for offline tests.' : 'Fake provider disabled.',
        ];

        $checks[] = [
            'name' => 'Platform credential',
            'status' => app(PlatformSettingsService::class)->platformCredentialConfigured() ? 'ok' : 'warning',
            'detail' => 'Default AI provider credential status (value not shown).',
        ];

        return $checks;
    }
}
