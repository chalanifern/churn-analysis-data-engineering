<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the demo administrator account used to sign in to the dashboard.
 *
 * The credentials are defined here and in README.md - never inside a Blade
 * template. Passwords are hashed by the model's `hashed` cast.
 */
final class UserSeeder extends Seeder
{
    /** @var list<array{name: string, email: string, password: string, role: string}> */
    private const ACCOUNTS = [
        [
            'name' => 'Analytics Administrator',
            'email' => 'admin@sltmobitel.test',
            'password' => 'password',
            'role' => 'admin',
        ],
        [
            'name' => 'Decision Analyst',
            'email' => 'analyst@sltmobitel.test',
            'password' => 'password',
            'role' => 'analyst',
        ],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->command?->info(sprintf('  Users .............. %d accounts seeded', count(self::ACCOUNTS)));
    }
}
