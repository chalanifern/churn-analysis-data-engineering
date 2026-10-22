<?php

declare(strict_types=1);

namespace App\Support\Seeding;

/**
 * Deterministic pseudo-random helper for synthetic data generation.
 *
 * A fixed seed keeps `db:seed` reproducible, which matters for an academic demo:
 * the same figures appear every time the dataset is rebuilt, so screenshots and
 * the report stay consistent.
 */
final class DeterministicRandom
{
    private int $state;

    public function __construct(int $seed = 20240101)
    {
        $this->state = $seed & 0x7FFFFFFF;
    }

    /**
     * Linear congruential generator - sufficient for demo data and portable.
     */
    public function next(): int
    {
        // Numerical Recipes constants.
        $this->state = (int) ((1664525 * $this->state + 1013904223) & 0x7FFFFFFF);

        return $this->state;
    }

    /**
     * Uniform float in the half-open interval [$min, $max).
     */
    public function float(float $min, float $max): float
    {
        return $min + ($this->next() / 0x7FFFFFFF) * ($max - $min);
    }

    /**
     * Uniform integer in the inclusive range [$min, $max].
     */
    public function int(int $min, int $max): int
    {
        return $min + (int) floor($this->next() / 0x7FFFFFFF * ($max - $min + 1));
    }

    public function chance(float $probability): bool
    {
        return $this->float(0.0, 1.0) < $probability;
    }

    /**
     * @template T
     *
     * @param  list<T>  $values
     * @return T
     */
    public function pick(array $values): mixed
    {
        return $values[$this->int(0, count($values) - 1)];
    }

    /**
     * Pick a key from a `weight => probability` map, where the probabilities are
     * treated as relative weights.
     *
     * @param  array<string|int, float>  $weights
     */
    public function weighted(array $weights): string|int
    {
        $total = array_sum($weights);
        $roll = $this->float(0.0, (float) $total);
        $cumulative = 0.0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;

            if ($roll <= $cumulative) {
                return $key;
            }
        }

        return (string) array_key_last($weights);
    }

    /**
     * Value drawn from an approximately normal distribution via the central limit
     * theorem, clamped to [$min, $max]. Used for quantities such as monthly spend
     * and data usage that cluster around a mean with a long right tail.
     */
    public function normal(float $mean, float $stdDev, float $min, float $max): float
    {
        $sum = 0.0;

        for ($i = 0; $i < 6; $i++) {
            $sum += $this->float(0.0, 1.0);
        }

        $value = $mean + (($sum - 3.0) / 1.2247) * $stdDev;

        return max($min, min($max, $value));
    }
}
