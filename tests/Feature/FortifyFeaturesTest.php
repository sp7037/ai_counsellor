<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class FortifyFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_two_factor_features_are_disabled(): void
    {
        $features = config('fortify.features');

        $this->assertNotContains(Features::registration(), $features);
        $this->assertNotContains(Features::twoFactorAuthentication(), $features);
        $this->assertNotContains(Features::passkeys(), $features);
        $this->assertNotContains(Features::emailVerification(), $features);
    }

    public function test_passkey_and_two_factor_routes_are_not_registered(): void
    {
        $routes = collect(Route::getRoutes())->map->uri()->implode(' ');

        $this->assertStringNotContainsString('passkey', $routes);
        $this->assertStringNotContainsString('two-factor', $routes);
    }

    public function test_fortify_does_not_register_duplicate_email_verification_route(): void
    {
        $names = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();

        $this->assertSame(1, $names->filter(fn (string $name) => $name === 'verification.verify')->count());
        $this->assertTrue($names->contains('verification.notice'));
    }
}
