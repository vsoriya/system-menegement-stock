<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The My account page, which every role shares.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Original Name',
            'email' => 'me@example.com',
            'password' => Hash::make('current-secret'),
        ]);
    }

    public function test_the_name_can_be_changed_without_a_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'New Name',
                'email' => 'me@example.com',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Original Name',
                'email' => 'moved@example.com',
            ])
            ->assertSessionHasErrors('email_current_password');

        $this->assertSame('me@example.com', $user->fresh()->email);
    }

    public function test_the_email_changes_when_the_password_is_given(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Original Name',
                'email' => 'moved@example.com',
                'email_current_password' => 'current-secret',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('moved@example.com', $user->fresh()->email);
    }

    public function test_a_wrong_current_password_blocks_the_email_change(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Original Name',
                'email' => 'moved@example.com',
                'email_current_password' => 'guessing',
            ])
            ->assertSessionHasErrors('email_current_password');

        $this->assertSame('me@example.com', $user->fresh()->email);
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        $user = $this->user();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Original Name',
                'email' => 'taken@example.com',
                'email_current_password' => 'current-secret',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_keeping_your_own_email_is_not_treated_as_a_clash(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Same Email',
                'email' => 'me@example.com',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_the_password_can_be_changed(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'current-secret',
                'password' => 'a-brand-new-one',
                'password_confirmation' => 'a-brand-new-one',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('a-brand-new-one', $user->fresh()->password));
    }

    public function test_the_password_change_needs_the_current_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'wrong',
                'password' => 'a-brand-new-one',
                'password_confirmation' => 'a-brand-new-one',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('current-secret', $user->fresh()->password));
    }

    public function test_the_confirmation_has_to_match(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'current-secret',
                'password' => 'a-brand-new-one',
                'password_confirmation' => 'something-else',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_reusing_the_same_password_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'current-secret',
                'password' => 'current-secret',
                'password_confirmation' => 'current-secret',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_a_short_password_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'current-secret',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_changing_the_password_does_not_sign_you_out(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'current-secret',
            'password' => 'a-brand-new-one',
            'password_confirmation' => 'a-brand-new-one',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_you_cannot_change_your_own_role_from_this_page(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Original Name',
            'email' => 'me@example.com',
            'role' => 'admin',
        ]);

        // role is not in the payload the request allows through.
        $this->assertFalse($user->fresh()->isAdmin());
    }
}
