<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path forwards visitors to the dashboard.
     */
    public function test_the_root_path_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }

    /**
     * Guests reaching the dashboard are sent to the login screen.
     */
    public function test_the_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
