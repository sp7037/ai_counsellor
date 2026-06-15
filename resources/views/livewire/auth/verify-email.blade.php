<?php

use App\Livewire\Actions\Logout;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirect(app(PostLoginRedirect::class)->intendedUrl(Auth::user()), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="mt-4 flex flex-col gap-6">
    <x-auth-header
        title="Verify your email"
        :description="auth()->user()->isPlatformSuperAdmin()
            ? 'Verify your email to access the Platform Super Admin control plane.'
            : 'Please verify your email address before continuing.'"
    />

    @if (session('status') === 'verification-link-sent')
        <div class="text-center text-sm font-medium text-green-600">
            A new verification link has been sent to your email address.
        </div>
    @endif

    <flux:button wire:click="sendVerification" variant="primary" class="w-full">
        Resend verification email
    </flux:button>

    <button wire:click="logout" type="button" class="text-sm text-zinc-500 underline">
        Log out
    </button>
</div>
