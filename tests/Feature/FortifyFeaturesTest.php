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
    }

    public function test_passkey_and_two_factor_routes_are_not_registered(): void
    {
        $routes = collect(Route::getRoutes())->map->uri()->implode(' ');

        $this->assertStringNotContainsString('passkey', $routes);
        $this->assertStringNotContainsString('two-factor', $routes);
    }
}
