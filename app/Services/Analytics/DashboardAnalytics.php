<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

/**
 * High-level KPIs and time-series data for the dashboard.
 *
 * All values returned are derived from the synthetic MongoDB dataset. Labels
 * explicitly mark outputs as prototype/synthetic as required by the assignment.
 *
 * Every expensive input - the reporting month and the scored risk snapshot (band
 * counts plus the highest scoring rows) - is resolved once per instance rather
 * than once per calling method. Both come from the six-join risk pipeline, and the
 * dashboard needs them for the KPI tiles, the risk distribution and the table
 * underneath, so re-running it per method tripled the dashboard's database time
 * for results that cannot differ.
 */
final class DashboardAnalytics
{
    /**
     * Memoised per request; see the class docblock.
     *
     * @var array{bands: array<string, int>, average_score: float, rows: list<array<string, mixed>>}|null
     */
    private ?array $riskSnapshot = null;

    /**
     * How many rows the memoised risk snapshot holds, so a wider request replaces
     * it rather than returning a short table.
     */
    private int $riskSnapshotLimit = 0;

    private ?CarbonImmutable $anchorMonth = null;

    /**
     * Rows in the dashboard's "highest risk" table. Shared with the band counts so
     * both come from one pass over the scored population.
     */
    private const TOP_RISK_ROWS = 10;

    public function __construct(
        private readonly MongoAggregator $aggregator,
        private readonly ChurnRiskService $riskService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $customers = $this->aggregator->count('customers');
        $active = $this->aggregator->count('customers', ['customer_status' => 'Active']);
        $churned = $this->aggregator->count('customers', ['customer_status' => 'Churned']);
        $churnRate = $customers > 0 ? round(($churned / $customers) * 100, 2) : 0.0;

        $mrr = $this->activeMonthlyRecurringRevenue();

        $revLost = $this->aggregator->aggregateFirst('subscriptions', [
            ['$match' => ['status' => 'Churned']],
            ['$group' => ['_id' => null, 'lost' => ['$sum' => '$monthly_charge']]],
        ])['lost'] ?? 0.0;

        $complaints = $this->aggregator->count('complaints');
        $unresolved = $this->aggregator->count('complaints', ['status' => ['$in' => ['Open', 'In Progress']]]);

        $risk = $this->riskBands();

        return [
            'customers_total' => $customers,
            'customers_active' => $active,
            'customers_churned' => $churned,
            'churn_rate' => $churnRate,
            'mrr' => $mrr,
            'revenue_lost_churned' => round((float) $revLost, 2),
            'complaints_total' => $complaints,
            'complaints_unresolved' => $unresolved,
            'risk_high' => (int) ($risk['High'] ?? 0),
            'risk_medium' => (int) ($risk['Medium'] ?? 0),
            'risk_low' => (int) ($risk['Low'] ?? 0),
            'latest_month' => $this->anchorMonth()->format('Y-m'),
        ];
    }

