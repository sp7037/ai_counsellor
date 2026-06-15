<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Enums\Tenancy\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class BootstrapPlatformTenant extends Command
{
    protected $signature = 'platform:bootstrap-tenant
                            {--tenant-name= : Organisation name (required when creating a new tenant)}
                            {--tenant-slug= : Tenant slug (auto-generated from name when creating)}
                            {--name= : Tenant owner display name}
                            {--email= : Tenant owner email}
                            {--plan=trial : Plan code to assign when the tenant has no subscription}
                            {--no-activate : Leave the tenant in pending status}';

    protected $description = 'Create or update a tenant with an owner account (local development only)';

    public function handle(
        TenantLifecycleService $tenantLifecycle,
        SubscriptionLifecycleService $subscriptionLifecycle,
    ): int {
        if (app()->environment('production')) {
            $this->error('This command is disabled in production.');

            return self::FAILURE;
        }

        $actor = $this->resolvePlatformActor();
        if ($actor === null) {
            $this->error('No platform super-admin found. Run platform:create-super-admin first.');

            return self::FAILURE;
        }

        Auth::login($actor);

        $ownerName = $this->option('name') ?: $this->ask('Tenant owner name');
        $ownerEmail = $this->option('email') ?: $this->ask('Tenant owner email');
        $password = $this->resolvePassword();

        $validator = Validator::make(
            [
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', Password::min(12)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $tenant = $this->resolveTenant($tenantLifecycle);
            $owner = $this->resolveOwner($tenantLifecycle, $ownerName, $ownerEmail, $password);
            $this->ensureOwnerMembership($tenantLifecycle, $tenant, $owner, $actor);

            if (! $this->option('no-activate') && $tenant->status !== TenantStatus::Active) {
                $tenant = $tenantLifecycle->activate($tenant, $actor);
            }

            $this->assignPlanIfNeeded($subscriptionLifecycle, $tenant, $actor);

            $tenant->refresh();

            $this->info('Tenant ready: '.$tenant->name.' ('.$tenant->slug.')');
            $this->info('Tenant owner ready: '.$owner->email);
            $this->line('Dashboard: '.route('tenant.dashboard', $tenant));
            $this->line('Log in at: '.url('/login'));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } finally {
            Auth::logout();
        }
    }

    private function resolvePlatformActor(): ?User
    {
        return User::query()
            ->where('platform_role', PlatformRole::SuperAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->orderBy('id')
            ->first();
    }

    private function resolveTenant(TenantLifecycleService $tenantLifecycle): Tenant
    {
        $slug = $this->option('tenant-slug');

        if (is_string($slug) && $slug !== '') {
            $tenant = Tenant::query()->where('slug', $slug)->first();
            if ($tenant !== null) {
                return $tenant;
            }
        }

        $tenantName = $this->option('tenant-name') ?: $this->ask('Organisation name');
        $slug = is_string($slug) && $slug !== ''
            ? $slug
            : ($this->option('tenant-slug') ?: $tenantLifecycle->generateSlug($tenantName));

        $validator = Validator::make(
            [
                'tenant_name' => $tenantName,
                'tenant_slug' => $slug,
            ],
            [
                'tenant_name' => ['required', 'string', 'max:255'],
                'tenant_slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            ],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $tenantLifecycle->createTenant(
            [
                'name' => $tenantName,
                'slug' => $slug,
            ],
            owner: null,
            actor: Auth::user(),
        );
    }

    private function resolveOwner(
        TenantLifecycleService $tenantLifecycle,
        string $name,
        string $email,
        string $password,
    ): User {
        $user = User::query()->where('email', $email)->first();

        if ($user?->isPlatformSuperAdmin()) {
            throw ValidationException::withMessages([
                'email' => 'Platform administrator accounts cannot be used as tenant owners.',
            ]);
        }

        if ($user === null) {
            return $tenantLifecycle->createOwnerUser($name, $email, $password);
        }

        $user->update([
            'name' => $name,
            'password' => Hash::make($password),
            'status' => UserStatus::Active->value,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        return $user->fresh();
    }

    private function ensureOwnerMembership(
        TenantLifecycleService $tenantLifecycle,
        Tenant $tenant,
        User $owner,
        User $actor,
    ): void {
        $exists = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->exists();

        if ($exists) {
            return;
        }

        $tenantLifecycle->addOwner($tenant, $owner, $actor);
    }

    private function assignPlanIfNeeded(
        SubscriptionLifecycleService $subscriptionLifecycle,
        Tenant $tenant,
        User $actor,
    ): void {
        if ($tenant->subscription()->exists()) {
            return;
        }

        $planCode = $this->option('plan');
        if (! is_string($planCode) || $planCode === '') {
            return;
        }

        $plan = Plan::query()->where('code', $planCode)->first();
        if ($plan === null) {
            $this->warn("Plan '{$planCode}' was not found. Run: php artisan db:seed --class=PlansSeeder");

            return;
        }

        $subscriptionLifecycle->assignPlan($tenant, $plan, $actor, reason: 'Local bootstrap');
    }

    private function resolvePassword(): string
    {
        $envPassword = env('TENANT_BOOTSTRAP_PASSWORD');

        if (is_string($envPassword) && $envPassword !== '') {
            $this->warn('Using TENANT_BOOTSTRAP_PASSWORD from the environment. Unset it after bootstrap.');

            return $envPassword;
        }

        do {
            $password = $this->secret('Tenant owner password (min 12 characters)');
            $confirmation = $this->secret('Confirm password');
        } while ($password !== $confirmation && $this->retryPasswordEntry());

        return $password;
    }

    private function retryPasswordEntry(): bool
    {
        $this->error('Password confirmation did not match. Try again.');

        return true;
    }
}
