<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical controlled vocabularies for the SLT-MOBITEL churn analytics prototype.
 *
 * Every value written to MongoDB is validated against one of these lists by the
 * form requests, so the aggregation pipelines can rely on a fixed domain. The
 * same lists feed `<select>` options in the CRUD forms, the analytics filter
 * panels and the chart legends, which keeps the UI and the data consistent.
 */
final class ChurnVocabulary
{
    /** @var list<string> */
    public const GENDERS = ['Male', 'Female', 'Other'];

    /** @var list<string> */
    public const CUSTOMER_SEGMENTS = ['Individual', 'Corporate', 'SME'];

    /** @var list<string> */
    public const CUSTOMER_STATUSES = ['Active', 'Suspended', 'Churned', 'Terminated'];

    /** @var list<string> */
    public const PLAN_TYPES = ['Prepaid', 'Postpaid', 'Hybrid'];

    /** @var list<string> */
    public const RECORD_STATUSES = ['Active', 'Discontinued'];

    /** @var list<string> */
    public const CONNECTION_TYPES = ['Prepaid', 'Postpaid'];

    /** @var list<string> */
    public const SUBSCRIPTION_STATUSES = ['Active', 'Inactive', 'Churned'];

    /** @var list<string> */
    public const PAYMENT_METHODS = ['Online', 'Mobile App', 'Bank', 'Retailer', 'Other'];

    /** @var list<string> */
    public const PAYMENT_STATUSES = ['Paid', 'Pending', 'Overdue', 'Failed'];

    /** @var list<string> */
    public const RECHARGE_FREQUENCIES = ['Weekly', 'Fortnightly', 'Monthly', 'Occasional'];

    /** @var list<string> */
    public const COMPLAINT_CATEGORIES = ['Network', 'Billing', 'SIM', 'Data', 'Customer Service', 'Package', 'Other'];

    /** @var list<string> */
    public const COMPLAINT_SEVERITIES = ['Low', 'Medium', 'High'];

    /** @var list<string> */
    public const COMPLAINT_STATUSES = ['Open', 'In Progress', 'Resolved'];

    /** @var list<string> */
    public const CHURN_STATUSES = ['Churned', 'Active'];

    /** @var list<string> */
    public const CHURN_REASONS = [
        'High Cost',
        'Poor Network',
        'Poor Customer Service',
        'Competitor',
        'Low Usage',
        'SIM Issue',
        'Economic Pressure',
        'Unknown',
    ];

    /** @var list<string> */
    public const RETENTION_ACTIONS = [
        'Retained with offer',
        'Win-back campaign',
        'Loyalty discount',
        'Plan upgrade',
        'No action taken',
    ];

    /** @var list<string> */
    public const RISK_LEVELS = ['Low', 'Medium', 'High'];

    /** @var list<string> */
    public const DISTRICTS = [
        'Colombo',
        'Gampaha',
        'Kalutara',
        'Kandy',
        'Matale',
        'Nuwara Eliya',
        'Galle',
        'Matara',
        'Hambantota',
        'Jaffna',
        'Kilinochchi',
        'Mannar',
        'Vavuniya',
        'Mullaitivu',
        'Batticaloa',
        'Ampara',
        'Trincomalee',
        'Kurunegala',
        'Puttalam',
        'Anuradhapura',
        'Polonnaruwa',
        'Badulla',
        'Monaragala',
        'Ratnapura',
        'Kegalle',
    ];

    /**
     * Age buckets used by the Customer Analytics page. `min`/`max` are compared
     * against the customer's integer `age`.
     *
     * @var array<string, array{min: int, max: int}>
     */
    public const AGE_GROUPS = [
        '18-24' => ['min' => 18, 'max' => 24],
        '25-34' => ['min' => 25, 'max' => 34],
        '35-44' => ['min' => 35, 'max' => 44],
        '45-54' => ['min' => 45, 'max' => 54],
        '55-64' => ['min' => 55, 'max' => 64],
        '65+' => ['min' => 65, 'max' => 200],
    ];

    /**
     * Tenure buckets used by the Churn Analysis page. The boundaries are
     * expressed in months and evaluated with `$switch` inside the aggregation
     * pipelines so the grouping happens server side in MongoDB.
     *
     * @var array<string, array{min: int, max: int}>
     */
    public const TENURE_GROUPS = [
        '0-6 months' => ['min' => 0, 'max' => 6],
        '7-12 months' => ['min' => 7, 'max' => 12],
        '13-24 months' => ['min' => 13, 'max' => 24],
        '25-36 months' => ['min' => 25, 'max' => 36],
        '36+ months' => ['min' => 37, 'max' => 12000],
    ];

    /**
     * Monotonic usage bands used to filter the Usage management screen.
     *
     * @var array<string, array{min: float, max: float}>
     */
    public const USAGE_LEVELS = [
        'Low' => ['min' => 0, 'max' => 5],
        'Moderate' => ['min' => 5.01, 'max' => 15],
        'High' => ['min' => 15.01, 'max' => 40],
        'Very High' => ['min' => 40.01, 'max' => 100000],
    ];

    /**
     * Risk band boundaries for the project-defined churn risk score (0-100).
     *
     * IMPORTANT: project-defined analytical heuristic only. This is NOT an
     * official SLT-MOBITEL scoring model.
     *
     * @var array<string, array{min: int, max: int}>
     */
    public const RISK_BANDS = [
        'Low' => ['min' => 0, 'max' => 29],
        'Medium' => ['min' => 30, 'max' => 59],
        'High' => ['min' => 60, 'max' => 100],
    ];

    /**
     * @return list<string>
     */
    public static function ageGroupLabels(): array
    {
        return array_keys(self::AGE_GROUPS);
    }

    /**
     * @return list<string>
     */
    public static function tenureGroupLabels(): array
    {
        return array_keys(self::TENURE_GROUPS);
    }

    /**
     * @return list<string>
     */
    public static function usageLevelLabels(): array
    {
        return array_keys(self::USAGE_LEVELS);
    }

    /**
     * Map a churn risk score to its risk band label.
     */
    public static function riskLevelForScore(int|float|null $score): string
    {
        $score = max(0, min(100, (int) round((float) $score)));

        foreach (self::RISK_BANDS as $label => $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return $label;
            }
        }

        return 'Low';
    }

    /**
     * Turn a flat list into `[label => label]` pairs for Blade `<select>` tags.
     *
     * @param  list<string>  $values
     * @return array<string, string>
     */
    public static function options(array $values): array
    {
        return array_combine($values, $values);
    }
}
