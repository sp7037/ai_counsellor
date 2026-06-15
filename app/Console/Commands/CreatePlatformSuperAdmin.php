<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformSuperAdmin extends Command
{
    protected $signature = 'platform:create-super-admin
                            {--name= : Display name}
                            {--email= : Email address}';

    protected $description = 'Create or update the platform super-admin account (local development only)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command is disabled in production.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->resolvePassword();

        $validator = Validator::make(
            compact('name', 'email', 'password'),
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

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'platform_role' => PlatformRole::SuperAdmin->value,
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
            ],
        );

        $this->info('Platform super-admin ready: '.$user->email);

        return self::SUCCESS;
    }

    private function resolvePassword(): string
    {
        $envPassword = env('PLATFORM_BOOTSTRAP_PASSWORD');

        if (is_string($envPassword) && $envPassword !== '') {
            $this->warn('Using PLATFORM_BOOTSTRAP_PASSWORD from the environment. Unset it after bootstrap.');

            return $envPassword;
        }

        do {
            $password = $this->secret('Password (min 12 characters)');
            $confirmation = $this->secret('Confirm password');
        } while ($password !== $confirmation && ! $this->retryPasswordEntry());

        return $password;
    }

    private function retryPasswordEntry(): bool
    {
        $this->error('Password confirmation did not match. Try again.');

        return true;
    }
}
