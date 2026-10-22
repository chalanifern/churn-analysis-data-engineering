<?php

declare(strict_types=1);

use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/customers')->assertRedirect('/login');
        $this->get('/churn-analysis')->assertRedirect('/login');
        $this->get('/risk-retention')->assertRedirect('/login');
    }

    public function test_login_page_is_public(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in', false);
    }

    public function test_root_redirects_into_the_application(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@sltmobitel.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
