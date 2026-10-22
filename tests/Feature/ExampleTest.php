<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_redirect_to_login_or_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
