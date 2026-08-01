<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'clerk@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'clerk@example.com',
            'password' => 'secret1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_wrong_password_is_refused(): void
    {
        User::factory()->create([
            'email' => 'clerk@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'clerk@example.com',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Switching someone off has to actually keep them out, otherwise the
     * inactive flag is decoration.
     */
    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        User::factory()->inactive()->create([
            'email' => 'gone@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'gone@example.com',
            'password' => 'secret1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        User::factory()->create([
            'email' => 'clerk@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        // The limiter allows five tries.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => 'clerk@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => 'clerk@example.com',
            'password' => 'secret1234',
        ]);

        // Even the correct password is held off once the limit is hit.
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_password_reset_link_can_be_requested(): void
    {
        User::factory()->create(['email' => 'clerk@example.com']);

        $this->post(route('password.email'), ['email' => 'clerk@example.com'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'clerk@example.com']);
    }

    /**
     * An unknown address must get the same answer as a known one, so the form
     * cannot be used to work out who has an account.
     */
    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();
    }

    public function test_a_signed_in_user_is_kept_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect();
    }
}
