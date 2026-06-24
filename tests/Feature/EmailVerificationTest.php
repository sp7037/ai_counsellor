<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Models\User;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_verify_email_via_signed_link(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $owner->forceFill(['email_verified_at' => null])->save();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $owner->id, 'hash' => sha1($owner->email)],
        );

        $this->get($url)
            ->assertRedirect(app(PostLoginRedirect::class)->intendedUrl($owner->fresh()))
            ->assertSessionHasNoErrors();

        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($owner->fresh());
    }

    public function test_verification_link_works_while_logged_in_as_different_user(): void
    {
        ['user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $owner->forceFill(['email_verified_at' => null])->save();
        $otherUser = User::factory()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $owner->id, 'hash' => sha1($owner->email)],
        );

        $this->actingAs($otherUser)
            ->get($url)
            ->assertRedirect(app(PostLoginRedirect::class)->intendedUrl($owner->fresh()));

        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($owner->fresh());
    }

    public function test_invalid_verification_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1('wrong@example.test')],
        );

        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))->assertForbidden();
    }

    public function test_already_verified_user_is_logged_in_and_redirected(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $owner->id, 'hash' => sha1($owner->email)],
        );

        $this->get($url)
            ->assertRedirect(app(PostLoginRedirect::class)->intendedUrl($owner));

        $this->assertAuthenticatedAs($owner);
    }
}
