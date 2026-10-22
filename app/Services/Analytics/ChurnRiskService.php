<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Support\ChurnVocabulary;
use Carbon\CarbonImmutable;
use stdClass;

/**
 * Rule-based churn risk scoring for the Risk & Retention page.
 *
 * ACADEMIC NOTE
 * -------------
 * The score is a *project-defined analytical heuristic* built for this Decision
 * Analytics assignment. It is NOT an official SLT-MOBITEL scoring model and the
 * weights are not calibrated against real subscriber data.
 *
 * The whole score is produced by a single MongoDB aggregation pipeline, so the
 * six factors, the total and the risk band are all derived by the database
 * rather than in PHP or JavaScript.
 *
 *   low usage             +20   latest reported month below the usage threshold
 *   multiple complaints   +20   two or more complaints on record
 *   long recharge gap     +20   prepaid and no top-up for at least the gap threshold
 *   short tenure          +15   fewer than the tenure threshold months
 *   high monthly cost     +15   monthly charge above the cost threshold
 *   service issue         +10   at least one complaint still Open or In Progress
 *
 * Maximum attainable score is 100. Bands: Low 0-29, Medium 30-59, High 60-100.
 */
final class ChurnRiskService
{
    /**
     * The six factor keys, in display order. Each maps to a `risk_<key>` field
     * produced by the pipeline and to a weight in config.
     *
     * @var list<string>
     */
    public const FACTORS = [
        'low_usage',
        'multiple_complaints',
        'long_recharge_gap',
        'short_tenure',
        'high_monthly_cost',
        'service_issue',
    ];

    public function __construct(private readonly MongoAggregator $aggregator) {}

    /**
     * Human readable factor definitions, read from config so the UI legend and
     * the aggregation pipeline can never disagree about the weights.
     *
     * @return array<string, array{key: string, label: string, weight: int, description: string}>
     */
    public function factorDefinitions(): array
    {
        $descriptions = [
            'low_usage' => 'Latest reported month of data usage is below the low-usage threshold.',
            'multiple_complaints' => 'Two or more complaints recorded against the customer.',
            'long_recharge_gap' => 'Prepaid subscriber with a long gap since the last recharge.',
            'short_tenure' => 'Subscription tenure is below the short-tenure threshold.',
            'high_monthly_cost' => 'Monthly charge is above the high-cost threshold.',
            'service_issue' => 'At least one complaint is still Open or In Progress.',
        ];

        /** @var array<string, array{key: string, label: string, weight: int, description: string}> $definitions */
        $definitions = [];

        foreach ((array) config('churn-analytics.risk.weights') as $key => $weight) {
            $definitions[(string) $key] = [
                'key' => (string) $key,
                'label' => ucwords(str_replace('_', ' ', (string) $key)),
                'weight' => (int) $weight,
                'description' => $descriptions[$key] ?? '',
            ];
        }

        return $definitions;
    }

    /**
     * Reporting anchor for "days since last recharge": the most recent payment
     * date present in the dataset.
     */
    public function reportingAnchor(): CarbonImmutable
    {
        return PaymentDates::latestPaymentDate($this->aggregator) ?? CarbonImmutable::create(2025, 7, 1);
    }

