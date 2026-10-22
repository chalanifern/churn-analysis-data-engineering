<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

/**
 * Isolates the "most recent date in the payments collection" query so the risk
 * service does not need to know about raw collection names or the anchor choice.
 */
final class PaymentDates
{
    /**
     * The latest payment date in the dataset, which acts as the reporting anchor
     * for "days since last recharge". Deriving it from the data keeps the risk
     * figures reproducible regardless of the real system date.
     */
    public static function latestPaymentDate(MongoAggregator $aggregator): ?CarbonImmutable
    {
        $document = $aggregator->aggregateFirst('payments', [
            ['$sort' => ['date' => -1]],
            ['$limit' => 1],
            ['$project' => ['_id' => 0, 'date' => 1]],
        ]);

        $date = $document['date'] ?? null;

        return is_string($date) ? CarbonImmutable::parse($date) : null;
    }

    /**
     * The most recent "YYYY-MM" reporting month present in the usage collection.
     */
    public static function latestUsageMonth(MongoAggregator $aggregator): ?string
    {
        $document = $aggregator->aggregateFirst('usage', [
            ['$sort' => ['month' => -1]],
            ['$limit' => 1],
            ['$project' => ['_id' => 0, 'month' => 1]],
        ]);

        $month = $document['month'] ?? null;

        return is_string($month) ? $month : null;
    }

    /**
     * Latest reporting month as a first-of-month date.
     *
     * Falls back to the payment anchor, and finally to the start of the current
     * month, so callers always receive a usable date on an empty dataset.
     */
    public static function latestMonth(MongoAggregator $aggregator): CarbonImmutable
    {
        $month = self::latestUsageMonth($aggregator) ?? self::latestPaymentDate($aggregator)?->format('Y-m');

        return $month === null
            ? CarbonImmutable::now()->startOfMonth()
            : CarbonImmutable::parse($month.'-01')->startOfMonth();
    }
}
