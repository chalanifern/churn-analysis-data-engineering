<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default seeder: the demo accounts, the synthetic plan catalogue and the full
 * synthetic customer base for the analytics prototype.
 *
 *   php artisan db:seed
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding the SLT-MOBITEL churn analytics prototype (synthetic data only)...');

        $this->call([
            UserSeeder::class,
            PlanSeeder::class,
            AnalyticsDataSeeder::class,
        ]);

        $this->command?->info('Done. Sign in with admin@sltmobitel.test / password');
    }
}