    /**
     * Paginated risk table for the Risk & Retention page.
     *
     * One pipeline performs the joins, the scoring and the pagination: `$facet`
     * runs the summary branch and the page branch in a single server round trip.
     *
     * @param  array{
     *     risk_level?: string|null,
     *     district?: string|null,
     *     customer_segment?: string|null,
     *     plan_id?: string|null,
     *     gender?: string|null,
     *     search?: string|null,
     *     sort?: string|null
     * }  $filters
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int,
     *     summary: array{high: int, medium: int, low: int, average_score: float}
     * }
     */
    public function paginatedRiskTable(array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $skip = ($page - 1) * $perPage;

        // The scored pipeline joins six collections per customer, so it is the
        // expensive part of this query. A single $facet computes the band summary,
        // the row count and the requested page from one pass over the scored
        // documents instead of re-running the joins for each result set.
        $pipeline = $this->scoredPipeline($filters);
        $pipeline[] = ['$facet' => [
            'summary' => [$this->bandSummaryStage()],
            'rows' => [
                ['$sort' => $this->resolveSort($filters['sort'] ?? null)],
                ['$skip' => $skip],
                ['$limit' => $perPage],
            ],
            'count' => [['$count' => 'total']],
        ]];

        $facet = $this->aggregator->aggregateFirst('customers', $pipeline);

        $summary = (array) ($facet['summary'][0] ?? []);
        $total = (int) (($facet['count'][0]['total'] ?? 0));

        return [
            'rows' => array_map(
                fn (array $row): array => $this->decorate($row),
                $facet['rows'] ?? [],
            ),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'summary' => [
                'high' => (int) ($summary['high'] ?? 0),
                'medium' => (int) ($summary['medium'] ?? 0),
                'low' => (int) ($summary['low'] ?? 0),
                'average_score' => round((float) ($summary['average_score'] ?? 0), 1),
            ],
        ];
    }

