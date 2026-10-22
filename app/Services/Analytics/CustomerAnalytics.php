<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Customer lookup, profile aggregation, and usage/payments history.
 */
final class CustomerAnalytics
{
    public function __construct(private readonly MongoAggregator $aggregator) {}

    /**
     * @return array<string, mixed>
     */
    public function findById(string $customerId): ?array
    {
        $cust = $this->aggregator->aggregateFirst('customers', [
            ['$match' => ['customer_id' => $customerId]],
        ]);

        if ($cust === []) {
            return null;
        }

        $sub = $this->aggregator->aggregateFirst('subscriptions', [
            ['$match' => ['customer_id' => $customerId]],
            ['$sort' => ['status' => 1, 'tenure_months' => -1]],
        ]);

        $churn = $this->aggregator->aggregateFirst('churn_records', [
            ['$match' => ['customer_id' => $customerId]],
        ]);

        $stats = $this->aggregator->aggregateFirst('usage', [
            ['$match' => ['customer_id' => $customerId]],
            ['$group' => [
                '_id' => null,
                'avg_data' => ['$avg' => '$data_usage_gb'],
                'avg_voice' => ['$avg' => '$voice_minutes'],
                'avg_sms' => ['$avg' => '$sms_count'],
                'months' => ['$sum' => 1],
            ]],
        ]);

        $compl = $this->aggregator->aggregateFirst('complaints', [
            ['$match' => ['customer_id' => $customerId]],
            ['$group' => [
                '_id' => null,
                'total' => ['$sum' => 1],
                'unresolved' => ['$sum' => ['$cond' => [['$in' => ['$status', ['Open', 'In Progress']]], 1, 0]]],
            ]],
        ]);

        $lastPay = $this->aggregator->aggregateFirst('payments', [
            ['$match' => ['customer_id' => $customerId]],
            ['$sort' => ['date' => -1]],
            ['$limit' => 1],
        ]);

        return [
            'customer' => $cust,
            'subscription' => $sub,
            'churn' => $churn,
            'usage_stats' => $stats,
            'complaint_stats' => $compl,
            'last_payment' => $lastPay,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usageHistory(string $customerId, int $limit = 18): array
    {
        return $this->aggregator->aggregate('usage', [
            ['$match' => ['customer_id' => $customerId]],
            ['$sort' => ['month' => -1]],
            ['$limit' => $limit],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentHistory(string $customerId, int $limit = 12): array
    {
        return $this->aggregator->aggregate('payments', [
            ['$match' => ['customer_id' => $customerId]],
            ['$sort' => ['date' => -1]],
            ['$limit' => $limit],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function complaints(string $customerId, int $limit = 20): array
    {
        return $this->aggregator->aggregate('complaints', [
            ['$match' => ['customer_id' => $customerId]],
            ['$sort' => ['complaint_date' => -1]],
            ['$limit' => $limit],
        ]);
    }
}
