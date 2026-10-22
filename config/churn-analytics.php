<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Prototype Identity
    |--------------------------------------------------------------------------
    |
    | This project is an academic analytics prototype. The wording shown in the
    | interface makes that explicit so the dashboards are never mistaken for
    | official SLT-MOBITEL reporting.
    |
    */

    'prototype' => [
        'label' => env('CHURN_PROTOTYPE_LABEL', 'Analytics Prototype'),
        'disclaimer' => 'Academic analytics prototype built for a Decision Analytics assignment. All data is synthetic and all figures are model outputs, not official SLT-MOBITEL reporting.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Synthetic Data Generation
    |--------------------------------------------------------------------------
    |
    | "customer_count" is the number of synthetic customers produced by
    | `php artisan db:seed`. The brief asks for 5,000-10,000; the default sits in
    | the middle of that range. "usage_months" controls how many monthly usage
    | snapshots each subscriber receives, which dominates the total document
    | count of the dataset.
    |
    */

    'seeding' => [
        'customer_count' => (int) env('CHURN_SEED_CUSTOMERS', 7500),
        'usage_months' => (int) env('CHURN_SEED_USAGE_MONTHS', 18),
        'churn_rate' => (float) env('CHURN_SEED_CHURN_RATE', 0.315),
        'chunk_size' => (int) env('CHURN_SEED_CHUNK', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Churn Risk Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Project-defined, rule-based churn risk score. Maximum attainable score is
    | 100. THIS IS AN ACADEMIC HEURISTIC - IT IS NOT AN OFFICIAL SLT-MOBITEL
    | SCORING MODEL. Changing a weight here changes every risk figure in the
    | Risk & Retention page and the risk KPI on the dashboard.
    |
    */

    'risk' => [
        'max_score' => 100,

        'weights' => [
            'low_usage' => 20,
            'multiple_complaints' => 20,
            'long_recharge_gap' => 20,
            'short_tenure' => 15,
            'high_monthly_cost' => 15,
            'service_issue' => 10,
        ],

        'thresholds' => [
            // Latest month below this data volume (GB) counts as "low usage".
            'low_usage_gb' => 5.0,

            // Complaint count at or above which "multiple complaints" triggers.
            'multiple_complaints_count' => 2,

            // Days since the last recharge at or above which a "recharge gap" exists.
            'long_recharge_gap_days' => 45,

            // Tenure below this (months) counts as "short tenure".
            'short_tenure_months' => 6,

            // Monthly charge above this (LKR) counts as "high cost".
            'high_monthly_charge' => 2000,
        ],

        'bands' => [
            'Low' => ['min' => 0, 'max' => 29],
            'Medium' => ['min' => 30, 'max' => 59],
            'High' => ['min' => 60, 'max' => 100],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Display
    |--------------------------------------------------------------------------
    |
    | Number of rows shown on the dashboard "recent high-risk customers" table
    * and the default page size of the management CRUD tables.
    |
    */

    'display' => [
        'recent_high_risk' => 10,
        'paginate' => 15,
    ],

];