    /**
     * Highest scoring customers, used by the dashboard KPI and the recent
     * high-risk table.
     *
     * @return list<array<string, mixed>>
     */
    public function topRisk(int $limit = 10, ?string $district = null, ?string $segment = null, ?string $planId = null): array
    {
        $pipeline = $this->scoredPipeline(array_filter([
            'risk_level' => null,
            'district' => $district,
            'customer_segment' => $segment,
            'plan_id' => $planId,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== 'all'));

        $pipeline[] = ['$sort' => ['risk_score' => -1, 'customer_id' => 1]];
        $pipeline[] = ['$limit' => max(1, min(200, $limit))];

        return array_map(
            fn (array $row): array => $this->decorate($row),
            $this->aggregator->aggregate('customers', $pipeline),
        );
    }

    /**
     * Count of customers in a risk band, honouring the surrounding filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countByRiskLevel(array $filters, string $level): int
    {
        $filters['risk_level'] = $level;
        $pipeline = $this->scoredPipeline($filters);
        $pipeline[] = ['$count' => 'total'];

        return (int) ($this->aggregator->aggregateFirst('customers', $pipeline)['total'] ?? 0);
    }

    /**
     * Full scoring detail for a single customer, used by the profile page.
     *
     * @return array<string, mixed>|null
     */
    public function scoreForCustomer(string $customerId): ?array
    {
        $pipeline = $this->scoredPipeline([]);
        $pipeline[0] = ['$match' => ['customer_id' => $customerId]];
        $pipeline[] = ['$limit' => 1];

        $row = $this->aggregator->aggregateFirst('customers', $pipeline);

        return $row === [] ? null : $this->decorate($row);
    }

    /**
     * How many retained customers sit in each risk band, for the Risk & Retention
     * page summary cards.
     *
     * @return array<string, int>
     */
    public function bandCounts(): array
    {
        // Taken from the same pass as the top-risk rows so the counts and the rows
        // can never disagree.
        return $this->bandCountsWithTopRisk(1)['bands'];
    }

    /**
     * Band counts and the highest scoring customers from a single pass over the
     * scored population.
     *
     * The dashboard needs both: the KPI tiles and the risk distribution show the
     * counts, and the table underneath shows the rows. Running the six-join scoring
     * pipeline twice for them doubled the dashboard's database time, so `$facet`
     * derives both from one traversal instead.
     *
     * @return array{
     *     bands: array<string, int>,
     *     average_score: float,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function bandCountsWithTopRisk(int $limit = 10): array
    {
        $limit = max(1, min(200, $limit));

        $pipeline = $this->scoredPipeline([]);
        $pipeline[] = ['$facet' => [
            'summary' => [$this->bandSummaryStage()],
            'rows' => [
                ['$sort' => ['risk_score' => -1, 'customer_id' => 1]],
                ['$limit' => $limit],
            ],
        ]];

        $facet = $this->aggregator->aggregateFirst('customers', $pipeline);
        $summary = (array) ($facet['summary'][0] ?? []);

        return [
            'bands' => [
                'High' => (int) ($summary['high'] ?? 0),
                'Medium' => (int) ($summary['medium'] ?? 0),
                'Low' => (int) ($summary['low'] ?? 0),
            ],
            'average_score' => round((float) ($summary['average_score'] ?? 0), 1),
            'rows' => array_map(
                fn (array $row): array => $this->decorate($row),
                $facet['rows'] ?? [],
            ),
        ];
    }

    // -----------------------------------------------------------------------
    // Pipeline construction
    // -----------------------------------------------------------------------

    /**
     * `$group` stage that tallies the scored population per band.
     *
     * Shared by the risk table, the band counts and the dashboard so all three
     * report the same numbers from the same expression.
     *
     * @return array<string, mixed>
     */
    private function bandSummaryStage(): array
    {
        return ['$group' => [
            '_id' => null,
            'high' => ['$sum' => ['$cond' => [['$eq' => ['$risk_level', 'High']], 1, 0]]],
            'medium' => ['$sum' => ['$cond' => [['$eq' => ['$risk_level', 'Medium']], 1, 0]]],
            'low' => ['$sum' => ['$cond' => [['$eq' => ['$risk_level', 'Low']], 1, 0]]],
            'average_score' => ['$avg' => '$risk_score'],
        ]];
    }

    /**
     * Start from `customers`, restrict to the requested subset, join the five
     * supporting collections, apply the scoring rules and return one flat document
     * per customer.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function scoredPipeline(array $filters): array
    {
        /** @var array<string, mixed> $thresholds */
        $thresholds = (array) config('churn-analytics.risk.thresholds');
        /** @var array<string, int> $weights */
        $weights = (array) config('churn-analytics.risk.weights');
        $maxScore = (int) config('churn-analytics.risk.max_score', 100);

        /** @var array<string, array{min: int, max: int}> $bands */
        $bands = (array) config('churn-analytics.risk.bands');
        $anchor = $this->reportingAnchor();

        // Filters that can only be applied once the joins and the score exist.
        $postJoinMatch = $this->scoredMatch($filters);

        $pipeline = [
            // Retained subscribers only. A customer who has already churned is
            // counted in the churn analysis, not as forward-looking risk.
            ['$match' => $this->customerMatch($filters)],

            // The customer's current (longest running) subscription.
            $this->joinOnCustomerId('subscriptions', 'subscription', [
                ['$addFields' => ['is_current' => ['$cond' => [['$eq' => ['$status', 'Active']], 1, 0]]]],
                ['$sort' => ['is_current' => -1, 'tenure_months' => -1]],
                ['$limit' => 1],
            ]),

            // The plan behind that subscription.
            [
                '$lookup' => [
                    'from' => 'plans',
                    'let' => ['pid' => ['$arrayElemAt' => ['$subscription.plan_id', 0]]],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$plan_id', '$$pid']]]],
                        ['$limit' => 1],
                    ],
                    'as' => 'plan',
                ],
            ],

            // Most recent reported month of consumption.
            $this->joinOnCustomerId('usage', 'latest_usage', [
                ['$sort' => ['month' => -1]],
                ['$limit' => 1],
            ]),

            // Most recent payment / recharge.
            $this->joinOnCustomerId('payments', 'latest_payment', [
                ['$sort' => ['date' => -1]],
                ['$limit' => 1],
            ]),

            // Complaint statistics are aggregated inside the sub-pipeline so the
            // main document carries two numbers instead of the whole history.
            [
                '$lookup' => [
                    'from' => 'complaints',
                    'let' => ['cid' => '$customer_id'],
                    'pipeline' => [
                        ['$match' => ['$expr' => ['$eq' => ['$customer_id', '$$cid']]]],
                        ['$group' => [
                            '_id' => null,
                            'total' => ['$sum' => 1],
                            'unresolved' => ['$sum' => ['$cond' => [
                                ['$in' => ['$status', ['Open', 'In Progress']]],
                                1,
                                0,
                            ]]],
                            'average_resolution_days' => ['$avg' => '$resolution_days'],
                        ]],
                    ],
                    'as' => 'complaint_stats',
                ],
            ],

            // Churn outcome, used to show which retention action saved a customer.
            $this->joinOnCustomerId('churn_records', 'churn', [
                ['$limit' => 1],
            ]),

            // Flatten the joined arrays into plain scalar fields.
            ['$addFields' => [
                'connection_type' => [
                    '$ifNull' => [['$arrayElemAt' => ['$subscription.connection_type', 0]], 'Unknown'],
                ],
                'is_prepaid' => ['$eq' => [
                    ['$ifNull' => [['$arrayElemAt' => ['$subscription.connection_type', 0]], null]],
                    'Prepaid',
                ]],
                'tenure_months' => ['$ifNull' => [['$arrayElemAt' => ['$subscription.tenure_months', 0]], 0]],
                'monthly_charge' => ['$ifNull' => [['$arrayElemAt' => ['$subscription.monthly_charge', 0]], 0]],
                'plan_id' => ['$arrayElemAt' => ['$subscription.plan_id', 0]],
                'plan_name' => ['$ifNull' => [['$arrayElemAt' => ['$plan.plan_name', 0]], 'Unassigned']],
                'data_usage_gb' => ['$ifNull' => [['$arrayElemAt' => ['$latest_usage.data_usage_gb', 0]], 0]],
                'active_days' => ['$ifNull' => [['$arrayElemAt' => ['$latest_usage.active_days', 0]], 0]],
                'usage_month' => ['$arrayElemAt' => ['$latest_usage.month', 0]],
                'complaint_count' => ['$ifNull' => [['$arrayElemAt' => ['$complaint_stats.total', 0]], 0]],
                'open_complaint_count' => ['$ifNull' => [['$arrayElemAt' => ['$complaint_stats.unresolved', 0]], 0]],
                'average_resolution_days' => ['$round' => [
                    ['$ifNull' => [['$arrayElemAt' => ['$complaint_stats.average_resolution_days', 0]], 0]],
                    1,
                ]],
                'last_payment_date' => ['$arrayElemAt' => ['$latest_payment.date', 0]],
                'last_payment_amount' => ['$ifNull' => [['$arrayElemAt' => ['$latest_payment.amount', 0]], 0]],
                'retention_action' => ['$ifNull' => [['$arrayElemAt' => ['$churn.retention_action', 0]], null]],
            ]],

            // "Days since last recharge" against the dataset's latest transaction.
            // Postpaid customers have no recharges, so the value stays null and the
            // recharge-gap factor cannot trigger for them.
            ['$addFields' => [
                'days_since_last_recharge' => ['$cond' => [
                    ['$and' => [
                        ['$eq' => ['$is_prepaid', true]],
                        ['$ne' => ['$last_payment_date', null]],
                    ]],
                    MongoAggregator::daysSince('last_payment_date', $anchor),
                    null,
                ]],
            ]],

            // ---- The six scoring rules, evaluated server side ----------------
            ['$addFields' => [
                'risk_low_usage' => ['$cond' => [
                    ['$lt' => ['$data_usage_gb', (float) $thresholds['low_usage_gb']]],
                    $weights['low_usage'],
                    0,
                ]],
                'risk_multiple_complaints' => ['$cond' => [
                    ['$gte' => ['$complaint_count', (int) $thresholds['multiple_complaints_count']]],
                    $weights['multiple_complaints'],
                    0,
                ]],
                'risk_long_recharge_gap' => ['$cond' => [
                    ['$and' => [
                        ['$eq' => ['$is_prepaid', true]],
                        ['$gte' => [
                            ['$ifNull' => ['$days_since_last_recharge', 0]],
                            (int) $thresholds['long_recharge_gap_days'],
                        ]],
                    ]],
                    $weights['long_recharge_gap'],
                    0,
                ]],
                'risk_short_tenure' => ['$cond' => [
                    ['$lt' => ['$tenure_months', (int) $thresholds['short_tenure_months']]],
                    $weights['short_tenure'],
                    0,
                ]],
                'risk_high_monthly_cost' => ['$cond' => [
                    ['$gt' => ['$monthly_charge', (float) $thresholds['high_monthly_charge']]],
                    $weights['high_monthly_cost'],
                    0,
                ]],
                'risk_service_issue' => ['$cond' => [
                    ['$gt' => ['$open_complaint_count', 0]],
                    $weights['service_issue'],
                    0,
                ]],
            ]],

            ['$addFields' => [
                'risk_score' => ['$min' => [
                    $maxScore,
                    ['$add' => [
                        '$risk_low_usage',
                        '$risk_multiple_complaints',
                        '$risk_long_recharge_gap',
                        '$risk_short_tenure',
                        '$risk_high_monthly_cost',
                        '$risk_service_issue',
                    ]],
                ]],
            ]],

            ['$addFields' => [
                'risk_level' => ['$switch' => [
                    'branches' => [
                        ['case' => ['$gte' => ['$risk_score', $bands['High']['min']]], 'then' => 'High'],
                        ['case' => ['$gte' => ['$risk_score', $bands['Medium']['min']]], 'then' => 'Medium'],
                    ],
                    'default' => 'Low',
                ]],
            ]],

            // Filters that can only be applied once the joins and the score exist.
            ['$match' => $postJoinMatch],

            ['$project' => [
                '_id' => 0,
                'customer_id' => 1,
                'name' => 1,
                'age' => 1,
                'gender' => 1,
                'district' => 1,
                'customer_segment' => 1,
                'customer_status' => 1,
                'connection_type' => 1,
                'plan_id' => 1,
                'plan_name' => 1,
                'tenure_months' => 1,
                'monthly_charge' => 1,
                'data_usage_gb' => 1,
                'active_days' => 1,
                'usage_month' => 1,
                'complaint_count' => 1,
                'open_complaint_count' => 1,
                'average_resolution_days' => 1,
                'last_payment_date' => 1,
                'last_payment_amount' => 1,
                'days_since_last_recharge' => 1,
                'retention_action' => 1,
                'risk_score' => 1,
                'risk_level' => 1,
                'risk_low_usage' => 1,
                'risk_multiple_complaints' => 1,
                'risk_long_recharge_gap' => 1,
                'risk_short_tenure' => 1,
                'risk_high_monthly_cost' => 1,
                'risk_service_issue' => 1,
            ]],
        ];

