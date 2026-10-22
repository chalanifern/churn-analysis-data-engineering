<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use Carbon\CarbonImmutable;
use Generator;

/**
 * Builds a coherent synthetic Mobitel subscriber base for the prototype.
 *
 * The generator is deliberately *story driven* rather than uniformly random: each
 * customer first receives a latent churn outcome, and every downstream collection
 * is then derived from that outcome. Churned customers show declining usage, wider
 * recharge gaps, more complaints and shorter tenure, while retained customers look
 * healthy. Without this coupling the dashboards would display random noise and the
 * risk scoring model would appear to make no sense, which would undermine the
 * analytical half of the assignment.
 *
 * NO REAL CUSTOMER DATA IS USED. Names are obvious placeholders, districts are
 * public administrative geography, and every numeric attribute is generated.
 *
 * The reporting window ends at a fixed anchor month so the monthly trend charts
 * always end on a complete, deterministic month.
 */
final class SyntheticDatasetGenerator
{
    /** @var array<string, float> */
    private const GENDER_WEIGHTS = ['Male' => 50.0, 'Female' => 48.0, 'Other' => 2.0];

    /** @var array<string, float> */
    private const SEGMENT_WEIGHTS = ['Individual' => 74.0, 'SME' => 18.0, 'Corporate' => 8.0];

    /**
     * Churn reasons, weighted so "High Cost" and "Competitor" dominate - matching
     * the affordability and trade-incentive pressures described in the business
     * problem that motivated the project.
     *
     * @var array<string, float>
     */
    private const CHURN_REASON_WEIGHTS = [
        'High Cost' => 26.0,
        'Competitor' => 22.0,
        'Poor Network' => 16.0,
        'Low Usage' => 14.0,
        'Poor Customer Service' => 10.0,
        'SIM Issue' => 7.0,
        'Economic Pressure' => 5.0,
    ];

    /** @var array<string, float> */
    private const RETENTION_ACTION_WEIGHTS = [
        'Retained with offer' => 34.0,
        'Loyalty discount' => 26.0,
        'Win-back campaign' => 22.0,
        'Plan upgrade' => 12.0,
        'No action taken' => 6.0,
    ];

    private DeterministicRandom $random;

    /**
     * Share of subscribers who also hold a second, historical line.
     */
    private const SECONDARY_PLAN_CHANCE = 0.08;

    /** @var array<string, array<string, mixed>> */
    private array $plans;

    private CarbonImmutable $latestMonth;

    private CarbonImmutable $earliestMonth;

    /**
     * @param  int  $customerCount  number of synthetic customers to produce
     * @param  float  $targetChurnRate  probability that a given customer churns
     * @param  int  $usageMonths  length of the monthly reporting window
     * @param  int  $seed  fixed seed keeps `db:seed` reproducible for the demo
     */
    public function __construct(
        private readonly int $customerCount,
        private readonly float $targetChurnRate,
        private readonly int $usageMonths = 18,
        int $seed = 20240101,
    ) {
        $this->random = new DeterministicRandom($seed);
        $this->plans = PlanCatalogue::byId();

        $this->latestMonth = CarbonImmutable::create(2025, 6, 1);
        $this->earliestMonth = $this->latestMonth->subMonths($this->usageMonths - 1);
    }

