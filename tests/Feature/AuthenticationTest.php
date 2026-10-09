<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_authenticated_page(): void
    {
        $response = $this->get('/genres');

        $response->assertRedirect('/login');

    }
}
