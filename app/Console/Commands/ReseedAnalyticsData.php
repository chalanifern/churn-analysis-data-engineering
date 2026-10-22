<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\AnalyticsDataSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild the synthetic dataset in place.
 *
 * `deleteMany` is used rather than dropping the collections, so the indexes
 * created by the migrations survive and every pipeline keeps its indexes.
 */
final class ReseedAnalyticsData extends Command
{
    /**
     * The child collections are cleared first so the temporary state never
     * contains a usage or payment record whose customer no longer exists.
     *
     * @var list<string>
     */
    private const COLLECTIONS = [
        'usage',
        'payments',
        'complaints',
        'subscriptions',
        'churn_records',
        'customers',
        'plans',
    ];

    protected $signature = 'churn:reseed
                            {--customers= : Override the number of synthetic customers}
                            {--months= : Override the length of the monthly reporting window}';

    protected $description = 'Delete the synthetic analytics data and regenerate it (indexes are preserved)';

    public function handle(): int
    {
        if ($this->option('customers') !== null) {
            config(['churn-analytics.seeding.customer_count' => (int) $this->option('customers')]);
        }

        if ($this->option('months') !== null) {
            config(['churn-analytics.seeding.usage_months' => (int) $this->option('months')]);
        }

        $this->components->warn('Deleting existing synthetic data...');

        $database = DB::connection('mongodb')->getDatabase();

        foreach (self::COLLECTIONS as $collection) {
            $result = $database->selectCollection($collection)->deleteMany([]);
            $this->components->twoColumnDetail($collection, (string) $result->getDeletedCount().' removed');
        }

        $this->newLine();
        $this->components->info('Regenerating...');

        $this->call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => AnalyticsDataSeeder::class, '--force' => true]);

        $this->newLine();
        $this->components->info('Synthetic dataset rebuilt.');

        return self::SUCCESS;
    }
}
