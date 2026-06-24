<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, PostLoginRedirect $redirects): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));

        if (! hash_equals(
            (string) $request->route('hash'),
            sha1($user->getEmailForVerification()),
        )) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            $this->authenticateVerifiedUser($request, $user);

            return redirect()->intended($redirects->intendedUrl($user));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $this->authenticateVerifiedUser($request, $user);

        return redirect()->intended($redirects->intendedUrl($user));
    }

    private function authenticateVerifiedUser(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
    }
}
