<?php

use App\Enums\UserStatus;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(PostLoginRedirect $redirects): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user->status === UserStatus::Disabled) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been disabled.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->redirect($redirects->intendedUrl($user), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Log in to your account" description="Managed B2B access — contact your administrator if you need an account." />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input wire:model="email" label="{{ __('Email address') }}" type="email" required autofocus autocomplete="email" />

        <div class="relative">
            <flux:input wire:model="password" label="{{ __('Password') }}" type="password" required autocomplete="current-password" />

            <x-text-link class="absolute right-0 top-0 text-sm" href="{{ route('password.request') }}">
                {{ __('Forgot password?') }}
            </x-text-link>
        </div>

        <flux:checkbox wire:model="remember" label="{{ __('Remember me') }}" />

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
    </form>
</div>
