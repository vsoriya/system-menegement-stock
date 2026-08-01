<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The starting accounts.
 *
 * Seeders get run again long after go-live, usually to top up demo data. The old
 * version wrote the password on every run, so doing that quietly reset the real
 * administrator's password back to a value published in the repository. That is
 * what these tests are here to stop coming back.
 */
class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Resolved from the container rather than through artisan, so the seeder's
     * console output stays out of the test run.
     */
    private function seedUsers(): void
    {
        app(UserSeeder::class)->run();
    }

    public function test_seeding_creates_the_three_starting_accounts(): void
    {
        config(['app.seed_password' => 'correct-horse-battery']);

        $this->seedUsers();

        $this->assertDatabaseCount('users', 3);

        $roles = User::query()->pluck('role', 'email');

        $this->assertSame(UserRole::Admin, $roles['admin@example.com']);
        $this->assertSame(UserRole::Manager, $roles['manager@example.com']);
        $this->assertSame(UserRole::Staff, $roles['staff@example.com']);
    }

    public function test_a_supplied_password_is_used(): void
    {
        config(['app.seed_password' => 'correct-horse-battery']);

        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        $this->assertTrue(Hash::check('correct-horse-battery', $admin->password));
    }

    /**
     * The regression guard. If this ever fails, a fresh install is sitting on a
     * password that anyone reading the source already knows.
     */
    public function test_no_account_is_left_with_a_guessable_password(): void
    {
        config(['app.seed_password' => null]);

        $this->seedUsers();

        foreach (User::query()->get() as $user) {
            foreach (['password', 'secret', '12345678', 'admin'] as $guess) {
                $this->assertFalse(
                    Hash::check($guess, $user->password),
                    "The seeded account {$user->email} accepts the password {$guess}.",
                );
            }
        }
    }

    public function test_a_short_supplied_password_is_ignored_in_favour_of_a_generated_one(): void
    {
        config(['app.seed_password' => 'abc']);

        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        $this->assertFalse(Hash::check('abc', $admin->password));
    }

    public function test_re_seeding_does_not_reset_an_existing_password(): void
    {
        config(['app.seed_password' => 'first-run-password']);

        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        // The owner changes their password after go-live.
        $admin->forceFill(['password' => Hash::make('the-real-one')])->save();

        // Months later, someone re-seeds to top up demo data.
        config(['app.seed_password' => 'a-different-password']);
        $this->seedUsers();

        $admin->refresh();

        $this->assertTrue(Hash::check('the-real-one', $admin->password));
        $this->assertFalse(Hash::check('first-run-password', $admin->password));
        $this->assertFalse(Hash::check('a-different-password', $admin->password));
    }

    public function test_existing_passwords_can_be_reset_when_explicitly_requested(): void
    {
        config(['app.seed_password' => 'first-run-password']);

        $this->seedUsers();

        config([
            'app.seed_password' => 'new-shared-password',
            'app.seed_reset_passwords' => true,
        ]);

        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        $this->assertTrue(Hash::check('new-shared-password', $admin->password));
        $this->assertFalse(Hash::check('first-run-password', $admin->password));
    }

    public function test_re_seeding_does_not_create_duplicate_accounts(): void
    {
        config(['app.seed_password' => 'correct-horse-battery']);

        $this->seedUsers();
        $this->seedUsers();

        $this->assertDatabaseCount('users', 3);
    }

    /**
     * Everything except the password is safe to refresh, which is what makes
     * re-seeding useful in the first place.
     */
    public function test_re_seeding_restores_the_name_role_and_active_flag(): void
    {
        config(['app.seed_password' => 'correct-horse-battery']);

        $this->seedUsers();

        $admin = User::query()->where('email', 'admin@example.com')->sole();

        $admin->forceFill([
            'name' => 'Renamed By Accident',
            'role' => UserRole::Staff,
            'is_active' => false,
        ])->save();

        $this->seedUsers();
        $admin->refresh();

        $this->assertSame('Soriya Admin', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_the_seeded_admin_can_actually_sign_in(): void
    {
        config(['app.seed_password' => 'correct-horse-battery']);

        $this->seedUsers();

        $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'correct-horse-battery',
        ])->assertRedirect();

        // The point of the test: a seeded install is usable, not locked out.
        $this->assertAuthenticated();
    }
}
