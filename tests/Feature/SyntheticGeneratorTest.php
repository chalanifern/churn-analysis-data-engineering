<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Seeding\SyntheticDatasetGenerator;
use DateTimeInterface;
use Tests\TestCase;

/**
 * Invariants of the synthetic generator, asserted without touching MongoDB.
 *
 * The generator writes a second, historical line for a share of subscribers, so
 * `subscriptions` grows faster than `customers`. Both entry points therefore have
 * to know which subscription belongs to the customer they just generated, which is
 * exactly what a positional index gets wrong.
 */
final class SyntheticGeneratorTest extends TestCase
{
    private const CUSTOMERS = 200;

    private const BATCH_SIZE = 7;

    private function generator(): SyntheticDatasetGenerator
    {
        return new SyntheticDatasetGenerator(
            customerCount: self::CUSTOMERS,
            targetChurnRate: 0.32,
            usageMonths: 18,
        );
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  ...$payloads
     * @return array<string, list<array<string, mixed>>>
     */
    private static function merge(array ...$payloads): array
    {
        $merged = [];

        foreach ($payloads as $payload) {
            foreach ($payload as $collection => $documents) {
                foreach ($documents as $document) {
                    $merged[$collection][] = $document;
                }
            }
        }

        return $merged;
    }

    /**
     * A comparable digest of a generated payload.
     *
     * Timestamps are Carbon instances, and two objects holding the same instant are
     * not `===` to each other, so they are flattened to ISO strings first. The
     * payload is then hashed rather than compared document by document: a failing
     * comparison here would otherwise diff thousands of nested arrays.
     *
     * @param  array<string, list<array<string, mixed>>>  $payload
     */
    private static function digest(array $payload): string
    {
        $normalised = array_map(
            static fn (array $documents): array => array_map(
                static fn (array $document): array => array_map(
                    static fn (mixed $value): mixed => $value instanceof DateTimeInterface
                        ? $value->format('Y-m-d H:i:s')
                        : $value,
                    $document,
                ),
                $documents,
            ),
            $payload,
        );

        ksort($normalised);

        return md5((string) json_encode($normalised));
    }

    public function test_batched_output_matches_the_whole_dataset(): void
    {
        $expected = $this->generator()->generate();

        $batches = [];

        foreach ($this->generator()->generateInBatches(self::BATCH_SIZE) as $batch) {
            $batches[] = $batch;
        }

        $this->assertNotEmpty($batches, 'The batched generator must yield at least one batch.');
        $this->assertGreaterThan(
            1,
            count($batches),
            'A batch size smaller than the customer count must produce several batches.',
        );

        $merged = self::merge(...$batches);

        $this->assertSame(array_keys($expected), array_keys($merged));
        $this->assertSame(self::digest($expected), self::digest($merged), 'Batched generation diverged from generate().');
    }

    public function test_every_subscription_belongs_to_an_existing_customer(): void
    {
        $payload = $this->generator()->generate();

        $customerIds = array_column($payload['customers'], 'customer_id');

        $this->assertCount(self::CUSTOMERS, $customerIds);

        $orphans = array_values(array_filter(
            array_unique(array_column($payload['subscriptions'], 'customer_id')),
            static fn (string $customerId): bool => ! in_array($customerId, $customerIds, true),
        ));

        $this->assertSame([], $orphans);
    }

    public function test_a_secondary_line_inherits_the_primary_lines_status(): void
    {
        $payload = $this->generator()->generate();

        $primary = [];
        $secondaryLines = 0;

        foreach ($payload['subscriptions'] as $subscription) {
            $sequence = (string) $subscription['customer_id'];

            if (! str_contains((string) $subscription['subscription_id'], 'B')) {
                $primary[$sequence] = $subscription;

                continue;
            }

            $secondaryLines++;
            $line = $primary[$sequence] ?? null;

            $this->assertNotNull($line, "Secondary line {$subscription['subscription_id']} has no primary line before it.");
            $this->assertSame(
                $line['customer_id'],
                $subscription['customer_id'],
                "Secondary line {$subscription['subscription_id']} is attached to the wrong customer.",
            );
            $this->assertSame(
                $line['status'] === 'Churned' ? 'Churned' : 'Retained',
                $subscription['status'] === 'Churned' ? 'Churned' : 'Retained',
                "Secondary line {$subscription['subscription_id']} contradicts the status of its primary line.",
            );
            $this->assertLessThanOrEqual(
                max(2, (int) $line['tenure_months']),
                (int) $subscription['tenure_months'],
                'A historical line is bounded by the tenure of its own primary line.',
            );
        }

        $this->assertGreaterThan(0, $secondaryLines, 'The fixture must contain secondary lines for this test to mean anything.');
    }

    public function test_the_generator_is_deterministic(): void
    {
        $this->assertSame(
            self::digest($this->generator()->generate()),
            self::digest($this->generator()->generate()),
            'Two runs with the same seed must produce the same dataset.',
        );
    }
}