    /**
     * "YYYY-MM" list of reporting months, oldest first.
     *
     * @return list<string>
     */
    public function months(): array
    {
        $months = [];

        for ($cursor = $this->earliestMonth; $cursor->lessThanOrEqualTo($this->latestMonth); $cursor = $cursor->addMonth()) {
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }

    /**
     * The last day of the reporting window. The risk model treats this as "today",
     * so the dashboard is not affected by the real system date.
     */
    public function reportingAnchor(): CarbonImmutable
    {
        return $this->latestMonth->endOfMonth();
    }

    /**
     * Produce the whole dataset, grouped by collection and ready for bulk insert.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function generate(): array
    {
        $customers = [];
        $subscriptions = [];
        $usage = [];
        $payments = [];
        $complaints = [];
        $churnRecords = [];

        for ($index = 1; $index <= $this->customerCount; $index++) {
            $primary = $this->generateCustomer(
                index: $index,
                customers: $customers,
                subscriptions: $subscriptions,
                usage: $usage,
                payments: $payments,
                complaints: $complaints,
                churnRecords: $churnRecords,
            );

            if ($this->random->chance(self::SECONDARY_PLAN_CHANCE)) {
                $subscriptions[] = $this->generateSecondarySubscription($primary['customer'], $primary['subscription']);
            }
        }

        return [
            'customers' => $customers,
            'subscriptions' => $subscriptions,
            'usage' => $usage,
            'payments' => $payments,
            'complaints' => $complaints,
            'churn_records' => $churnRecords,
        ];
    }

    /**
     * Generate the dataset in batches of customers, yielding one payload per
     * batch in the same shape as generate().
     *
     * generate() materialises the entire dataset in memory, which for the full
     * 7,500-customer volume is several hundred thousand documents and can exceed
     * the PHP memory limit. Callers that persist to MongoDB should consume this
     * generator and insert each batch, letting the previous one be collected. The
     * output is identical to generate() for any batch size.
     *
     * @return Generator<int, array<string, list<array<string, mixed>>>>
     */
    public function generateInBatches(int $batchSize = 250): Generator
    {
        $batchSize = max(1, $batchSize);

        $batch = self::emptyPayload();
        $batchCustomers = 0;

        for ($index = 1; $index <= $this->customerCount; $index++) {
            $primary = $this->generateCustomer(
                index: $index,
                customers: $batch['customers'],
                subscriptions: $batch['subscriptions'],
                usage: $batch['usage'],
                payments: $batch['payments'],
                complaints: $batch['complaints'],
                churnRecords: $batch['churn_records'],
            );

            if ($this->random->chance(self::SECONDARY_PLAN_CHANCE)) {
                $batch['subscriptions'][] = $this->generateSecondarySubscription(
                    $primary['customer'],
                    $primary['subscription'],
                );
            }

            $batchCustomers++;

            if ($batchCustomers >= $batchSize) {
                yield $batch;
                $batch = self::emptyPayload();
                $batchCustomers = 0;
            }
        }

        if ($batchCustomers > 0) {
            yield $batch;
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function emptyPayload(): array
    {
        return [
            'customers' => [],
            'subscriptions' => [],
            'usage' => [],
            'payments' => [],
            'complaints' => [],
            'churn_records' => [],
        ];
    }

    /**
     * Generate every document that belongs to one customer.
     *
     * The customer and its primary subscription are returned rather than looked up
     * by offset afterwards: a second line can be appended between calls, so any
     * index the caller computed for itself would drift.
     *
     * @param  list<array<string, mixed>>  $customers
     * @param  list<array<string, mixed>>  $subscriptions
     * @param  list<array<string, mixed>>  $usage
     * @param  list<array<string, mixed>>  $payments
     * @param  list<array<string, mixed>>  $complaints
     * @param  list<array<string, mixed>>  $churnRecords
     * @return array{customer: array<string, mixed>, subscription: array<string, mixed>}
     */
    private function generateCustomer(
        int $index,
        array &$customers,
        array &$subscriptions,
        array &$usage,
        array &$payments,
        array &$complaints,
        array &$churnRecords,
    ): array {
        $sequence = str_pad((string) $index, 5, '0', STR_PAD_LEFT);
        $customerId = 'C'.$sequence;

        $name = sprintf('Synth Customer %s', $sequence);
        $age = $this->drawAge();
        $gender = (string) $this->random->weighted(self::GENDER_WEIGHTS);
        $district = (string) $this->random->weighted(PlanCatalogue::DISTRICT_WEIGHTS);
        $segment = (string) $this->random->weighted(self::SEGMENT_WEIGHTS);

        // ----- Latent churn outcome -----------------------------------------
        $churned = $this->random->chance($this->targetChurnRate);
        $churnDate = $churned ? $this->drawChurnDate() : null;
        $churnReason = $churned
            ? (string) $this->random->weighted(self::CHURN_REASON_WEIGHTS)
            : 'Unknown';

        // A share of retained subscribers are already showing disengagement
        // signals without having left yet. This is the population the Risk &
        // Retention page exists to surface, so without it the high-risk band would
        // always be empty and the model would appear not to work. It is also what
        // a real mobile operator observes: complaints, falling usage, a widening
        // recharge gap and a high bill in the months before cancellation.
        $atRisk = ! $churned && $this->random->chance(0.17);

        // How many whole months before the reference month the customer stopped
        // transacting. Produces a genuine gap in the payments collection rather
        // than just a stored "days since recharge" value.
        $disengagedMonthsBack = $atRisk ? $this->random->int(1, 3) : 0;

        // Tenure measured at the point the customer left, or at the end of the
        // reporting window for retained customers.
        $tenureMonths = $this->drawTenureMonths($churned);
        $anchorDate = $churnDate ?? $this->reportingAnchor();
        $activationMonth = $anchorDate->startOfMonth()->subMonths($tenureMonths);

        $plan = $this->drawPlan($segment, $activationMonth);
        $connectionType = $this->connectionTypeFor($plan);
        $monthlyCharge = $this->drawMonthlyCharge($plan, $segment, $tenureMonths);

        // How many months of history are actually observable. A customer cannot
        // have usage for months before they were activated, and the window is
        // only 18 months long.
        $observedMonths = (int) max(1, min($tenureMonths, $this->usageMonths));

        // The history window ENDS at the customer's reference month (their churn
        // month, or the end of the reporting window for retained customers) and
        // works backwards. Anchoring to the end rather than to the start of the
        // window is what makes an active customer's most recent transaction
        // recent, which the recharge-gap risk factor depends on.
        $seriesStart = $anchorDate->startOfMonth()->subMonths($observedMonths - 1);

        $customer = [
            'customer_id' => $customerId,
            'name' => $name,
            'age' => $age,
            'gender' => $gender,
            'district' => $district,
            'customer_segment' => $segment,
            'registration_date' => $activationMonth->addDays($this->random->int(0, 27))->format('Y-m-d'),
            'customer_status' => $this->drawCustomerStatus($churned),
            'phone' => '07'.str_pad((string) $this->random->int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => sprintf('synth.customer.%s@synthetic.invalid', $sequence),
        ];
        $this->stampTimestamps($customer);
        $customers[] = $customer;

        $subscription = [
            'subscription_id' => 'S'.$sequence,
            'customer_id' => $customerId,
            'plan_id' => $plan['plan_id'],
            'connection_type' => $connectionType,
            'activation_date' => $activationMonth->addDays($this->random->int(0, 27))->format('Y-m-d'),
            'tenure_months' => $tenureMonths,
            'monthly_charge' => $monthlyCharge,
            'status' => $churned ? 'Churned' : 'Active',
        ];
        $this->stampTimestamps($subscription);
        $subscriptions[] = $subscription;

        $churnRecord = [
            'customer_id' => $customerId,
            'churn_status' => $churned ? 'Churned' : 'Active',
            'churn_date' => $churnDate?->format('Y-m-d'),
            'churn_reason' => $churnReason,
            'retention_action' => $churned
                ? 'No action taken'
                : (string) $this->random->weighted(self::RETENTION_ACTION_WEIGHTS),
        ];
        $this->stampTimestamps($churnRecord);
        $churnRecords[] = $churnRecord;

        $this->buildUsageDocuments($sequence, $customerId, $plan, $observedMonths, $churnDate, $churned, $churnReason, $atRisk, $seriesStart, $usage);
        $this->buildPaymentDocuments($sequence, $customerId, $plan, $connectionType, $monthlyCharge, $observedMonths, $churnDate, $churned, $churnReason, $disengagedMonthsBack, $seriesStart, $payments);
        $this->buildComplaintDocuments($sequence, $customerId, $observedMonths, $churnDate, $churned, $atRisk, $churnReason, $complaints);

        return ['customer' => $customer, 'subscription' => $subscription];
    }

    // -----------------------------------------------------------------------
    // Monthly usage
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $target
     */
    private function buildUsageDocuments(
        string $sequence,
        string $customerId,
        array $plan,
        int $observedMonths,
        ?CarbonImmutable $churnDate,
        bool $churned,
        string $churnReason,
        bool $atRisk,
        CarbonImmutable $seriesStart,
        array &$target,
    ): void {
        $dataAllowance = (float) $plan['data_gb'];
        $voiceAllowance = (int) $plan['voice_minutes'];
        $smsAllowance = (int) $plan['sms_count'];

        // Baseline consumption as a share of the plan allowance. An at-risk
        // customer is using a fraction of what the plan was sold for.
        $dataRatio = $this->random->normal($atRisk ? 0.30 : 0.62, 0.18, 0.03, 1.30);
        $voiceRatio = $this->random->normal($atRisk ? 0.28 : 0.48, 0.16, 0.03, 1.25);
        $smsRatio = $this->random->normal($atRisk ? 0.24 : 0.40, 0.15, 0.02, 1.20);

        // How sharply usage decays in the run-up to churn.
        $declineStrength = $churned ? $this->random->float(0.55, 0.92) : 0.0;

        // Price-driven churners keep consuming right up to their last bill, so
        // their usage series is NOT modelled as declining.
        $declinesBeforeChurn = $churned && ! in_array($churnReason, ['High Cost', 'Competitor'], true);

        // An at-risk retained customer is already running at a reduced level, and
        // drops further in the last reported month so "low usage" is triggered.
        $atRiskTailDecay = $atRisk ? $this->random->float(0.45, 0.80) : 0.0;

        for ($offset = 0; $offset < $observedMonths; $offset++) {
            $month = $seriesStart->addMonths($offset);

            if ($churnDate !== null && $month->greaterThan($churnDate->startOfMonth())) {
                break;
            }

            $monthsToChurn = $churnDate === null
                ? PHP_INT_MAX
                : $churnDate->startOfMonth()->diffInMonths($month, false);

            $decay = 0.0;

            if ($declinesBeforeChurn && $monthsToChurn <= 6) {
                $progress = (6 - max(0, $monthsToChurn)) / 6;
                $decay = $declineStrength * $progress;
            }

            // At-risk retained customers taper off over their final two months.
            $tailDecay = $atRiskTailDecay * match (true) {
                $offset >= $observedMonths - 1 => 1.0,
                $offset >= $observedMonths - 2 => 0.55,
                default => 0.0,
            };

            $totalDecay = min(0.95, $decay + $tailDecay);

            // Mild seasonal swing so the trend lines are not perfectly flat.
            $seasonality = 1.0 + 0.09 * sin((float) $month->month * M_PI / 6);
            $noise = $this->random->float(0.91, 1.11);

            $dataGb = max(0.0, $dataAllowance * $dataRatio * $seasonality * $noise * (1 - $totalDecay));
            $voiceMinutes = max(0, (int) round($voiceAllowance * $voiceRatio * $noise * (1 - 0.8 * $totalDecay)));
            $smsCount = max(0, (int) round($smsAllowance * $smsRatio * $this->random->float(0.86, 1.14) * (1 - 0.6 * $totalDecay)));

            // Heavy users are on the network most days; a customer whose usage has
            // decayed before churn, or who is already disengaging, is rarely active.
            $activeDays = (int) round($this->random->int(7, 30) * (1 - 0.7 * $totalDecay));

            $document = [
                'usage_id' => 'U'.$sequence.$month->format('ym'),
                'customer_id' => $customerId,
                'month' => $month->format('Y-m'),
                'data_usage_gb' => round($dataGb, 2),
                'voice_minutes' => $voiceMinutes,
                'sms_count' => $smsCount,
                'active_days' => max(0, min(31, $activeDays)),
            ];

            $this->stampTimestamps($document, $month->addDays($this->random->int(0, 27)));
            $target[] = $document;
        }
    }

    // -----------------------------------------------------------------------
    // Payments and recharges
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $target
     */
    private function buildPaymentDocuments(
        string $sequence,
        string $customerId,
        array $plan,
        string $connectionType,
        float $monthlyCharge,
        int $observedMonths,
        ?CarbonImmutable $churnDate,
        bool $churned,
        string $churnReason,
        int $disengagedMonthsBack,
        CarbonImmutable $seriesStart,
        array &$target,
    ): void {
        $prepaid = $connectionType === 'Prepaid';

        // Prepaid subscribers top up several times a month; postpaid pay one bill.
        $rechargesPerMonth = $prepaid ? $this->random->int(2, 4) : 1;
        $rechargeSize = $prepaid ? max(200.0, $monthlyCharge * $this->random->float(0.30, 0.60)) : $monthlyCharge;

        /** @var array<string, float> $methodWeights */
        $methodWeights = $prepaid
            ? ['Mobile App' => 42.0, 'Retailer' => 28.0, 'Online' => 18.0, 'Bank' => 8.0, 'Other' => 4.0]
            : ['Bank' => 40.0, 'Online' => 24.0, 'Mobile App' => 20.0, 'Retailer' => 12.0, 'Other' => 4.0];

        $frequencyLabel = $this->rechargeFrequencyLabel($prepaid ? $rechargesPerMonth : 1);

        // Financial stress precedes price-related churn: a customer who leaves
        // over affordability usually stops or delays paying first.
        $inArrears = $churned
            && in_array($churnReason, ['High Cost', 'Economic Pressure'], true);

        $previousRechargeDate = null;
        $transactionCounter = 0;

        for ($offset = 0; $offset < $observedMonths; $offset++) {
            $month = $seriesStart->addMonths($offset);

            if ($churnDate !== null && $month->greaterThan($churnDate->startOfMonth())) {
                break;
            }

            // A disengaging customer simply stops transacting. Omitting the recent
            // months - rather than storing a large "days since recharge" number -
            // is what produces a genuine gap for the risk pipeline to measure.
            if ($disengagedMonthsBack > 0 && $offset > $observedMonths - 1 - $disengagedMonthsBack) {
                continue;
            }

            $monthsToChurn = $churnDate === null
                ? PHP_INT_MAX
                : $churnDate->startOfMonth()->diffInMonths($month, false);

            $arrearsThisMonth = $inArrears && $monthsToChurn <= 4 && $this->random->chance(0.55);

            for ($sequenceInMonth = 0; $sequenceInMonth < $rechargesPerMonth; $sequenceInMonth++) {
                $date = $month->addDays($this->random->int(0, 29));

                if ($churnDate !== null && $date->greaterThan($churnDate)) {
                    continue;
                }

                $amount = $prepaid
                    ? round($rechargeSize * $this->random->float(0.82, 1.28), 2)
                    : round($monthlyCharge * $this->random->float(0.98, 1.04), 2);

                // The gap since the previous recharge. On the first transaction it
                // is the gap since activation, which is also what the risk model
                // reads through `$dateDiff` against the reporting anchor.
                $gapDays = $previousRechargeDate === null
                    ? 30
                    : (int) $previousRechargeDate->startOfDay()->diffInDays($date, true);

                if ($arrearsThisMonth && $sequenceInMonth === 0) {
                    $gapDays = $this->random->int(46, 115);
                }

                $status = 'Paid';
                $daysOverdue = 0;

                if ($arrearsThisMonth && $sequenceInMonth === 0) {
                    $status = $this->random->chance(0.45) ? 'Overdue' : 'Failed';
                    $daysOverdue = $gapDays;
                } elseif ($this->random->chance(0.035)) {
                    $status = 'Pending';
                }

                $transactionCounter++;

                $document = [
                    'payment_id' => 'PY'.$sequence.$month->format('ym').str_pad((string) $transactionCounter, 2, '0', STR_PAD_LEFT),
                    'customer_id' => $customerId,
                    'date' => $date->format('Y-m-d'),
                    'amount' => $amount,
                    'payment_method' => (string) $this->random->weighted($methodWeights),
                    'payment_status' => $status,
                    'days_overdue' => $daysOverdue,
                    'payment_type' => $prepaid ? 'Recharge' : 'Bill',
                    'recharge_amount' => $prepaid ? $amount : null,
                    'recharge_frequency' => $prepaid ? $frequencyLabel : null,
                    'days_since_last_recharge' => $prepaid ? $gapDays : null,
                ];

                $this->stampTimestamps($document, $date);
                $target[] = $document;

                $previousRechargeDate = $date;
            }
        }
    }

    // -----------------------------------------------------------------------
    // Complaints
    // -----------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>>  $target
     */
    private function buildComplaintDocuments(
        string $sequence,
        string $customerId,
        int $observedMonths,
        ?CarbonImmutable $churnDate,
        bool $churned,
        bool $atRisk,
        string $churnReason,
        array &$target,
    ): void {
        // About a third of the healthy base never raises a complaint at all, which
        // keeps the "zero complaints" bucket meaningful in the risk model.
        if (! $atRisk && $this->random->chance(0.32)) {
            return;
        }

        $serviceReason = in_array($churnReason, ['Poor Network', 'Poor Customer Service', 'SIM Issue'], true);
        $baseRate = match (true) {
            $churned && $serviceReason => 0.62,
            $churned => 0.24,
            $atRisk => 0.34,
            default => 0.11,
        };

        $count = (int) round($observedMonths * $this->random->float($baseRate * 0.5, $baseRate * 1.35));

        // Complaints are scattered across the same window the usage and payment
        // history covers, ending at the reporting (or churn) month.
        $windowStart = $churnDate === null
            ? $this->latestMonth->subMonths($observedMonths - 1)
            : $churnDate->startOfMonth()->subMonths($observedMonths - 1);

        for ($i = 0; $i < $count; $i++) {
            // At-risk customers complain in a cluster towards the end of the
            // window; everyone else is spread evenly across it.
            $monthOffset = $atRisk
                ? $this->random->int(max(0, (int) floor($observedMonths * 0.5)), max(0, $observedMonths - 1))
                : $this->random->int(0, max(0, $observedMonths - 1));

            $month = $windowStart->addMonths($monthOffset);
            $date = $month->addDays($this->random->int(0, 27));

            if ($churnDate !== null && $date->greaterThan($churnDate)) {
                continue;
            }

            $category = $this->drawComplaintCategory($churned, $serviceReason, $churnReason);
            $severity = $this->drawComplaintSeverity($churned || $atRisk, $serviceReason);
            $status = $this->drawComplaintStatus($atRisk);

            $document = [
                'complaint_id' => 'CP'.$sequence.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'customer_id' => $customerId,
                'complaint_date' => $date->format('Y-m-d'),
                'category' => $category,
                'description' => $this->random->pick(PlanCatalogue::COMPLAINT_TEMPLATES[$category]),
                'severity' => $severity,
                'status' => $status,
                'resolution_days' => $status === 'Resolved'
                    ? $this->random->int(1, $severity === 'High' ? 14 : 30)
                    : 0,
            ];

            $this->stampTimestamps($document, $date);
            $target[] = $document;
        }
    }

    // -----------------------------------------------------------------------
    // Secondary subscription
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $primary
     * @return array<string, mixed>
     */
    private function generateSecondarySubscription(array $customer, array $primary): array
    {
        $segment = (string) $customer['customer_segment'];
        $churned = (string) $primary['status'] === 'Churned';

        $plan = $this->drawPlan($segment, $this->earliestMonth);
        $tenure = $this->random->int(1, max(2, (int) $primary['tenure_months']));

        $document = [
            'subscription_id' => 'S'.substr((string) $customer['customer_id'], 1).'B'.$this->random->int(10, 99),
            'customer_id' => $customer['customer_id'],
            'plan_id' => $plan['plan_id'],
            'connection_type' => $this->connectionTypeFor($plan),
            'activation_date' => $this->earliestMonth->subMonths($tenure)->format('Y-m-d'),
            'tenure_months' => $tenure,
            'monthly_charge' => round(((float) $plan['monthly_price']) * $this->random->float(0.90, 1.05), 2),
            'status' => $churned ? 'Churned' : ($this->random->chance(0.25) ? 'Inactive' : 'Active'),
        ];

        $this->stampTimestamps($document);

        return $document;
    }

    // -----------------------------------------------------------------------
    // Draw helpers
    // -----------------------------------------------------------------------

    private function drawAge(): int
    {
        $roll = $this->random->float(0, 1);

        return match (true) {
            $roll < 0.30 => $this->random->int(18, 29),
            $roll < 0.62 => $this->random->int(30, 44),
            $roll < 0.88 => $this->random->int(45, 59),
            default => $this->random->int(60, 74),
        };
    }

    /**
     * Churn dates cluster towards the end of the reporting window and ramp up
     * slightly, so the monthly churn trend is a realistic curve rather than noise.
     */
    private function drawChurnDate(): CarbonImmutable
    {
        $monthsAvailable = max(1, $this->usageMonths - 2);
        $monthsBack = (int) floor($this->random->float(0, 1) ** 1.4 * $monthsAvailable);
        $month = $this->latestMonth->subMonths($monthsBack);

        return $month->setDate($month->year, $month->month, $this->random->int(1, $month->daysInMonth));
    }

    private function drawTenureMonths(bool $churned): int
    {
        $roll = $this->random->float(0, 1);

        if ($churned) {
            return match (true) {
                $roll < 0.34 => $this->random->int(0, 6),
                $roll < 0.60 => $this->random->int(7, 12),
                $roll < 0.82 => $this->random->int(13, 24),
                default => $this->random->int(25, 48),
            };
        }

        return match (true) {
            $roll < 0.18 => $this->random->int(1, 6),
            $roll < 0.36 => $this->random->int(7, 12),
            $roll < 0.60 => $this->random->int(13, 24),
            $roll < 0.82 => $this->random->int(25, 36),
            default => $this->random->int(37, 72),
        };
    }

    private function drawCustomerStatus(bool $churned): string
    {
        if ($churned) {
            return $this->random->chance(0.94) ? 'Churned' : 'Terminated';
        }

        return $this->random->chance(0.055) ? 'Suspended' : 'Active';
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function drawPlan(string $segment, CarbonImmutable $activationMonth): array
    {
        /** @var array<string, float> $weights */
        $weights = PlanCatalogue::weightsForSegment($segment);

        // The retired plan can only be held by long-standing subscribers.
        if ($activationMonth->year <= 2021) {
            $weights['P011'] = ($weights['P011'] ?? 0.0) + 14.0;
        }

        $planId = (string) $this->random->weighted($weights);

        return $this->plans[$planId] ?? $this->plans['P002'];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function drawMonthlyCharge(array $plan, string $segment, int $tenureMonths): float
    {
        $base = (float) $plan['monthly_price'];

        // Long-standing subscribers carry a legacy discount.
        $discount = match (true) {
            $tenureMonths >= 36 => 0.88,
            $tenureMonths >= 18 => 0.94,
            default => 1.0,
        };

        // Corporate and SME accounts negotiate volume pricing.
        $discount *= match ($segment) {
            'Corporate' => 0.92,
            'SME' => 0.96,
            default => 1.0,
        };

        return round($base * $discount * $this->random->float(0.98, 1.06), 2);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function connectionTypeFor(array $plan): string
    {
        return match ((string) $plan['plan_type']) {
            'Prepaid' => 'Prepaid',
            'Postpaid' => 'Postpaid',
            default => $this->random->chance(0.5) ? 'Prepaid' : 'Postpaid',
        };
    }

    private function rechargeFrequencyLabel(int $perMonth): string
    {
        return match (true) {
            $perMonth >= 4 => 'Weekly',
            $perMonth === 3 => 'Fortnightly',
            $perMonth === 2 => 'Monthly',
            default => 'Occasional',
        };
    }

    private function drawComplaintCategory(bool $churned, bool $serviceReason, string $churnReason): string
    {
        /** @var array<string, float> $weights */
        $weights = match ($churnReason) {
            'Poor Network' => ['Network' => 60.0, 'Data' => 22.0, 'Customer Service' => 10.0, 'SIM' => 5.0, 'Billing' => 3.0],
            'SIM Issue' => ['SIM' => 62.0, 'Network' => 18.0, 'Customer Service' => 10.0, 'Other' => 6.0, 'Billing' => 4.0],
            'Poor Customer Service' => ['Customer Service' => 58.0, 'Billing' => 16.0, 'Other' => 14.0, 'Network' => 8.0, 'Package' => 4.0],
            'High Cost' => ['Billing' => 44.0, 'Package' => 30.0, 'Customer Service' => 14.0, 'Other' => 7.0, 'Data' => 5.0],
            'Low Usage' => ['Data' => 40.0, 'Package' => 30.0, 'Billing' => 18.0, 'Other' => 7.0, 'Network' => 5.0],
            'Competitor' => ['Other' => 34.0, 'Package' => 28.0, 'Billing' => 20.0, 'Data' => 12.0, 'Network' => 6.0],
            'Economic Pressure' => ['Billing' => 52.0, 'Package' => 22.0, 'Other' => 14.0, 'Customer Service' => 8.0, 'Data' => 4.0],
            default => ['Network' => 22.0, 'Billing' => 20.0, 'SIM' => 12.0, 'Data' => 16.0, 'Customer Service' => 14.0, 'Package' => 10.0, 'Other' => 6.0],
        };

        // Price-driven churners still report network problems, just less often.
        if ($churned && ! $serviceReason) {
            $weights['Network'] = ($weights['Network'] ?? 0.0) + 12.0;
        }

        return (string) $this->random->weighted($weights);
    }

    private function drawComplaintSeverity(bool $churned, bool $serviceReason): string
    {
        /** @var array<string, float> $weights */
        $weights = match (true) {
            $churned && $serviceReason => ['High' => 42.0, 'Medium' => 40.0, 'Low' => 18.0],
            $churned => ['High' => 18.0, 'Medium' => 47.0, 'Low' => 35.0],
            default => ['High' => 10.0, 'Medium' => 38.0, 'Low' => 52.0],
        };

        return (string) $this->random->weighted($weights);
    }

    private function drawComplaintStatus(bool $atRisk = false): string
    {
        // Disengaging customers are less likely to have their complaints closed.
        return (string) $this->random->weighted($atRisk
            ? ['Resolved' => 48.0, 'In Progress' => 31.0, 'Open' => 21.0]
            : ['Resolved' => 68.0, 'In Progress' => 21.0, 'Open' => 11.0]);
    }

    /**
     * Attach `created_at` / `updated_at` to a document.
     *
     * @param  array<string, mixed>  $document
     */
    private function stampTimestamps(array &$document, ?CarbonImmutable $date = null): void
    {
        $resolved = $date ?? CarbonImmutable::parse((string) ($document['churn_date']
            ?? $document['complaint_date']
            ?? $document['date']
            ?? $document['activation_date']
            ?? $document['registration_date']
            ?? $this->latestMonth->format('Y-m-d')));

        $document['created_at'] = $resolved;
        $document['updated_at'] = $resolved;
    }
}
