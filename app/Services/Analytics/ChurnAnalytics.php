<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Churn analysis aggregations (segments, reasons, cohorts).
 */
final class ChurnAnalytics
{
    public function __construct(private readonly MongoAggregator $aggregator) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function bySegment(): array
    {
        return $this->aggregator->aggregate('customers', [
            ['$match' => ['customer_status' => 'Churned']],
            ['$group' => ['_id' => '$customer_segment', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byPlan(): array
    {
        return $this->aggregator->aggregate('subscriptions', [
            ['$match' => ['status' => 'Churned']],
            ['$lookup' => [
                'from' => 'plans',
                'localField' => 'plan_id',
                'foreignField' => 'plan_id',
                'as' => 'plan',
            ]],
            ['$unwind' => '$plan'],
            ['$group' => [
                '_id' => '$plan_id',
                'plan_name' => ['$first' => '$plan.plan_name'],
                'count' => ['$sum' => 1],
            ]],
            ['$sort' => ['count' => -1]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byDistrict(): array
    {
        return $this->aggregator->aggregate('customers', [
            ['$match' => ['customer_status' => 'Churned']],
            ['$group' => ['_id' => '$district', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reasons(): array
    {
        return $this->aggregator->groupCount('churn_records', 'churn_reason', ['churn_status' => 'Churned'], 20);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $total = $this->aggregator->count('customers');
        $churned = $this->aggregator->count('customers', ['customer_status' => 'Churned']);
        $rate = $total > 0 ? round(($churned / $total) * 100, 2) : 0.0;

        return [
            'total_customers' => $total,
            'churned_customers' => $churned,
            'churn_rate' => $rate,
        ];
    }
}
