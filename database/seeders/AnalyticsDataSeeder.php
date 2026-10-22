<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChurnRecord;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Usage;
use App\Support\Seeding\SyntheticDatasetGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Generates the synthetic customer base and all related collections.
 *
 * The generator is deterministic, so re-running this seeder reproduces exactly
 * the same figures - which keeps the numbers in the report and any screenshots
 * consistent between runs.
 *
 * Volume is controlled by config('churn-analytics.seeding') so tests can seed a
 * much smaller dataset:
 *
 *   CHURN_SEED_CUSTOMERS=200 php artisan db:seed --class=AnalyticsDataSeeder
 */
final class AnalyticsDataSeeder extends Seeder
{
    public function run(): void
    {
        $customerCount = (int) config('churn-analytics.seeding.customer_count');
        $usageMonths = (int) config('churn-analytics.seeding.usage_months');
        $churnRate = (float) config('churn-analytics.seeding.churn_rate');
        $chunkSize = (int) config('churn-analytics.seeding.chunk_size');

        $this->command?->info(sprintf(
            '  Generating %s customers over %d months (target churn rate %.1f%%)...',
            number_format($customerCount),
            $usageMonths,
            $churnRate * 100,
        ));

        $generator = new SyntheticDatasetGenerator(
            customerCount: $customerCount,
            targetChurnRate: $churnRate,
            usageMonths: $usageMonths,
        );

        // The dataset is written batch by batch rather than generated in full
        // first. At the default volume the whole dataset is roughly 358,000
        // documents, which does not comfortably fit in the CLI memory limit.
        $inserted = [
            'customers' => 0,
            'subscriptions' => 0,
            'usage' => 0,
            'payments' => 0,
            'complaints' => 0,
            'churn_records' => 0,
        ];

        $batchCustomers = max(10, min($customerCount, (int) round($chunkSize / 4)));

        foreach ($generator->generateInBatches($batchCustomers) as $batch) {
            // Order matters within a batch: plans and customers must exist before
            // the collections that reference them.
            foreach ($this->collections() as $collection => $model) {
                $documents = $batch[$collection];
                $inserted[$collection] += $this->insertInChunks($model, $collection, $documents, $chunkSize);
            }

            unset($batch);
            gc_collect_cycles();
        }

        foreach ($inserted as $collection => $count) {
            $this->command?->info(sprintf('  %-18s %s documents inserted', $collection, number_format($count)));
        }

        $churned = ChurnRecord::raw(fn ($collection) => $collection->countDocuments(['churn_status' => 'Churned']));
        $total = $customerCount > 0 ? $churned / $customerCount * 100 : 0.0;

        $this->command?->info(sprintf(
            '  Churned %s of %s customers (%.1f%%).',
            number_format($churned),
            number_format($customerCount),
            $total,
        ));
    }

    /**
     * Collection name to model class, in dependency order.
     *
     * @return array<string, class-string<Model>>
     */
    private function collections(): array
    {
        return [
            'customers' => Customer::class,
            'subscriptions' => Subscription::class,
            'usage' => Usage::class,
            'payments' => Payment::class,
            'complaints' => Complaint::class,
            'churn_records' => ChurnRecord::class,
        ];
    }

    /**
     * Bulk insert in chunks. A single insertMany of ~100k documents is slow and
     * memory hungry on the Windows CLI, so the documents are written in batches.
     *
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $documents
     * @return int Number of documents written.
     */
    private function insertInChunks(string $model, string $collection, array $documents, int $chunkSize): int
    {
        if ($documents === []) {
            return 0;
        }

        foreach (array_chunk($documents, max(1, $chunkSize)) as $chunk) {
            $model::raw(fn ($collection) => $collection->insertMany($chunk));
        }

        return count($documents);
    }

    /**
     * Remove every document from the analytical collections. Used by the
     * `mongodb:fresh` console command so the demo can be rebuilt from scratch
     * without dropping the indexes.
     */
    public static function truncate(): void
    {
        $connection = DB::connection('mongodb');
        $database = $connection->getDatabase();

        foreach (['usage', 'payments', 'complaints', 'subscriptions', 'churn_records', 'customers', 'plans'] as $collection) {
            $database->selectCollection($collection)->deleteMany([]);
        }
    }
}
