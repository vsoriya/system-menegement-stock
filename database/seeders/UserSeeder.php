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
     *
     * Exception: set SEED_PASSWORD and SEED_RESET_PASSWORDS=true to intentionally
     * reset the seeded accounts (useful when the one-time generated password was
     * lost from deploy logs).
     */
    public function run(): void
    {
        $supplied = config('app.seed_password');
        $generated = ! is_string($supplied) || mb_strlen($supplied) < 8;
        $password = $generated ? Str::password(16) : $supplied;
        $resetExisting = (bool) config('app.seed_reset_passwords') && ! $generated;

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
        $reset = [];

        foreach ($users as $user) {
            /** @var User|null $existing */
            $existing = User::query()->where('email', $user['email'])->first();

            if ($existing !== null) {
                $payload = [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'is_active' => true,
                ];

                if ($resetExisting) {
                    $payload['password'] = Hash::make($password);
                    $reset[] = $user['email'];
                }

                $existing->forceFill($payload)->save();

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

        $this->announce($created, $reset, $password, $generated, $resetExisting);
    }

    /**
     * Print the sign-in details once, because a generated password is not
     * recoverable afterwards. Only ever shown for accounts just created.
     *
     * @param  array<int, string>  $created
     * @param  array<int, string>  $reset
     */
    protected function announce(array $created, array $reset, string $password, bool $generated, bool $resetExisting): void
    {
        // Null when the seeder is called from a test rather than the console.
        if ($this->command === null) {
            return;
        }

        if ($created === [] && $reset === []) {
            $this->command->info('Users already existed. Passwords were left untouched.');

            return;
        }

        if ($created !== []) {
            $this->command->info('Created accounts: '.implode(', ', $created));
        }

        if ($reset !== []) {
            $this->command->warn('Reset passwords for: '.implode(', ', $reset));
        }

        if ($generated) {
            $this->command->warn('Generated password (shown once, save it now): '.$password);
            $this->command->warn('Set SEED_PASSWORD in your .env to choose your own instead.');

            return;
        }

        if ($resetExisting) {
            $this->command->info('Password taken from SEED_PASSWORD (reset applied).');

            return;
        }

        $this->command->info('Password taken from SEED_PASSWORD.');
    }
}