    /**
     * Month-by-month cohort retention for the last $months of the window.
     *
     * `total` is the customer base registered by the end of the month, `churned`
     * is how many of them left in that month, and `retained` is everyone still on
     * the base at that point - so `rate` is the monthly churn rate of that cohort
     * rather than the churn share of the month's own exits.
     *
     * Retained customers have a null `churn_date`, so they cannot be counted by
     * matching on `churn_date`. They are derived instead: base (cumulative
     * registrations) minus everyone lost up to and including that month.
     *
     * Both series are grouped server side into one row per month, so this costs
     * two cheap aggregations regardless of how many customers exist.
     *
     * @return list<array{month: string, churned: int, retained: int, total: int, rate: float}>
     */
    public function churnTrend(int $months = 12): array
    {
        $months = max(1, $months);

        $first = $this->anchorMonth()->subMonths($months - 1)->startOfMonth();
        $last = $this->anchorMonth()->endOfMonth();
        $firstKey = $first->format('Y-m');

        $registrations = $this->monthlyCounts('customers', 'registration_date', $last);
        $losses = $this->monthlyCounts('churn_records', 'churn_date', $last, ['churn_status' => 'Churned']);

        // Anything that happened before the window still counts towards the base.
        $base = 0;
        $lost = 0;

        foreach ($registrations as $month => $count) {
            $base += (string) $month < $firstKey ? $count : 0;
        }

        foreach ($losses as $month => $count) {
            $lost += (string) $month < $firstKey ? $count : 0;
        }

        $out = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $month = $first->addMonths($offset);
            $key = $month->format('Y-m');

            $base += $registrations[$key] ?? 0;
            $churnedThisMonth = $losses[$key] ?? 0;
            $lost += $churnedThisMonth;

            $out[] = [
                'month' => $key,
                'churned' => $churnedThisMonth,
                'retained' => max(0, $base - $lost),
                'total' => $base,
                'rate' => $base > 0 ? round(($churnedThisMonth / $base) * 100, 2) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function churnByReason(): array
    {
        return $this->aggregator->groupCount('churn_records', 'churn_reason', ['churn_status' => 'Churned'], 15);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function churnByDistrict(): array
    {
        return $this->aggregator->aggregate('customers', [
            ['$match' => ['customer_status' => 'Churned']],
            ['$group' => ['_id' => '$district', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]],
            ['$limit' => 15],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function riskDistribution(): array
    {
        $b = $this->riskBands();
        $total = array_sum($b);

        return array_map(function (string $k, int $v) use ($total) {
            return [
                'level' => $k,
                'count' => $v,
                'percent' => $total > 0 ? round(($v / $total) * 100, 2) : 0.0,
                'color' => match ($k) {
                    'High' => '#dc3545',
                    'Medium' => '#ffc107',
                    'Low' => '#28a745',
                    default => '#6c757d',
                },
            ];
        }, array_keys($b), array_values($b));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topRiskCustomers(int $limit = self::TOP_RISK_ROWS): array
    {
        return $this->riskSnapshot($limit)['rows'];
    }

    // -----------------------------------------------------------------------
    // Shared query inputs
    // -----------------------------------------------------------------------

    /**
     * The latest reported month, resolved once per request. The dashboard and the
     * view composer both need it, and it costs two aggregations to resolve.
     */
    private function anchorMonth(): CarbonImmutable
    {
        return $this->anchorMonth ??= PaymentDates::latestMonth($this->aggregator);
    }

    /**
     * Risk band counts and the highest scoring customers, resolved once per
     * request. This is the most expensive query on the dashboard (six joins plus
     * the scoring rules) and three of the four consumers need the same result.
     *
     * @return array{bands: array<string, int>, average_score: float, rows: list<array<string, mixed>>}
     */
    private function riskSnapshot(int $limit = self::TOP_RISK_ROWS): array
    {
        if ($this->riskSnapshot === null || $limit > $this->riskSnapshotLimit) {
            $this->riskSnapshot = $this->riskService->bandCountsWithTopRisk($limit);
            $this->riskSnapshotLimit = $limit;
        }

        return $this->riskSnapshot;
    }

    /**
     * Retained customers per risk band.
     *
     * @return array<string, int>
     */
    private function riskBands(): array
    {
        return $this->riskSnapshot()['bands'];
    }

    private function activeMonthlyRecurringRevenue(): float
    {
        $mrr = $this->aggregator->aggregateFirst('subscriptions', [
            ['$match' => ['status' => 'Active']],
            ['$group' => ['_id' => null, 'mrr' => ['$sum' => '$monthly_charge']]],
        ])['mrr'] ?? 0.0;

        return round((float) $mrr, 2);
    }

    /**
     * Document counts bucketed into "YYYY-MM" by a stored ISO date field.
     *
     * Dates are stored as `YYYY-MM-DD` strings, so both the range filter and the
     * month prefix sort and compare correctly without a `$toDate` conversion.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, int> month => document count
     */
    private function monthlyCounts(string $collection, string $dateField, CarbonImmutable $until, array $match = []): array
    {
        $rows = $this->aggregator->aggregate($collection, [
            ['$match' => $match + [$dateField => ['$ne' => null, '$lte' => $until->format('Y-m-d')]]],
            ['$group' => ['_id' => ['$substr' => ['$'.$dateField, 0, 7]], 'count' => ['$sum' => 1]]],
        ]);

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['_id']] = (int) $row['count'];
        }

        return $counts;
    }
}
