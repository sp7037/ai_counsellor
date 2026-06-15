<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    public function confirm(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('home'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Confirm password" description="Please confirm your password before continuing." />

    <form wire:submit="confirm" class="flex flex-col gap-6">
        <flux:input wire:model="password" label="{{ __('Password') }}" type="password" required autocomplete="current-password" />
        <flux:button variant="primary" type="submit" class="w-full">{{ __('Confirm') }}</flux:button>
    </form>
</div>
