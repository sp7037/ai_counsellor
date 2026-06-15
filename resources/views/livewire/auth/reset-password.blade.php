<?php

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(ResetUserPassword $resetUserPassword): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($resetUserPassword): void {
                $resetUserPassword->reset($user, [
                    'password' => $this->password,
                    'password_confirmation' => $this->password_confirmation,
                ]);
            },
        );

        if ($status !== Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Reset password" description="Enter your new password below" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="resetPassword" class="flex flex-col gap-6">
        <flux:input wire:model="email" label="{{ __('Email') }}" type="email" required autocomplete="email" />
        <flux:input wire:model="password" label="{{ __('Password') }}" type="password" required autocomplete="new-password" />
        <flux:input wire:model="password_confirmation" label="{{ __('Confirm password') }}" type="password" required autocomplete="new-password" />
        <flux:button type="submit" variant="primary" class="w-full">{{ __('Reset password') }}</flux:button>
    </form>
</div>
