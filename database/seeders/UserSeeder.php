<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Creates the starting accounts.
     *
     * Two rules matter here, and the previous version broke both.
     *
     * An existing account never has its password rewritten. This seeder used to
     * run updateOrCreate over the password field, which meant re-seeding to top
     * up demo data silently reset a real administrator's password back to a
     * value printed in the repository.
     *
     * A new account gets a generated password unless one is supplied, so a fresh
     * install is never sitting on a password that is public knowledge.
     */
    public function run(): void
    {
        $supplied = config('app.seed_password');
        $generated = ! is_string($supplied) || mb_strlen($supplied) < 8;
        $password = $generated ? Str::password(16) : $supplied;

        $users = [
            [
                'name' => 'Soriya Admin',
                'email' => 'admin@example.com',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Stock Manager',
                'email' => 'manager@example.com',
                'role' => UserRole::Manager,
            ],
            [
                'name' => 'Warehouse Staff',
                'email' => 'staff@example.com',
                'role' => UserRole::Staff,
            ],
        ];

        $created = [];

        foreach ($users as $user) {
            /** @var User|null $existing */
            $existing = User::query()->where('email', $user['email'])->first();

            if ($existing !== null) {
                // Name and role are safe to refresh. The password is not.
                $existing->forceFill([
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'is_active' => true,
                ])->save();

                continue;
            }

            User::query()->create([
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ]);

            $created[] = $user['email'];
        }

        $this->announce($created, $password, $generated);
    }

    /**
     * Print the sign-in details once, because a generated password is not
     * recoverable afterwards. Only ever shown for accounts just created.
     *
     * @param  array<int, string>  $created
     */
    protected function announce(array $created, string $password, bool $generated): void
    {
        // Null when the seeder is called from a test rather than the console.
        if ($this->command === null) {
            return;
        }

        if ($created === []) {
            $this->command->info('Users already existed. Passwords were left untouched.');

            return;
        }

        $this->command->info('Created accounts: '.implode(', ', $created));

        if ($generated) {
            $this->command->warn('Generated password (shown once, save it now): '.$password);
            $this->command->warn('Set SEED_PASSWORD in your .env to choose your own instead.');

            return;
        }

        $this->command->info('Password taken from SEED_PASSWORD.');
    }
}
