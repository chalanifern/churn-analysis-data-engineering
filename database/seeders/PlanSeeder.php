<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Seeding\PlanCatalogue;
use Illuminate\Database\Seeder;

/**
 * Seeds the synthetic mobile plan catalogue. Reference data only - no customers.
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $documents = array_map(
            static fn (array $plan): array => $plan + [
                'created_at' => $now,
                'updated_at' => $now,
            ],
            PlanCatalogue::PLANS,
        );

        Plan::raw(fn ($collection) => $collection->insertMany($documents));

        $this->command?->info(sprintf('  Plans .............. %d plans seeded', count($documents)));
    }
}