        return $pipeline;
    }

    // -----------------------------------------------------------------------
    // Filter construction
    // -----------------------------------------------------------------------

    /**
     * Filters that can be applied to `customers` before any join runs. Untrusted
     * input is only ever used as a literal value or a quoted regex - never as a
     * raw operator document.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function customerMatch(array $filters): array
    {
        $match = ['customer_status' => 'Active'];

        $district = $this->filterValue($filters, 'district');

        if ($district !== null) {
            $match['district'] = $district;
        }

        $segment = $this->filterValue($filters, 'customer_segment');

        if ($segment !== null) {
            $match['customer_segment'] = $segment;
        }

        $gender = $this->filterValue($filters, 'gender');

        if ($gender !== null) {
            $match['gender'] = $gender;
        }

        $search = $this->filterValue($filters, 'search');

        if ($search !== null) {
            $quoted = preg_quote($search, '/');
            $match['$or'] = [
                ['customer_id' => ['$regex' => $quoted, '$options' => 'i']],
                ['name' => ['$regex' => $quoted, '$options' => 'i']],
            ];
        }

        return $match;
    }

    /**
     * Filters that depend on the joins or on the computed score, so they must run
     * after the `$addFields` stages.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|stdClass an empty stdClass when nothing is filtered
     */
    private function scoredMatch(array $filters): array|stdClass
    {
        $match = [];

        $level = $this->filterValue($filters, 'risk_level');

        if ($level !== null) {
            $match['risk_level'] = $level;
        }

        $planId = $this->filterValue($filters, 'plan_id');

        if ($planId !== null) {
            $match['plan_id'] = $planId;
        }

        $connectionType = $this->filterValue($filters, 'connection_type');

        if ($connectionType !== null) {
            $match['connection_type'] = $connectionType;
        }

        // An empty PHP array serialises as a BSON array, but `$match` needs a
        // document. Returning a stdClass keeps the stage valid when no filter is
        // active, so callers never have to conditionally build the pipeline.
        return $match === [] ? (object) [] : $match;
    }

    /**
     * Normalise a request filter: trim it, and treat "" / "all" as "no filter".
     *
     * @param  array<string, mixed>  $filters
     */
    private function filterValue(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return ($value === '' || $value === 'all') ? null : $value;
    }

    /**
     * Resolve the requested sort into a safe `$sort` document.
     *
     * @return array<string, int>
     */
    private function resolveSort(?string $sort): array
    {
        return match ($sort) {
            'score_asc' => ['risk_score' => 1, 'customer_id' => 1],
            'tenure_desc' => ['tenure_months' => -1, 'risk_score' => -1],
            'tenure_asc' => ['tenure_months' => 1, 'risk_score' => -1],
            'usage_asc' => ['data_usage_gb' => 1, 'risk_score' => -1],
            'complaints_desc' => ['complaint_count' => -1, 'risk_score' => -1],
            'charge_desc' => ['monthly_charge' => -1, 'risk_score' => -1],
            'name_asc' => ['name' => 1],
            default => ['risk_score' => -1, 'customer_id' => 1],
        };
    }

    /**
     * A correlated `$lookup` on the `customer_id` business key.
     *
     * @param  list<array<string, mixed>>  $pipeline
     * @return array<string, mixed>
     */
    private function joinOnCustomerId(string $from, string $as, array $pipeline = []): array
    {
        return [
            '$lookup' => [
                'from' => $from,
                'let' => ['cid' => '$customer_id'],
                'pipeline' => array_merge(
                    [
                        ['$match' => ['$expr' => ['$eq' => ['$customer_id', '$$cid']]]],
                    ],
                    $pipeline,
                ),
                'as' => $as,
            ],
        ];
    }

    /**
     * Post-process a pipeline row: derive the triggered factors and round the
     * floating point values the UI prints.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function decorate(array $row): array
    {
        $triggered = [];

        foreach (self::FACTORS as $factor) {
            if ((int) ($row['risk_'.$factor] ?? 0) > 0) {
                $triggered[] = $factor;
            }
        }

        $row['risk_score'] = (int) ($row['risk_score'] ?? 0);
        $row['risk_level'] = (string) ($row['risk_level'] ?? ChurnVocabulary::riskLevelForScore($row['risk_score']));
        $row['tenure_months'] = (int) ($row['tenure_months'] ?? 0);
        $row['complaint_count'] = (int) ($row['complaint_count'] ?? 0);
        $row['open_complaint_count'] = (int) ($row['open_complaint_count'] ?? 0);
        $row['active_days'] = (int) ($row['active_days'] ?? 0);
        $row['monthly_charge'] = round((float) ($row['monthly_charge'] ?? 0), 2);
        $row['data_usage_gb'] = round((float) ($row['data_usage_gb'] ?? 0), 2);
        $row['average_resolution_days'] = round((float) ($row['average_resolution_days'] ?? 0), 1);
        $row['days_since_last_recharge'] = ($row['days_since_last_recharge'] ?? null) === null
            ? null
            : (int) $row['days_since_last_recharge'];
        $row['triggered_factors'] = $triggered;
        $row['factor_labels'] = array_map(
            static fn (string $factor): string => ucwords(str_replace('_', ' ', $factor)),
            $triggered,
        );

        return $row;
    }
}
