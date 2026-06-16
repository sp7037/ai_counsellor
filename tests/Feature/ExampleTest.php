<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_login_page_is_available(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_health_endpoint_is_available_without_secrets(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertDontSee('APP_KEY');
        $response->assertDontSee('password');
    }
}
